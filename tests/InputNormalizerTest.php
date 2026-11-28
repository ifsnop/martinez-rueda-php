<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\InputNormalizer;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\GeometryOracle;

include_once('autoloader.php');

/**
 * Normalización de entradas (ISSUES.md #2): detección de la precisión de los datos,
 * unificación de vértices, partición de aristas en vértices cercanos, y su efecto en
 * las operaciones públicas.
 */
final class InputNormalizerTest extends TestCase
{
    private static function poly(array $rings): Polygon
    {
        return Polygon::create()->fillFromArray($rings);
    }

    // ── detectPrecision ──────────────────────────────────────────────────────

    public function testDetectsSevenDecimalGeoJson(): void
    {
        $p = self::poly([[[-4.1161297, 40.4826806], [-3.8667245, 40.6494051], [-3.9778742, 40.8156752]]]);
        $this->assertSame(1e-7, InputNormalizer::detectPrecision([$p]));
    }

    public function testUsesTheMaximumNumberOfDecimalsOfAllOperands(): void
    {
        $a = self::poly([[[1.5, 2.25], [3.125, 4.0], [5.0, 6.75]]]);                 // 3 decimales
        $b = self::poly([[[1.123456, 2.0], [3.0, 4.0], [5.0, 6.0]]]);                 // 6 decimales
        $this->assertSame(1e-6, InputNormalizer::detectPrecision([$a, $b]));
    }

    public function testDoesNotNormalizeIntegersOrFewDecimals(): void
    {
        $this->assertSame(0.0, InputNormalizer::detectPrecision([self::poly([[[0, 0], [10, 0], [10, 10]]])]));
        $this->assertSame(0.0, InputNormalizer::detectPrecision([self::poly([[[0.1234, 0], [1, 0], [1, 1]]])]));
    }

    public function testDoesNotNormalizeFullPrecisionData(): void
    {
        $p = self::poly([[[0.1 + 0.2, 0], [1, 0], [1, 1]]]);                          // 0.30000000000000004
        $this->assertSame(0.0, InputNormalizer::detectPrecision([$p]));
        $q = self::poly([[[40.49956209027567, -3.523761811338624], [40.5, -3.5], [40.6, -3.6]]]);
        $this->assertSame(0.0, InputNormalizer::detectPrecision([$q]));
    }

    public function testDetectionBounds(): void
    {
        $this->assertSame(1e-5, InputNormalizer::detectPrecision([self::poly([[[0.12345, 0], [1, 0], [1, 1]]])]));
        $this->assertSame(1e-9, InputNormalizer::detectPrecision([self::poly([[[0.123456789, 0], [1, 0], [1, 1]]])]));
        $this->assertSame(0.0, InputNormalizer::detectPrecision([self::poly([[[0.1234567891, 0], [1, 0], [1, 1]]])]));
        $this->assertSame(0.0, InputNormalizer::detectPrecision([Polygon::create()->fillFromArray([])]));
    }

    // ── normalize ────────────────────────────────────────────────────────────

    public function testCracksEdgeAtNearbyVertexOfTheOtherOperand(): void
    {
        // El vértice (1, 1.00000004) de B está a 4e-8 de la arista superior de A.
        $a = self::poly([[[0, 0], [2, 0], [2, 1], [0, 1]]]);
        $b = self::poly([[[1, 1.00000004], [1.5, 2], [0.5, 2]]]);
        [$na, $nb] = InputNormalizer::normalize([$a, $b], 1e-7);
        $this->assertSame([[0.0, 0.0], [2.0, 0.0], [2.0, 1.0], [1.0, 1.00000004], [0.0, 1.0]], $na->getArray()[0]);
        $this->assertSame($b->getArray(), $nb->getArray(), 'B no cambia');
    }

    public function testUnifiesVerticesCloserThanTheTolerance(): void
    {
        $a = self::poly([[[0, 0], [1, 0], [1, 1]]]);
        $b = self::poly([[[1.00000003, 1.00000002], [2, 1], [2, 2]]]);
        [, $nb] = InputNormalizer::normalize([$a, $b], 1e-7);
        $this->assertSame([1.0, 1.0], $nb->getArray()[0][0]);
    }

    public function testLeavesDistantGeometryUntouched(): void
    {
        $a = self::poly([[[0, 0], [2, 0], [2, 1], [0, 1]]]);
        $b = self::poly([[[1, 1.000001], [1.5, 2], [0.5, 2]]]);                        // a 1e-6 > tol
        [$na, $nb] = InputNormalizer::normalize([$a, $b], 1e-7);
        $this->assertSame($a->getArray(), $na->getArray());
        $this->assertSame($b->getArray(), $nb->getArray());
    }

    public function testDoesNotModifyTheOriginalPolygonsAndKeepsInversion(): void
    {
        $a = Polygon::create()->fillFromArray([[[0, 0], [2, 0], [2, 1], [0, 1]]], true);
        $b = self::poly([[[1, 1.00000004], [1.5, 2], [0.5, 2]]]);
        $before = $a->getArray();
        [$na] = InputNormalizer::normalize([$a, $b], 1e-7);
        $this->assertSame($before, $a->getArray());
        $this->assertTrue($na->isInverted);
    }

    public function testDropsRingsThatCollapse(): void
    {
        $a = self::poly([[[0, 0], [1e-8, 0], [0, 1e-8]], [[5, 5], [6, 5], [6, 6]]]);
        [$na] = InputNormalizer::normalize([$a], 1e-7);
        $this->assertCount(1, $na->getArray());
    }

    // ── efecto en las operaciones públicas ───────────────────────────────────

    private const REPRO_A = [[[-4.1161297, 40.4826806], [-3.8667245, 40.6494051], [-3.9778742, 40.8156752], [-4.2272794, 40.6489507]],
                             [[-3.6724398, 40.5387099], [-3.4230347, 40.7054344], [-3.5341843, 40.8717045], [-3.7835895, 40.70498]]];
    private const REPRO_B = [[[-3.8111497, 40.5662701], [-3.7280146, 40.6218449], [-3.8947392, 40.8712501], [-3.9778742, 40.8156752]],
                             [[-4.088115, 40.2608357], [-4.00498, 40.3164105], [-4.0605548, 40.3995456], [-4.1436899, 40.3439707]],
                             [[-3.783135, 40.3444252], [-3.4505949, 40.5667245], [-3.6173194, 40.8161297], [-3.9498596, 40.5938303]]];

    /** Caso de ISSUES.md #2: polígonos de ~10 km con 7 decimales; antes la unión fallaba en ~1/3 del área. */
    public function testRoundedGeoJsonIsClippedCorrectlyByDefault(): void
    {
        $this->assertSame([], GeometryOracle::check(self::REPRO_A, self::REPRO_B, 1000, 2e-7));
    }

    public function testExplicitPrecisionOverridesDetection(): void
    {
        // Coordenadas enteras + un vértice a 4e-8 de la arista: la detección no normaliza,
        // la precisión explícita sí (la arista superior de A pasa por el vértice de B).
        $a = self::poly([[[0, 0], [2, 0], [2, 1], [0, 1]]]);
        $b = self::poly([[[1, 1.00000004], [1.5, 2], [0.5, 2]]]);
        $auto = Algorithm::union($a, $b)->getArray();
        $explicit = Algorithm::union($a, $b, 1e-7)->getArray();
        $hasVertex = fn(array $rings) => in_array([1.0, 1.00000004], array_merge(...$rings), true);
        $this->assertTrue($hasVertex($explicit), 'con precisión explícita la unión pasa por el vértice de B');
        // Tras normalizar, B toca A en un solo punto: dos anillos que comparten ese vértice.
        $this->assertCount(2, $explicit);
        $this->assertNotEquals($auto, $explicit);
    }

    public function testZeroPrecisionDisablesNormalization(): void
    {
        $a = self::poly([[[0, 0], [2, 0], [2, 1], [0, 1]]]);
        $b = self::poly([[[1, 1.00000004], [1.5, 2], [0.5, 2]]]);
        $this->assertSame(Algorithm::union($a, $b)->getArray(), Algorithm::union($a, $b, 0.0)->getArray());
        $na = self::poly(self::REPRO_A);
        $nb = self::poly(self::REPRO_B);
        $this->assertNotSame(
            Algorithm::union($na, $nb)->getArray(),
            Algorithm::union($na, $nb, 0.0)->getArray(),
            'con precision 0 no se normaliza (y el caso de ISSUES.md #2 vuelve a dar otro resultado)'
        );
    }

    public function testManyOperationsNormalizeAllOperandsTogether(): void
    {
        $polys = [self::poly([self::REPRO_A[0]]), self::poly([self::REPRO_A[1]]),
                  self::poly([self::REPRO_B[0]]), self::poly([self::REPRO_B[1]]), self::poly([self::REPRO_B[2]])];
        $r = Algorithm::unionMany($polys)->getArray();
        $all = array_merge(self::REPRO_A, self::REPRO_B);
        mt_srand(5);
        $wrong = 0;
        for ($k = 0; $k < 1000; $k++) {
            $x = -4.3 + mt_rand() / mt_getrandmax() * 0.9;
            $y = 40.2 + mt_rand() / mt_getrandmax() * 0.7;
            if (GeometryOracle::nearEdge([$all, $r], $x, $y, 2e-7)) {
                continue;
            }
            $inAny = false;
            foreach ($all as $ring) {
                $inAny = $inAny || GeometryOracle::inside([$ring], $x, $y);
            }
            if (GeometryOracle::inside($r, $x, $y) !== $inAny) {
                $wrong++;
            }
        }
        $this->assertSame(0, $wrong);
    }
}
