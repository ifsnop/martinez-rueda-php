<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Algorithm;

final class PointTest extends TestCase
{
    private float $T;

    protected function setUp(): void
    {
        $this->T = Algorithm::TOLERANCE;
        $this->assertGreaterThan(0.0, $this->T, 'Algorithm::TOLERANCE debe ser > 0');
    }

    /** ------------------ Point::compare ------------------ */

    public function testCompareByXAscending(): void
    {
        $a = new Point(0.0, 0.0);
        $b = new Point(1.0, 0.0);
        $this->assertSame(-1, Point::compare($a, $b));
        $this->assertSame( 1, Point::compare($b, $a));
    }

    public function testCompareByYWhenXWithinTolerance(): void
    {
        $dx = $this->T / 2.0; // |dx| < T ⇒ compara por y
        $a = new Point(1.0, 0.0);
        $b = new Point(1.0 + $dx, 2.0);
        $this->assertSame(-1, Point::compare($a, $b)); // 0 < 2
        $this->assertSame( 1, Point::compare($b, $a));
    }

    public function testCompareEqualityWithinTolerance(): void
    {
        $dx = $this->T / 2.0; // ambos < T ⇒ igual
        $dy = $this->T / 2.0;
        $a = new Point(5.0, -3.0);
        $b = new Point(5.0 + $dx, -3.0 + $dy);
        $this->assertSame(0, Point::compare($a, $b));
    }

    /** ------------------ Point::collinear ------------------ */

    public function testCollinearOn45DegreeLine(): void
    {
        $p1 = new Point(0.0, 0.0);
        $p2 = new Point(1.0, 1.0);
        $p3 = new Point(2.0, 2.0);
        $this->assertTrue(Point::collinear($p1, $p2, $p3));
    }

    public function testCollinearNearlyOnLineWithinTolerance(): void
    {
        $p1 = new Point(0.0, 0.0);
        $p2 = new Point(2.0, 0.0);
        $p3 = new Point(1.0, $this->T / 4.0); // desviación < T ⇒ colineal (det = T/2)
        $this->assertTrue(Point::collinear($p1, $p2, $p3));
    }

    public function testCollinearNotOnLineBeyondTolerance(): void
    {
        $p1 = new Point(0.0, 0.0);
        $p2 = new Point(2.0, 0.0);
        $p3 = new Point(1.0, 10.0 * $this->T); // > T ⇒ no colineal
        $this->assertFalse(Point::collinear($p1, $p2, $p3));
    }

    /** ------------- Point::pointAboveOrOnLine ------------- */

    public function testPointAboveOrOnLineHorizontal(): void
    {
        $left = new Point(0.0, 0.0);
        $right = new Point(2.0, 0.0);

        $above = new Point(1.0, 1.0);
        $on    = new Point(1.0, 0.0);
        $slightlyBelowWithinTol = new Point(1.0, -$this->T / 2.0);
        $below = new Point(1.0, -10.0 * $this->T);

        $this->assertTrue(Point::pointAboveOrOnLine($above, $left, $right));
        $this->assertTrue(Point::pointAboveOrOnLine($on, $left, $right)); // sobre la línea
        // >= -T ⇒ un poco por debajo dentro de T aún cuenta como "on"
        $this->assertTrue(Point::pointAboveOrOnLine($slightlyBelowWithinTol, $left, $right));
        $this->assertFalse(Point::pointAboveOrOnLine($below, $left, $right));
    }

    /** ------------------ Point::between ------------------ */

    public function testBetweenStrictInteriorExcludesEndpointsWithTolerance(): void
    {
        $left  = new Point(0.0, 0.0);
        $right = new Point(10.0, 0.0);

        $start = new Point(0.0, 0.0);
        $end   = new Point(10.0, 0.0);

        $nearStartInside   = new Point(2.0 * $this->T, 0.0);          // dot >= T ⇒ interior por el inicio
        $deepInside        = new Point(5.0, 0.0);                     // interior claro
        $nearEndInside     = new Point(10.0 - 2.0 * $this->T, 0.0);   // dot - sqlen <= -T ⇒ interior
        $tooCloseToEnd     = new Point(10.0 - $this->T / 20.0, 0.0);  // x > 10 - T/10 ⇒ excluido

        // Extremos excluidos por diseño
        $this->assertFalse(Point::between($start, $left, $right));
        $this->assertFalse(Point::between($end,   $left, $right));

        $this->assertTrue(Point::between($nearStartInside, $left, $right));
        $this->assertTrue(Point::between($deepInside,      $left, $right));
        $this->assertTrue(Point::between($nearEndInside,   $left, $right));
        $this->assertFalse(Point::between($tooCloseToEnd,  $left, $right));
    }

    /** ------------- Point::linesIntersect (líneas) ------------- */

    public function testLinesIntersectProperCrossingInterior(): void
    {
        // A: horizontal, B: vertical, cruce interior claro
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(10.0, 0.0);

        $b0 = new Point(5.0, -5.0);
        $b1 = new Point(5.0,  5.0);

        $ip = Point::linesIntersect($a0, $a1, $b0, $b1);
        $this->assertNotNull($ip, 'Debe intersectar como líneas');

        $this->assertSame(0, $ip->alongA); // a = 0.5 ⇒ interior
        $this->assertSame(0, $ip->alongB); // b = 0.5 ⇒ interior
        $this->assertEquals([5.0, 0.0], $ip->point->getArray());
    }

    public function testLinesIntersectAtAStartAndAEnd(): void
    {
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(10.0, 0.0);

        // Cruza exactamente en el inicio de A (a=0 ⇒ alongA=-1)
        $b0 = new Point(0.0, -1.0);
        $b1 = new Point(0.0,  1.0);
        $ipStart = Point::linesIntersect($a0, $a1, $b0, $b1);
        $this->assertNotNull($ipStart);
        $this->assertSame(-1, $ipStart->alongA);
        $this->assertSame( 0, $ipStart->alongB);
        $this->assertEquals([0.0, 0.0], $ipStart->point->getArray());

        // Cruza exactamente en el final de A (a=1 ⇒ alongA=1)
        $c0 = new Point(10.0, -1.0);
        $c1 = new Point(10.0,  1.0);
        $ipEnd = Point::linesIntersect($a0, $a1, $c0, $c1);
        $this->assertNotNull($ipEnd);
        $this->assertSame(1,  $ipEnd->alongA);
        $this->assertSame(0,  $ipEnd->alongB);
        $this->assertEquals([10.0, 0.0], $ipEnd->point->getArray());
    }

    public function testLinesIntersectOutsideSegmentAButAsLines(): void
    {
        // Intersección de líneas fuera de segmento A (a > 1 + T ⇒ alongA=2)
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(1.0, 0.0);

        $xBeyond = 1.0 + 100.0 * $this->T; // margen amplio para evitar borde
        $b0 = new Point($xBeyond, -1.0);
        $b1 = new Point($xBeyond,  1.0);

        $ip = Point::linesIntersect($a0, $a1, $b0, $b1);
        $this->assertNotNull($ip);
        $this->assertSame(2, $ip->alongA); // fuera por el final de A
        $this->assertSame(0, $ip->alongB); // interior de B
        $this->assertEquals([$xBeyond, 0.0], $ip->point->getArray());
    }

    public function testLinesIntersectOutsideBeforeA(): void
    {
        // Intersección a la izquierda de A (a < 0 - T ⇒ alongA=-2)
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(10.0, 0.0);

        $xBefore = -100.0 * $this->T;
        $b0 = new Point($xBefore, -1.0);
        $b1 = new Point($xBefore,  1.0);

        $ip = Point::linesIntersect($a0, $a1, $b0, $b1);
        $this->assertNotNull($ip);
        $this->assertSame(-2, $ip->alongA);
        $this->assertSame( 0, $ip->alongB);
        $this->assertEquals([$xBefore, 0.0], $ip->point->getArray());
    }

    public function testLinesParallelReturnNull(): void
    {
        // Segmentos paralelos (colineales) ⇒ null
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(10.0, 0.0);

        $b0 = new Point(0.0, 1.0);
        $b1 = new Point(10.0, 1.0);

        $this->assertNull(Point::linesIntersect($a0, $a1, $b0, $b1));
    }

    public function testLinesAlmostParallelWithinToleranceReturnNull(): void
    {
        // Misma pendiente con un offset en y dentro de T ⇒ axb ~ 0 ⇒ null
        $a0 = new Point(0.0, 0.0);
        $a1 = new Point(10.0, 0.0);

        $b0 = new Point(0.0, $this->T / 2.0);
        $b1 = new Point(10.0, $this->T / 2.0);

        $this->assertNull(Point::linesIntersect($a0, $a1, $b0, $b1));
    }

    /** ----------- __eq, __toString, __repr, getArray ----------- */

    public function testEqWithinTolerance(): void
    {
        $a = new Point(1.0, 2.0);
        $b = new Point(1.0 + $this->T / 2.0, 2.0 - $this->T / 2.0);
        $c = new Point(1.0 + 10.0 * $this->T, 2.0);

        $this->assertTrue($a->__eq($b));
        $this->assertFalse($a->__eq($c));
    }

    public function testToStringAndReprAndGetArray(): void
    {
        $p = new Point(1.5, -2.25);
        $this->assertSame('[1.5,-2.25]', (string)$p);
        $this->assertSame('1.5,-2.25', $p->__repr());
        $this->assertSame([1.5, -2.25], $p->getArray());
    }
}
