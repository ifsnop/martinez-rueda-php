<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests\Support;

/**
 * Compara dos regiones poligonales SIN usar la librería que se está probando
 * (ISSUES.md #4). Dos regiones se consideran iguales si:
 *
 *  1. Fronteras: cada vértice de una está a menos de $delta de la frontera de la otra,
 *     y viceversa. Detecta piezas que sobran o faltan (por pequeñas o lejanas que
 *     sean), agujeros perdidos y vértices desplazados más de $delta.
 *  2. Interiores: en puntos de muestra (deterministas) que no están cerca de ninguna
 *     frontera, ambas regiones dicen lo mismo con la regla par-impar. Detecta
 *     interiores invertidos con la misma frontera.
 *
 * El orden de los anillos, el vértice inicial, la orientación y los vértices colineales
 * de más no importan. Acepta anillos abiertos o cerrados, y tanto una lista de anillos
 * como una lista de polígonos (MultiPolygon: [polígono [anillo [[x,y], ...]]]).
 */
final class RegionComparator
{
    /** δ por defecto: 1e-10 relativo a la magnitud de las coordenadas (mínimo 1e-10). */
    public static function defaultDelta(array $a, array $b): float
    {
        $max = 1.0;
        foreach ([self::rings($a), self::rings($b)] as $rings) {
            foreach ($rings as $r) {
                foreach ($r as $p) {
                    $max = max($max, abs($p[0]), abs($p[1]));
                }
            }
        }
        return 1e-10 * $max;
    }

    /**
     * @param string|null $why (salida) motivo de la primera diferencia encontrada
     */
    public static function equal(array $a, array $b, ?float $delta = null, ?string &$why = null, int $samples = 2000): bool
    {
        $why = null;
        $delta ??= self::defaultDelta($a, $b);
        $ra = self::rings($a);
        $rb = self::rings($b);

        // 1) fronteras en los dos sentidos
        $ia = new BoundaryIndex($ra);
        $ib = new BoundaryIndex($rb);
        foreach ([['a', $ra, 'b', $ib], ['b', $rb, 'a', $ia]] as [$na, $from, $nb, $to]) {
            if (($why = self::verticesOffBoundary($na, $from, $nb, $to, $delta)) !== null) {
                return false;
            }
        }
        if ($ra === [] && $rb === []) {
            return true;
        }

        // 2) interiores por muestreo
        $all = array_merge(...array_merge($ra, $rb));
        $xs = array_column($all, 0);
        $ys = array_column($all, 1);
        [$x0, $x1, $y0, $y1] = [min($xs), max($xs), min($ys), max($ys)];
        $skip = 10 * $delta;
        $seed = 12345;
        for ($k = 0; $k < $samples; $k++) {
            $seed = ($seed * 1103515245 + 12345) % 2147483648;
            $x = $x0 + ($seed / 2147483648) * ($x1 - $x0);
            $seed = ($seed * 1103515245 + 12345) % 2147483648;
            $y = $y0 + ($seed / 2147483648) * ($y1 - $y0);
            $inA = GeometryOracle::inside($ra, $x, $y);
            if ($inA !== GeometryOracle::inside($rb, $x, $y) && !$ia->near($x, $y, $skip) && !$ib->near($x, $y, $skip)) {
                $why = sprintf('el punto [%.12g, %.12g] está %s de a pero %s de b', $x, $y,
                    $inA ? 'dentro' : 'fuera', $inA ? 'fuera' : 'dentro');
                return false;
            }
        }
        return true;
    }

    /** Lista plana de anillos abiertos a partir de anillos o polígonos. */
    public static function rings(array $geom): array
    {
        $out = [];
        $walk = function (array $g) use (&$walk, &$out): void {
            if ($g === []) {
                return;
            }
            if (is_array($g[0]) && isset($g[0][0]) && !is_array($g[0][0])) {
                // es un anillo: [[x,y], ...]
                $r = array_map(fn($p) => [(float)$p[0], (float)$p[1]], $g);
                $n = count($r);
                if ($n > 1 && $r[0] == $r[$n - 1]) {
                    array_pop($r);
                }
                if ($r !== []) {
                    $out[] = $r;
                }
                return;
            }
            foreach ($g as $sub) {
                if (is_array($sub)) {
                    $walk($sub);
                }
            }
        };
        $walk($geom);
        return $out;
    }

    /**
     * Comprobación exacta para R = A xor B sin conocer el resultado esperado: la frontera
     * de A xor B es la unión de las fronteras de A y B (salvo tramos comunes). Así que
     * cada vértice de R debe estar sobre la frontera de A o de B, y cada vértice de A y de B
     * sobre la frontera de R. Detecta anillos perdidos o sobrantes por pequeños que sean,
     * que el muestreo de puntos no ve en polígonos grandes (p.ej. una isla de Europa).
     */
    public static function xorBoundariesConsistent(array $A, array $B, array $R, ?float $delta = null, ?string &$why = null): bool
    {
        $why = null;
        $ra = self::rings($A);
        $rb = self::rings($B);
        $rr = self::rings($R);
        $delta ??= self::defaultDelta(array_merge($ra, $rb), $rr);
        $why = self::verticesOffBoundary('R', $rr, 'A∪B', new BoundaryIndex(array_merge($ra, $rb)), $delta)
            ?? self::verticesOffBoundary('A∪B', array_merge($ra, $rb), 'R', new BoundaryIndex($rr), $delta);
        return $why === null;
    }

    /** Primer vértice de $from que no está a menos de $delta de la frontera indexada en $to (o null). */
    private static function verticesOffBoundary(string $na, array $from, string $nb, BoundaryIndex $to, float $delta): ?string
    {
        foreach ($from as $ri => $ring) {
            foreach ($ring as $vi => $p) {
                if (!$to->near($p[0], $p[1], $delta)) {
                    $d = $to->distance($p[0], $p[1]);
                    return sprintf('vértice %s[%d][%d] = %s está a %s de la frontera de %s (δ = %.1e)',
                        $na, $ri, $vi, json_encode($p), is_infinite($d) ? 'INF (región vacía)' : sprintf('%.3e', $d), $nb, $delta);
                }
            }
        }
        return null;
    }
}
