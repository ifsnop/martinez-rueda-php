<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/**
 * Normalización de las entradas antes de una operación booleana (ISSUES.md #2).
 *
 * Problema: cuando los datos vienen redondeados (p.ej. GeoJSON con 7 decimales), dos
 * polígonos que comparten un borde, o un vértice que estaba exactamente sobre la arista
 * vecina, quedan separados por unos milímetros. El barrido trabaja con tolerancias mucho
 * menores (1e-12), ve esas aristas casi coincidentes como distintas, y las decisiones de
 * orden e intersección se contradicen: se pierden o se añaden regiones enteras.
 *
 * Solución (Milenkovic 1988, "data normalization": vertex shifting + edge cracking),
 * aplicada a TODOS los operandos a la vez:
 *  1. Vértices a menos de $tol entre sí (en x y en y) se unifican en el primero visto.
 *  2. Si un vértice v está a menos de $tol de una arista (sin ser uno de sus extremos),
 *     se inserta v en la arista: la arista pasa exactamente por v.
 *  3. Se repite el paso 2 hasta que no hay cambios (normalmente 1-2 pasadas).
 * Ninguna coordenada se mueve más de $tol.
 *
 * La tolerancia por defecto la da detectPrecision(): el cuanto de los datos según su
 * número de decimales, solo si tienen entre 5 y 9 (lon/lat redondeados). Con enteros o
 * con precisión completa no se normaliza, porque no hay forma de saber si están
 * redondeados (y una tolerancia de 1 con coordenadas enteras destrozaría la geometría).
 *
 * Índices: rejilla hash de celda $tol para el paso 1, y rejilla de celda igual a la
 * longitud media de arista para el paso 2 (cada arista solo mira las celdas de su
 * bounding box). Coste casi lineal: ~30 ms para Europa + una copia desplazada.
 */
final class InputNormalizer
{
    /** Decimales mínimo y máximo para considerar que los datos están redondeados. */
    public const MIN_DECIMALS = 5;
    public const MAX_DECIMALS = 9;

    /**
     * Precisión de los datos: 10^-d, con d el mayor número de decimales de todas las
     * coordenadas, si MIN_DECIMALS <= d <= MAX_DECIMALS. En otro caso 0.0 (no normalizar).
     *
     * @param Polygon[] $polygons
     */
    public static function detectPrecision(array $polygons): float
    {
        $maxDec = 0;
        foreach ($polygons as $p) {
            foreach ($p->regions as $region) {
                foreach ($region as $pt) {
                    foreach ([$pt->x, $pt->y] as $c) {
                        $d = $maxDec; // solo interesa si supera el máximo actual
                        while ($d <= self::MAX_DECIMALS && round($c, $d) != $c) {
                            $d++;
                        }
                        if ($d > self::MAX_DECIMALS) {
                            return 0.0; // precisión completa: no está redondeado
                        }
                        $maxDec = $d;
                    }
                }
            }
        }
        return $maxDec >= self::MIN_DECIMALS ? 10 ** -$maxDec : 0.0;
    }

    /**
     * @param Polygon[] $polygons
     * @return Polygon[] polígonos normalizados (mismas claves); los originales no se modifican
     */
    public static function normalize(array $polygons, float $tol): array
    {
        if ($tol <= 0.0) {
            return $polygons;
        }

        // --- 1) unificación de vértices (rejilla de celda $tol) ---
        $cells = [];
        $verts = [];               // vértices únicos [x, y]; los anillos guardan ids
        $ringsByPoly = [];
        foreach ($polygons as $k => $poly) {
            $rings = [];
            foreach ($poly->regions as $region) {
                $ring = [];
                foreach ($region as $pt) {
                    $ring[] = self::unify($pt->x, $pt->y, $tol, $cells, $verts);
                }
                $rings[] = $ring;
            }
            $ringsByPoly[$k] = $rings;
        }

        // --- 2) índice de vértices para el cracking (celda ~ longitud media de arista) ---
        $edgeLen = 0.0;
        $edgeCount = 0;
        foreach ($ringsByPoly as $rings) {
            foreach ($rings as $ring) {
                $n = count($ring);
                for ($i = 0; $i < $n; $i++) {
                    [$ax, $ay] = $verts[$ring[$i]];
                    [$bx, $by] = $verts[$ring[($i + 1) % $n]];
                    $edgeLen += abs($bx - $ax) + abs($by - $ay);
                    $edgeCount++;
                }
            }
        }
        $cell = max($tol * 4, $edgeCount > 0 ? $edgeLen / $edgeCount : 1.0);
        $grid = [];
        foreach ($verts as $id => [$x, $y]) {
            $grid[(int)floor($x / $cell) . ',' . (int)floor($y / $cell)][] = $id;
        }

        // --- 3) edge cracking hasta punto fijo ---
        for ($pass = 0; $pass < 8; $pass++) {
            $changed = false;
            foreach ($ringsByPoly as $k => $rings) {
                foreach ($rings as $ri => $ring) {
                    $out = [];
                    $n = count($ring);
                    for ($i = 0; $i < $n; $i++) {
                        $ia = $ring[$i];
                        $ib = $ring[($i + 1) % $n];
                        $out[] = $ia;
                        if ($ia === $ib) {
                            continue;
                        }
                        foreach (self::verticesNearEdge($ia, $ib, $verts, $grid, $cell, $tol) as $v) {
                            $out[] = $v;
                            $changed = true;
                        }
                    }
                    $ringsByPoly[$k][$ri] = self::dedup($out);
                }
            }
            if (!$changed) {
                break;
            }
        }

        // --- 4) reconstruir polígonos (anillos con menos de 3 vértices se descartan) ---
        $result = [];
        foreach ($polygons as $k => $poly) {
            $regions = [];
            foreach ($ringsByPoly[$k] as $ring) {
                if (count($ring) >= 3) {
                    $regions[] = array_map(fn(int $id) => new Point($verts[$id][0], $verts[$id][1]), $ring);
                }
            }
            $p = Polygon::create();
            $p->regions = $regions;
            $p->isInverted = $poly->isInverted ?? false;
            $p->numPoints = array_sum(array_map('count', $regions));
            $result[$k] = $p;
        }
        return $result;
    }

    private static function unify(float $x, float $y, float $tol, array &$cells, array &$verts): int
    {
        $cx = (int)floor($x / $tol);
        $cy = (int)floor($y / $tol);
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                foreach ($cells[($cx + $dx) . ',' . ($cy + $dy)] ?? [] as $id) {
                    if (abs($verts[$id][0] - $x) < $tol && abs($verts[$id][1] - $y) < $tol) {
                        return $id;
                    }
                }
            }
        }
        $id = count($verts);
        $verts[] = [$x, $y];
        $cells[$cx . ',' . $cy][] = $id;
        return $id;
    }

    /** Ids de los vértices a menos de $tol del interior de la arista a-b, ordenados a lo largo de ella. */
    private static function verticesNearEdge(int $ia, int $ib, array $verts, array $grid, float $cell, float $tol): array
    {
        [$ax, $ay] = $verts[$ia];
        [$bx, $by] = $verts[$ib];
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $l2 = $dx * $dx + $dy * $dy;
        $len = sqrt($l2);
        $x0 = (int)floor((min($ax, $bx) - $tol) / $cell);
        $x1 = (int)floor((max($ax, $bx) + $tol) / $cell);
        $y0 = (int)floor((min($ay, $by) - $tol) / $cell);
        $y1 = (int)floor((max($ay, $by) + $tol) / $cell);
        $found = [];
        for ($gx = $x0; $gx <= $x1; $gx++) {
            for ($gy = $y0; $gy <= $y1; $gy++) {
                foreach ($grid[$gx . ',' . $gy] ?? [] as $id) {
                    if ($id === $ia || $id === $ib) {
                        continue;
                    }
                    [$vx, $vy] = $verts[$id];
                    $t = (($vx - $ax) * $dx + ($vy - $ay) * $dy) / $l2;
                    if ($t <= 0.0 || $t >= 1.0) {
                        continue;
                    }
                    if (abs(($vx - $ax) * $dy - ($vy - $ay) * $dx) / $len < $tol) {
                        $found[$id] = $t;
                    }
                }
            }
        }
        asort($found);
        return array_keys($found);
    }

    /** Quita repetidos consecutivos, el cierre duplicado y las espigas A-B-A. */
    private static function dedup(array $ring): array
    {
        $out = [];
        foreach ($ring as $id) {
            if ($out === [] || end($out) !== $id) {
                $out[] = $id;
            }
        }
        while (count($out) > 1 && $out[0] === end($out)) {
            array_pop($out);
        }
        $changed = true;
        while ($changed && count($out) >= 3) {
            $changed = false;
            $n = count($out);
            for ($i = 0; $i < $n; $i++) {
                if ($out[($i + $n - 1) % $n] === $out[($i + 1) % $n]) {
                    array_splice($out, $i, 1);
                    $changed = true;
                    break;
                }
            }
        }
        return $out;
    }
}
