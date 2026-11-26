<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests\Support;

use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;

/**
 * Oráculo independiente de la librería para comprobar operaciones booleanas:
 * punto-en-polígono con la regla par-impar (la que aplica la librería) sobre
 * puntos de muestra aleatorios, ignorando los cercanos a cualquier arista.
 */
final class GeometryOracle
{
    /** Operación => [llamada a la librería, verdad booleana] */
    public static function operations(): array
    {
        return [
            'union'      => [fn($a, $b) => Algorithm::union($a, $b),      fn(bool $x, bool $y) => $x || $y],
            'intersect'  => [fn($a, $b) => Algorithm::intersect($a, $b),  fn(bool $x, bool $y) => $x && $y],
            'difference' => [fn($a, $b) => Algorithm::difference($a, $b), fn(bool $x, bool $y) => $x && !$y],
            'xor'        => [fn($a, $b) => Algorithm::xoring($a, $b),     fn(bool $x, bool $y) => $x !== $y],
        ];
    }

    /** Punto en polígono con la regla par-impar sobre todos los anillos. */
    public static function inside(array $rings, float $x, float $y): bool
    {
        $in = false;
        foreach ($rings as $r) {
            $n = count($r);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                [$xi, $yi] = $r[$i];
                [$xj, $yj] = $r[$j];
                if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                    $in = !$in;
                }
            }
        }
        return $in;
    }

    /**
     * Número de puntos de $pts mal clasificados en el resultado $R de una operación
     * (anillos de A, B y R; $truth = verdad booleana de la operación). Los puntos a menos
     * de $eps de alguna arista no cuentan. La distancia solo se calcula para los puntos
     * que discrepan, así que es barato aunque los polígonos tengan miles de vértices.
     */
    public static function countWrong(array $A, array $B, array $R, callable $truth, array $pts, float $eps): int
    {
        $wrong = 0;
        foreach ($pts as [$x, $y]) {
            if (self::inside($R, $x, $y) !== $truth(self::inside($A, $x, $y), self::inside($B, $x, $y))
                && !self::nearEdge([$A, $B, $R], $x, $y, $eps)) {
                $wrong++;
            }
        }
        return $wrong;
    }

    public static function nearEdge(array $ringSets, float $x, float $y, float $eps): bool
    {
        foreach ($ringSets as $rings) {
            foreach ($rings as $r) {
                $n = count($r);
                for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                    [$ax, $ay] = $r[$j];
                    [$bx, $by] = $r[$i];
                    $dx = $bx - $ax;
                    $dy = $by - $ay;
                    $l = $dx * $dx + $dy * $dy;
                    $t = $l > 0 ? max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / $l)) : 0.0;
                    if (hypot($x - ($ax + $t * $dx), $y - ($ay + $t * $dy)) < $eps) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Ejecuta las cuatro operaciones sobre A y B y devuelve la lista de
     * discrepancias con el oráculo (vacía si todo es correcto).
     *
     * @return string[]
     */
    /**
     * $minEps: distancia mínima a las aristas por debajo de la cual un punto no se evalúa.
     * Con datos redondeados conviene pasar ~2 cuantos: la normalización de entradas
     * (InputNormalizer) puede mover las aristas hasta un cuanto, y esos puntos son ambiguos.
     */
    public static function check(array $A, array $B, int $samples = 300, float $minEps = 0.0): array
    {
        $all = array_merge(...array_merge($A, $B));
        $xs = array_column($all, 0);
        $ys = array_column($all, 1);
        [$minx, $maxx, $miny, $maxy] = [min($xs), max($xs), min($ys), max($ys)];
        $eps = max(1e-6 * max($maxx - $minx, $maxy - $miny), $minEps);
        $pts = [];
        for ($k = 0; $k < $samples; $k++) {
            $pts[] = [PolygonGenerator::rnd($minx, $maxx), PolygonGenerator::rnd($miny, $maxy)];
        }

        $problems = [];
        foreach (self::operations() as $name => [$op, $truth]) {
            try {
                $R = $op(Polygon::create()->fillFromArray($A), Polygon::create()->fillFromArray($B))->getArray();
            } catch (\Throwable $e) {
                $problems[] = "$name: excepción " . get_class($e) . ': ' . $e->getMessage();
                continue;
            }
            foreach ($R as $ring) {
                if (count($ring) < 3) {
                    $problems[] = "$name: anillo degenerado en el resultado";
                    continue 2;
                }
                foreach ($ring as $p) {
                    if (!is_finite($p[0]) || !is_finite($p[1])) {
                        $problems[] = "$name: coordenada no finita en el resultado";
                        continue 3;
                    }
                }
            }
            $wrong = self::countWrong($A, $B, $R, $truth, $pts, $eps);
            if ($wrong > 0) {
                $problems[] = "$name: $wrong/" . count($pts) . " puntos mal clasificados" . PHP_EOL
                    . "  A = " . json_encode($A) . PHP_EOL
                    . "  B = " . json_encode($B) . PHP_EOL
                    . "  R = " . json_encode($R);
            }
        }
        return $problems;
    }

    /**
     * Genera $iters pares con el generador dado, los coloca con offset/escala
     * (y redondeo opcional a $round decimales) y devuelve todas las discrepancias.
     *
     * @return string[]
     */
    public static function fuzz(string $gen, int $seed, int $iters, float|array $offset, float $scale, ?int $round = null): array
    {
        mt_srand(crc32($gen) ^ $seed);
        $problems = [];
        for ($it = 0; $it < $iters; $it++) {
            [$A, $B] = PolygonGenerator::generate($gen);
            $A = PolygonGenerator::transform($A, $offset, $scale, $round);
            $B = PolygonGenerator::transform($B, $offset, $scale, $round);
            $minEps = $round === null ? 0.0 : 2 * 10 ** -$round;
            foreach (self::check($A, $B, 300, $minEps) as $p) {
                $problems[] = "(iter $it) $p";
            }
        }
        return $problems;
    }
}
