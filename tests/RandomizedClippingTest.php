<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;

include_once('autoloader.php');

/**
 * Test aleatorio (property-based) de las cuatro operaciones booleanas.
 *
 * Para cada par de polígonos generado aleatoriamente se calcula union,
 * intersect, difference y xor, y se compara el resultado con un oráculo
 * independiente: para puntos de muestra aleatorios, "dentro del resultado"
 * debe ser igual a op(dentro de A, dentro de B), usando la regla par-impar
 * (la misma que aplica la librería). Se ignoran los puntos muy cercanos a
 * cualquier arista, donde la clasificación es ambigua.
 *
 * Las semillas son fijas, así que el test es determinista. Variables de entorno:
 *   FUZZ_ITERS  pares de polígonos por generador y semilla (por defecto 20)
 *   FUZZ_SEEDS  número de semillas por generador (por defecto 20)
 */
final class RandomizedClippingTest extends TestCase
{
    private const SAMPLES = 300;

    /** Generadores => descripción */
    private const GENERATORS = [
        'star'    => 'polígonos estrellados simples aleatorios',
        'grid'    => 'rectángulos enteros: aristas compartidas, solapes colineales, vértices comunes',
        'rotgrid' => 'como grid pero girados un ángulo arbitrario (aristas compartidas inexactas)',
        'holes'   => 'polígono con agujero frente a polígono estrellado',
        'selfx'   => 'polígonos aleatorios auto-intersectantes (regla par-impar)',
        'shared'  => 'B reutiliza vértices exactos de A',
        'tiles'   => 'teselas adyacentes (triángulos con aristas comunes) repartidas entre A y B',
    ];

    /** Desplazamiento/escala para simular coordenadas lon/lat habituales. */
    private const PLACEMENTS = [
        'unit'   => [0.0, 1.0],
        'lonlat' => [-3.7, 0.01],   // rasgos de ~1-10 km en grados, cerca de Madrid
    ];

    public static function provideCases(): array
    {
        $seeds = (int)(getenv('FUZZ_SEEDS') ?: 2);
        $cases = [];
        foreach (array_keys(self::GENERATORS) as $gen) {
            foreach (self::PLACEMENTS as $pname => [$offset, $scale]) {
                for ($seed = 1; $seed <= $seeds; $seed++) {
                    $cases["$gen/$pname/seed$seed"] = [$gen, $seed, $offset, $scale];
                }
            }
        }
        return $cases;
    }

    /**
     * @dataProvider provideCases
     */
    public function testOperationsMatchPointOracle(string $gen, int $seed, float $offset, float $scale): void
    {
        $iters = (int)(getenv('FUZZ_ITERS') ?: 20);
        mt_srand(crc32($gen) ^ $seed);

        $ops = [
            'union'      => [fn($a, $b) => Algorithm::union($a, $b),      fn(bool $x, bool $y) => $x || $y],
            'intersect'  => [fn($a, $b) => Algorithm::intersect($a, $b),  fn(bool $x, bool $y) => $x && $y],
            'difference' => [fn($a, $b) => Algorithm::difference($a, $b), fn(bool $x, bool $y) => $x && !$y],
            'xor'        => [fn($a, $b) => Algorithm::xoring($a, $b),     fn(bool $x, bool $y) => $x !== $y],
        ];

        $failures = [];
        for ($it = 0; $it < $iters; $it++) {
            [$A, $B] = self::generate($gen);
            $A = self::transform($A, $offset, $scale);
            $B = self::transform($B, $offset, $scale);

            $all = array_merge(...array_merge($A, $B));
            $xs = array_column($all, 0);
            $ys = array_column($all, 1);
            [$minx, $maxx, $miny, $maxy] = [min($xs), max($xs), min($ys), max($ys)];
            $eps = 1e-6 * max($maxx - $minx, $maxy - $miny);
            $pts = [];
            for ($k = 0; $k < self::SAMPLES; $k++) {
                $pts[] = [self::rnd($minx, $maxx), self::rnd($miny, $maxy)];
            }

            foreach ($ops as $name => [$op, $truth]) {
                $R = $op(Polygon::create()->fillFromArray($A), Polygon::create()->fillFromArray($B))->getArray();

                foreach ($R as $ring) {
                    $this->assertGreaterThanOrEqual(3, count($ring), "anillo degenerado en $name");
                    foreach ($ring as $p) {
                        $this->assertTrue(is_finite($p[0]) && is_finite($p[1]), "coordenada no finita en $name");
                    }
                }

                $wrong = 0;
                $checked = 0;
                foreach ($pts as [$x, $y]) {
                    if (self::nearEdge([$A, $B, $R], $x, $y, $eps)) {
                        continue;
                    }
                    $checked++;
                    if (self::inside($R, $x, $y) !== $truth(self::inside($A, $x, $y), self::inside($B, $x, $y))) {
                        $wrong++;
                    }
                }
                if ($wrong > 0) {
                    $failures[] = "$name (iter $it): $wrong/$checked puntos mal clasificados" . PHP_EOL
                        . "  A = " . json_encode($A) . PHP_EOL
                        . "  B = " . json_encode($B) . PHP_EOL
                        . "  R = " . json_encode($R);
                }
            }
        }

        $this->assertSame(
            [],
            array_slice($failures, 0, 3),
            count($failures) . " operaciones incorrectas (generador {$gen}: " . self::GENERATORS[$gen] . ")"
        );
    }

    /**
     * Regresión: dos cuadrados con una arista común dentro del mismo polígono,
     * operados contra un polígono cuya bounding box no se solapa (atajo de
     * unionSegments/xorSegments/differenceSegments). Antes se perdía el segundo cuadrado.
     */
    public function testAdjacentRingsWithDisjointBoundingBoxes(): void
    {
        $two = [[[0, 0], [1, 0], [1, 1], [0, 1]], [[1, 0], [2, 0], [2, 1], [1, 1]]];
        $far = [[[10, 10], [11, 10], [11, 11], [10, 11]]];
        $mk = fn($r) => Polygon::create()->fillFromArray($r);

        $union = Algorithm::union($mk($two), $mk($far))->getArray();
        $this->assertTrue(self::inside($union, 1.5, 0.5), 'union pierde el segundo cuadrado');
        $this->assertTrue(self::inside($union, 0.5, 0.5));
        $this->assertTrue(self::inside($union, 10.5, 10.5));

        $xor = Algorithm::xoring($mk($two), $mk($far))->getArray();
        $this->assertTrue(self::inside($xor, 1.5, 0.5), 'xor pierde el segundo cuadrado');

        $diff = Algorithm::difference($mk($two), $mk($far))->getArray();
        $this->assertTrue(self::inside($diff, 1.5, 0.5), 'difference pierde el segundo cuadrado');
        $this->assertTrue(self::inside($diff, 0.5, 0.5));
        $this->assertFalse(self::inside($diff, 10.5, 10.5));
    }

    // ── Generadores ──────────────────────────────────────────────────────────

    private static function rnd(float $a, float $b): float
    {
        return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
    }

    private static function star(float $cx, float $cy, float $r, int $n): array
    {
        $angs = [];
        for ($i = 0; $i < $n; $i++) {
            $angs[] = self::rnd(0, 2 * M_PI);
        }
        sort($angs);
        $ring = [];
        foreach ($angs as $a) {
            $rr = self::rnd(0.3 * $r, $r);
            $ring[] = [$cx + $rr * cos($a), $cy + $rr * sin($a)];
        }
        return $ring;
    }

    private static function rect(float $x0, float $y0, float $x1, float $y1): array
    {
        return [[$x0, $y0], [$x1, $y0], [$x1, $y1], [$x0, $y1]];
    }

    private static function rotate(array $ring, float $ang): array
    {
        $c = cos($ang);
        $s = sin($ang);
        return array_map(fn($p) => [$p[0] * $c - $p[1] * $s, $p[0] * $s + $p[1] * $c], $ring);
    }

    /** @return array{0: array, 1: array} anillos de A y de B, en el rango aproximado [-10, 10] */
    private static function generate(string $g): array
    {
        switch ($g) {
            case 'star':
                return [
                    [self::star(self::rnd(-2, 2), self::rnd(-2, 2), self::rnd(1, 5), mt_rand(3, 25))],
                    [self::star(self::rnd(-2, 2), self::rnd(-2, 2), self::rnd(1, 5), mt_rand(3, 25))],
                ];
            case 'grid':
                $mk = function () {
                    $rings = [];
                    $k = mt_rand(1, 3);
                    for ($i = 0; $i < $k; $i++) {
                        $x = mt_rand(-4, 3);
                        $y = mt_rand(-4, 3);
                        $rings[] = self::rect($x, $y, $x + mt_rand(1, 4), $y + mt_rand(1, 4));
                    }
                    return $rings;
                };
                return [$mk(), $mk()];
            case 'rotgrid':
                $a = self::rnd(0, M_PI);
                [$A, $B] = self::generate('grid');
                return [
                    array_map(fn($r) => self::rotate($r, $a), $A),
                    array_map(fn($r) => self::rotate($r, $a), $B),
                ];
            case 'holes':
                $s = self::rnd(3, 6);
                return [
                    [self::rect(-$s, -$s, $s, $s), self::rect(-$s / 3, -$s / 3, $s / 3, $s / 3)],
                    [self::star(self::rnd(-3, 3), self::rnd(-3, 3), self::rnd(1, 4), mt_rand(3, 12))],
                ];
            case 'selfx':
                $mk = function () {
                    $r = [];
                    $n = mt_rand(4, 10);
                    for ($i = 0; $i < $n; $i++) {
                        $r[] = [self::rnd(-5, 5), self::rnd(-5, 5)];
                    }
                    return [$r];
                };
                return [$mk(), $mk()];
            case 'shared':
                $A = self::star(0, 0, 5, mt_rand(5, 15));
                $B = [];
                foreach ($A as $p) {
                    if (mt_rand(0, 2) === 0) {
                        $B[] = $p;
                    }
                }
                $B[] = [self::rnd(-6, 6), self::rnd(-6, 6)];
                if (count($B) < 3) {
                    $B = array_merge($B, self::star(1, 1, 3, 3));
                }
                $cx = array_sum(array_column($B, 0)) / count($B);
                $cy = array_sum(array_column($B, 1)) / count($B);
                usort($B, fn($p, $q) => atan2($p[1] - $cy, $p[0] - $cx) <=> atan2($q[1] - $cy, $q[0] - $cx));
                return [[$A], [$B]];
            case 'tiles':
                $n = mt_rand(5, 20);
                $pts = self::star(0, 0, 5, $n);
                $c = [self::rnd(-0.5, 0.5), self::rnd(-0.5, 0.5)];
                $A = [];
                $B = [];
                for ($i = 0; $i < $n; $i++) {
                    $tri = [$c, $pts[$i], $pts[($i + 1) % $n]];
                    if ($i % 2) {
                        $A[] = $tri;
                    } else {
                        $B[] = $tri;
                    }
                }
                return [$A, $B];
        }
        throw new InvalidArgumentException("generador desconocido: $g");
    }

    private static function transform(array $rings, float $offset, float $scale): array
    {
        return array_map(
            fn($r) => array_map(fn($p) => [$p[0] * $scale + $offset, $p[1] * $scale + $offset], $r),
            $rings
        );
    }

    // ── Oráculo ──────────────────────────────────────────────────────────────

    /** Punto en polígono con la regla par-impar sobre todos los anillos. */
    private static function inside(array $rings, float $x, float $y): bool
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

    private static function nearEdge(array $ringSets, float $x, float $y, float $eps): bool
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
}
