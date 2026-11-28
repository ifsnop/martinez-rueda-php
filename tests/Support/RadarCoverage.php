<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests\Support;

use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;

/**
 * Caso de uso real de la librería: cálculo de coberturas radar (zonas sin cobertura,
 * cobertura simple, doble, triple...). Genera escenarios deterministas y comprueba
 * los resultados SIN usar la librería para juzgarlos.
 *
 * Escenario: N radares situados al azar dentro de un círculo de 300 km alrededor de
 * (-3.7, 40.4), con alcance de 150-250 km (o 250-450 km en "alcance largo") y bordes
 * muy irregulares, como los del enmascaramiento del terreno: ondulación de baja
 * frecuencia, rugosidad acimut a acimut y 2-4 sectores de sombra por radar donde el
 * alcance cae al 25-60 %. Coordenadas lon/lat; opcionalmente redondeadas a N decimales.
 *
 * Capas de cobertura EXACTA k = 0..N, calculadas de dos formas independientes:
 *  - por combinaciones: al menos k = unión de las intersecciones de cada grupo de k
 *    radares; exactamente k = (al menos k) − (al menos k+1);
 *  - incremental: radar a radar, nueva_k = (k − R) ∪ (k−1 ∩ R). Es la que más
 *    encadena operaciones (los resultados acaban con 15-17 decimales).
 *
 * Comprobaciones (todas independientes de la librería):
 *  1. Las capas de los dos métodos son la misma región (RegionComparator: fronteras
 *     a menos de δ en ambos sentidos + interiores por muestreo).
 *  2. Las capas reparten la caja sin huecos ni solapes: Σ área(capas) = área(caja).
 *  3. Oráculo de puntos: cada punto de muestra está en la capa k igual al número de
 *     radares que lo cubren (contados con punto-en-polígono sobre las coberturas).
 */
final class RadarCoverage
{
    private const KM_PER_DEG = 111.32;
    private const LON0 = -3.7;
    private const LAT0 = 40.4;

    /**
     * @return array{sites: array, coverages: array} coberturas: un anillo por radar
     */
    public static function generate(int $seed, int $radars, ?int $round = null, bool $longRange = false, int $vertices = 720, float $siteRadiusKm = 300.0): array
    {
        mt_srand(8000 + $seed + 1000 * ($radars - 8));        // con 8 radares: 8000 + seed (casos ya publicados)
        $sites = [];
        $coverages = [];
        for ($i = 0; $i < $radars; $i++) {
            $d = $siteRadiusKm * sqrt(self::rnd(0, 1));                 // uniforme en el disco
            $th = self::rnd(0, 2 * M_PI);
            [$lon, $lat] = self::offset(self::LON0, self::LAT0, $d * sin($th), $d * cos($th));
            $range = $longRange ? self::rnd(250, 450) : self::rnd(150, 250);
            $sites[] = [$lon, $lat, $range];
            $coverages[] = [self::coverage($lon, $lat, $range, $vertices, $round)];
        }
        return ['sites' => $sites, 'coverages' => $coverages];
    }

    /**
     * Capas de cobertura exacta 0..N por combinaciones. La capa 0 es la caja menos la unión.
     * @return array<int, array> anillos de cada capa
     */
    public static function layersByCombinations(array $coverages, array $box): array
    {
        $n = count($coverages);
        $atLeast = [0 => self::poly($box)];
        for ($k = 1; $k <= $n; $k++) {
            $parts = [];
            foreach (self::subsets($n, $k) as $s) {
                $r = Algorithm::intersectMany(array_map(fn($i) => self::poly($coverages[$i]), $s));
                if ($r->regions) {
                    $parts[] = $r;
                }
            }
            $atLeast[$k] = $parts ? Algorithm::unionMany($parts) : self::poly([]);
        }
        $atLeast[$n + 1] = self::poly([]);
        $layers = [];
        for ($k = 0; $k <= $n; $k++) {
            $layers[$k] = Algorithm::difference($atLeast[$k], $atLeast[$k + 1])->getArray();
        }
        return $layers;
    }

    /** Capas de cobertura exacta 0..N añadiendo radar a radar. */
    public static function layersIncremental(array $coverages, array $box): array
    {
        $n = count($coverages);
        $layers = [0 => self::poly($box)];
        for ($k = 1; $k <= $n; $k++) {
            $layers[$k] = self::poly([]);
        }
        foreach ($coverages as $c) {
            $R = self::poly($c);
            $new = [];
            for ($k = 0; $k <= $n; $k++) {
                $stay = Algorithm::difference($layers[$k], $R);
                $new[$k] = $k === 0 ? $stay : Algorithm::union($stay, Algorithm::intersect($layers[$k - 1], $R));
            }
            $layers = $new;
        }
        return array_map(fn(Polygon $p) => $p->getArray(), $layers);
    }

    /** Caja que contiene todas las coberturas con un margen. */
    public static function box(array $coverages, float $margin = 0.2): array
    {
        $pts = array_merge(...array_merge(...$coverages));
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        return [[[min($xs) - $margin, min($ys) - $margin], [max($xs) + $margin, min($ys) - $margin],
                 [max($xs) + $margin, max($ys) + $margin], [min($xs) - $margin, max($ys) + $margin]]];
    }

    /**
     * Genera un escenario, calcula las capas por los dos métodos y devuelve la lista de
     * problemas encontrados (vacía si todo es correcto) y estadísticas del caso.
     *
     * @return array{problems: string[], stats: array}
     */
    public static function check(array $coverages, int $samples = 5000): array
    {
        $n = count($coverages);
        $box = self::box($coverages);
        $t = microtime(true);
        $comb = self::layersByCombinations($coverages, $box);
        $tComb = microtime(true) - $t;
        $t = microtime(true);
        $incr = self::layersIncremental($coverages, $box);
        $tIncr = microtime(true) - $t;

        $problems = [];
        // 1) mismas capas por los dos métodos
        for ($k = 0; $k <= $n; $k++) {
            if (!RegionComparator::equal($comb[$k], $incr[$k], null, $why)) {
                $problems[] = "capa $k distinta por combinaciones e incremental: $why";
            }
        }
        // 2) partición de la caja
        $boxArea = self::evenOddArea($box);
        foreach (['combinaciones' => $comb, 'incremental' => $incr] as $m => $layers) {
            $sum = array_sum(array_map([self::class, 'evenOddArea'], $layers));
            $err = abs($sum - $boxArea) / $boxArea;
            if ($err > 1e-9) {
                $problems[] = sprintf('%s: las capas no reparten la caja (Σ áreas = %.12f, caja = %.12f, error relativo %.1e)',
                    $m, $sum, $boxArea, $err);
            }
        }
        // 3) oráculo de puntos
        $origin = array_merge(...$coverages);
        mt_srand(424242);
        $hist = array_fill(0, $n + 1, 0);
        $bad = ['combinaciones' => 0, 'incremental' => 0];
        $first = null;
        for ($s = 0; $s < $samples; $s++) {
            $x = $box[0][0][0] + mt_rand() / mt_getrandmax() * ($box[0][1][0] - $box[0][0][0]);
            $y = $box[0][0][1] + mt_rand() / mt_getrandmax() * ($box[0][2][1] - $box[0][0][1]);
            $count = 0;
            foreach ($coverages as $c) {
                $count += GeometryOracle::inside($c, $x, $y) ? 1 : 0;
            }
            $hist[$count]++;
            foreach (['combinaciones' => $comb, 'incremental' => $incr] as $m => $layers) {
                $in = [];
                foreach ($layers as $k => $rings) {
                    if (GeometryOracle::inside($rings, $x, $y)) {
                        $in[] = $k;
                    }
                }
                if ($in !== [$count] && !GeometryOracle::nearEdge([$origin], $x, $y, 1e-7)) {
                    $bad[$m]++;
                    $first ??= sprintf('[%.7f, %.7f] cubierto por %d radares, pero está en la(s) capa(s) %s (%s)',
                        $x, $y, $count, json_encode($in), $m);
                }
            }
        }
        if ($bad['combinaciones'] || $bad['incremental']) {
            $problems[] = sprintf('puntos mal clasificados: %d por combinaciones, %d incremental (de %d); p.ej. %s',
                $bad['combinaciones'], $bad['incremental'], $samples, $first);
        }
        return ['problems' => $problems, 'stats' => [
            'anillos' => array_sum(array_map('count', $incr)),
            'vértices' => array_sum(array_map(fn($l) => array_sum(array_map('count', $l)), $incr)),
            'puntos por nº de radares' => $hist,
            'segundos' => [round($tComb, 1), round($tIncr, 1)],
        ]];
    }

    /**
     * Área de la región par-impar formada por anillos simples (que pueden tocarse):
     * Σ |área_i| · (−1)^profundidad_i, con la profundidad medida en un punto ESTRICTAMENTE
     * interior de cada anillo (junto al punto medio de su arista más larga).
     */
    public static function evenOddArea(array $rings): float
    {
        $rings = RegionComparator::rings($rings);
        $total = 0.0;
        foreach ($rings as $i => $r) {
            $n = count($r);
            $a = 0.0;
            $best = 0;
            $bestLen = -1.0;
            for ($p = 0, $q = $n - 1; $p < $n; $q = $p++) {
                $a += $r[$q][0] * $r[$p][1] - $r[$p][0] * $r[$q][1];
                $l = hypot($r[$p][0] - $r[$q][0], $r[$p][1] - $r[$q][1]);
                if ($l > $bestLen) {
                    $bestLen = $l;
                    $best = $p;
                }
            }
            [$x0, $y0] = $r[($best + $n - 1) % $n];
            [$x1, $y1] = $r[$best];
            $sgn = $a > 0 ? 1 : -1;                                   // antihorario: interior a la izquierda
            $px = ($x0 + $x1) / 2 - $sgn * ($y1 - $y0) * 1e-7;
            $py = ($y0 + $y1) / 2 + $sgn * ($x1 - $x0) * 1e-7;
            $depth = 0;
            foreach ($rings as $j => $o) {
                if ($j !== $i && GeometryOracle::inside([$o], $px, $py)) {
                    $depth++;
                }
            }
            $total += ($depth % 2 ? -1 : 1) * abs($a) / 2;
        }
        return $total;
    }

    // ── auxiliares ───────────────────────────────────────────────────────────

    private static function coverage(float $lon, float $lat, float $rangeKm, int $vertices, ?int $round): array
    {
        $harmonics = [];
        for ($k = 1; $k <= 12; $k++) {
            $harmonics[] = [self::rnd(0, 0.10) / sqrt($k), self::rnd(0, 2 * M_PI)];
        }
        $shadows = [];
        $ns = mt_rand(2, 4);
        for ($s = 0; $s < $ns; $s++) {
            $shadows[] = [self::rnd(0, 2 * M_PI), self::rnd(deg2rad(4), deg2rad(25)), self::rnd(0.25, 0.6)];
        }
        $ring = [];
        for ($i = 0; $i < $vertices; $i++) {
            $a = 2 * M_PI * $i / $vertices;
            $r = 1.0;
            foreach ($harmonics as $k => [$amp, $ph]) {
                $r += $amp * sin(($k + 1) * $a + $ph);                 // ondulación
            }
            $r *= 1 + self::rnd(-0.03, 0.03);                          // rugosidad
            foreach ($shadows as [$c, $w, $depth]) {                   // sombras del terreno
                $d = abs(atan2(sin($a - $c), cos($a - $c)));
                if ($d < $w) {
                    $r *= $depth + (1 - $depth) * ($d / $w) ** 4;
                }
            }
            [$x, $y] = self::offset($lon, $lat, $rangeKm * $r * sin($a), $rangeKm * $r * cos($a));
            $ring[] = $round === null ? [$x, $y] : [round($x, $round), round($y, $round)];
        }
        return $ring;
    }

    private static function offset(float $lon, float $lat, float $dxKm, float $dyKm): array
    {
        return [$lon + $dxKm / (self::KM_PER_DEG * cos(deg2rad($lat))), $lat + $dyKm / self::KM_PER_DEG];
    }

    private static function rnd(float $a, float $b): float
    {
        return $a + mt_rand() / mt_getrandmax() * ($b - $a);
    }

    private static function poly(array $rings): Polygon
    {
        return Polygon::create()->fillFromArray($rings);
    }

    private static function subsets(int $n, int $k): array
    {
        if ($k === 0) {
            return [[]];
        }
        if ($n < $k) {
            return [];
        }
        $out = [];
        foreach (self::subsets($n - 1, $k - 1) as $s) {
            $out[] = array_merge($s, [$n - 1]);
        }
        foreach (self::subsets($n - 1, $k) as $s) {
            $out[] = $s;
        }
        return $out;
    }
}
