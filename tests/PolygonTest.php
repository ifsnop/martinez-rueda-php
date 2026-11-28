<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\GeometryOracle;
use Ifsnop\MartinezRueda\Tests\Support\RegionComparator;

include_once('autoloader.php');

/**
 * ISSUES.md #5 (Polygon::create() sin rellenar como operando) y #6 (*Many con un solo
 * polígono devolvía la entrada sin limpiar).
 */
final class PolygonTest extends TestCase
{
    private const SQUARE = [[0, 0], [1, 0], [1, 1], [0, 1]];

    /** Dos cuadrados solapados: con par-impar el solape (centro en 2,2) se cancela. */
    private const OVERLAPPING = [[[0, 0], [3, 0], [3, 3], [0, 3]], [[1, 1], [4, 1], [4, 4], [1, 4]]];

    // ── #5 ───────────────────────────────────────────────────────────────────

    public function testCreatedPolygonIsNotInvertedByDefault(): void
    {
        $this->assertFalse(Polygon::create()->isInverted);
    }

    public function testUnfilledPolygonWorksWithTheLowLevelApi(): void
    {
        // Antes: Error "Typed property ...::$isInverted must not be accessed before initialization"
        $this->assertSame([], Algorithm::segments(Polygon::create())->segments);
    }

    public function testUnfilledPolygonIsAnEmptyOperand(): void
    {
        $sq = Polygon::create()->fillFromArray([self::SQUARE]);
        foreach ([Algorithm::union(Polygon::create(), $sq), Algorithm::union($sq, Polygon::create())] as $r) {
            $this->assertTrue(RegionComparator::equal($r->getArray(), [self::SQUARE], null, $why), (string)$why);
        }
        $this->assertSame([], Algorithm::intersect(Polygon::create(), $sq)->getArray());
    }

    // ── #6 ───────────────────────────────────────────────────────────────────

    public static function provideManyOperations(): array
    {
        return ['unionMany' => ['unionMany'], 'intersectMany' => ['intersectMany'],
                'xorMany' => ['xorMany'], 'differenceMany' => ['differenceMany']];
    }

    /**
     * @dataProvider provideManyOperations
     */
    public function testManyWithOnePolygonReturnsACleanedCopy(string $fn): void
    {
        $p = Polygon::create()->fillFromArray(self::OVERLAPPING);
        $r = Algorithm::$fn([$p]);

        $this->assertNotSame($p, $r, "$fn([P]) devuelve el mismo objeto sin procesar");
        $expected = Algorithm::union($p, Polygon::create())->getArray();
        $this->assertTrue(RegionComparator::equal($r->getArray(), $expected, null, $why), "$fn([P]) ≠ union(P, vacío): $why");
        // limpio: el solape no está, y ningún anillo del resultado lo atraviesa (antes eran los dos cuadrados originales)
        $this->assertFalse(GeometryOracle::inside($r->getArray(), 2.0, 2.0));
        $this->assertNotEquals($p->getArray(), $r->getArray(), "$fn([P]) devuelve los anillos originales sin limpiar");
    }

    public function testManyWithOnePolygonHonoursPrecision(): void
    {
        // vértice a 4e-8 de la arista superior: solo se une con precisión explícita
        $p = Polygon::create()->fillFromArray([[[0, 0], [2, 0], [2, 1], [0, 1]], [[1, 1.00000004], [1.5, 2], [0.5, 2]]]);
        $hasVertex = fn(array $rings) => in_array([1.0, 1.00000004], array_merge(...$rings), true);
        $this->assertSame(
            Algorithm::union($p, Polygon::create(), 1e-7)->getArray(),
            Algorithm::unionMany([$p], 1e-7)->getArray()
        );
        $this->assertTrue($hasVertex(Algorithm::unionMany([$p], 1e-7)->getArray()));
    }
}
