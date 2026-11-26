<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Tests\Support\RegionComparator;

/**
 * El comparador independiente que usa el arnés E2E (ISSUES.md #4) también tiene que ser fiable.
 */
final class RegionComparatorTest extends TestCase
{
    private const SQUARE = [[0, 0], [4, 0], [4, 4], [0, 4]];
    private const HOLE = [[1, 1], [1, 3], [3, 3], [3, 1]];

    public function testSameRegionWrittenDifferentlyIsEqual(): void
    {
        $a = [[self::SQUARE, self::HOLE]];                                         // MultiPolygon
        $b = [[[1, 3], [3, 3], [3, 1], [1, 1], [1, 3]],                            // anillos sueltos, cerrados,
              [[4, 4], [0, 4], [0, 0], [2, 0], [4, 0], [4, 4]]];                    // otro orden, otro inicio, colineal de más
        $this->assertTrue(RegionComparator::equal($a, $b, null, $why), (string)$why);
    }

    public function testEmptyRegionsAreEqual(): void
    {
        $this->assertTrue(RegionComparator::equal([], []));
    }

    public function testEmptyIsNotEqualToATinyPolygon(): void
    {
        $tri = [[-122.3793225342952, 37.708405012714316], [-122.3793225, 37.708405], [-122.37932240498668, 37.70840501108731]];
        $this->assertFalse(RegionComparator::equal([], [[$tri]], null, $why));
        $this->assertStringContainsString('INF', (string)$why);
    }

    public function testDetectsATinyExtraPieceFarAway(): void
    {
        $tiny = [[100, 100], [100.000001, 100], [100, 100.000001]];
        $this->assertFalse(RegionComparator::equal([self::SQUARE], [self::SQUARE, $tiny]));
    }

    public function testDetectsAMissingHole(): void
    {
        $this->assertFalse(RegionComparator::equal([self::SQUARE, self::HOLE], [self::SQUARE]));
    }

    public function testDetectsAVertexMovedMoreThanDelta(): void
    {
        $moved = [[0, 0], [4, 0], [4, 4.00001], [0, 4]];
        $this->assertFalse(RegionComparator::equal([self::SQUARE], [$moved], 1e-9));
        $this->assertTrue(RegionComparator::equal([self::SQUARE], [$moved], 1e-4));
    }

    public function testDetectsSwappedInsideAndOutsideWithTheSameBoundary(): void
    {
        // Mismas fronteras, pero en b el anillo interior es otra pieza y no un agujero:
        // con par-impar no cambia nada (ambos son iguales)...
        $this->assertTrue(RegionComparator::equal([self::SQUARE, self::HOLE], [self::HOLE, self::SQUARE]));
        // ...pero un anillo de más que coincide con la frontera sí invierte el interior.
        $this->assertFalse(RegionComparator::equal([self::SQUARE, self::HOLE], [self::SQUARE, self::HOLE, self::HOLE]));
    }

    public function testDefaultDeltaScalesWithCoordinates(): void
    {
        $this->assertSame(1e-10, RegionComparator::defaultDelta([[[0, 0], [0.5, 0], [0, 0.5]]], []));   // mínimo 1e-10
        $this->assertSame(4e-10, RegionComparator::defaultDelta([self::SQUARE], []));
        $this->assertEqualsWithDelta(1.8e-8, RegionComparator::defaultDelta([[[180, 0], [179, 1], [179, 0]]], []), 1e-20);
    }
}
