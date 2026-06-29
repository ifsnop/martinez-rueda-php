<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('autoloader.php');

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Intersecter;
use Ifsnop\MartinezRueda\RegionIntersecter;
use Ifsnop\MartinezRueda\Fill;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\PolySegments;
use Ifsnop\MartinezRueda\PolyBoolException;
use Ifsnop\MartinezRueda\Segment;
use Ifsnop\MartinezRueda\Node;

// ═════════════════════════════════════════════════════════════════════════════
// 5. INTERSECTER (vía RegionIntersecter e Intersecter directo)
// ─────────────────────────────────────────────────────────────────────────────
// Intersecter es el corazón del algoritmo Bentley-Ottmann. Los tests validan
// el contrato público observable: que el número y geometría de los segmentos
// de salida sean correctos para entradas canónicas.
//
// Cambios respecto a la versión anterior del algoritmo:
//   · SegmentIntersecter ha sido ELIMINADO. Usar new Intersecter(false)
//     o Algorithm::combine() / Algorithm::union() etc. directamente.
//   · RegionIntersecter::calculate2() ha sido ELIMINADO.
//     Usar calculate(bool $isInverted, false) directamente.
//   · SegmentIntersecter::calculate2() ha sido ELIMINADO.
//     Usar Algorithm::combine() que devuelve array de Segment[].
//   · Algorithm::combine() devuelve array (no CombinedPolySegments).
//   · Algorithm::selectUnion/Intersect/Difference/Xor reciben
//     (array $combined, bool $inv1, bool $inv2) en lugar de CombinedPolySegments.
//   · CombinedPolySegments, Selector, Matcher, Transition, IntersectionPoint
//     han sido ELIMINADOS.
// ═════════════════════════════════════════════════════════════════════════════
class IntersecterTest extends TestCase
{
    private Intersecter $intersecter;

    // Helper: cuadrado como Polygon
    private function square(float $x, float $y, float $size): Polygon
    {
        return Polygon::create()->fillFromArray([[
            new Point($x,         $y),
            new Point($x + $size, $y),
            new Point($x + $size, $y + $size),
            new Point($x,         $y + $size),
        ]]);
    }

    protected function setUp(): void
    {
        // SegmentIntersecter eliminado → usar Intersecter(false) directamente
        $this->intersecter = new Intersecter(selfIntersection: false);
    }

    // ── Constructor ──────────────────────────────────────────────────────────

    public function testConstructorInitialization(): void
    {
        $this->assertInstanceOf(Intersecter::class, new Intersecter(selfIntersection: true));
        $this->assertInstanceOf(Intersecter::class, new Intersecter(selfIntersection: false));
    }

    // ── newSegment ───────────────────────────────────────────────────────────

    public function testNewSegmentCreation(): void
    {
        $start   = new Point(0.0, 0.0);
        $end     = new Point(1.0, 1.0);
        $segment = $this->intersecter->newSegment($start, $end);

        $this->assertInstanceOf(Segment::class, $segment);
        $this->assertSame($start, $segment->start);
        $this->assertSame($end,   $segment->end);
        $this->assertInstanceOf(Fill::class, $segment->myFill);
        $this->assertNull($segment->myFill->below);
        $this->assertNull($segment->myFill->above);
        $this->assertNull($segment->otherFill);
    }

    public function testNewSegmentHasFill(): void
    {
        $ri  = new RegionIntersecter();
        $seg = $ri->newSegment(new Point(0, 0), new Point(1, 1));
        $this->assertInstanceOf(Fill::class, $seg->myFill);
    }

    // ── segmentCopy ──────────────────────────────────────────────────────────

    public function testSegmentCopy(): void
    {
        $orig = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 1.0));
        $orig->myFill->below = true;
        $orig->myFill->above = false;

        $newStart = new Point(0.5, 0.5);
        $newEnd   = new Point(1.5, 1.5);
        $copy     = $this->intersecter->segmentCopy($newStart, $newEnd, $orig);

        $this->assertInstanceOf(Segment::class, $copy);
        $this->assertSame($newStart, $copy->start);
        $this->assertSame($newEnd,   $copy->end);
        $this->assertEquals($orig->myFill->below, $copy->myFill->below);
        $this->assertEquals($orig->myFill->above, $copy->myFill->above);
    }

    public function testSegmentCopyCopiesFill(): void
    {
        $ri   = new RegionIntersecter();
        $fill = new Fill(true, false);
        $orig = new Segment(new Point(0, 0), new Point(1, 1), $fill);
        $copy = $ri->segmentCopy(new Point(0, 0), new Point(1, 1), $orig);
        $this->assertTrue($copy->myFill->below);
        $this->assertFalse($copy->myFill->above);
    }

    public function testSegmentCopyIsIndependent(): void
    {
        $ri   = new RegionIntersecter();
        $fill = new Fill(true, false);
        $orig = new Segment(new Point(0, 0), new Point(1, 1), $fill);
        $copy = $ri->segmentCopy(new Point(0, 0), new Point(1, 1), $orig);
        $copy->myFill->below = false;
        $this->assertTrue($orig->myFill->below);
    }

    // ── eventAddSegment ──────────────────────────────────────────────────────

    public function testEventAddSegment(): void
    {
        $segment = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 1.0));
        $evStart = $this->intersecter->eventAddSegment($segment, primary: true);

        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertTrue($evStart->isStart);
        $this->assertTrue($evStart->primary);
        $this->assertSame($segment, $evStart->seg);
        $this->assertNotNull($evStart->other);
        $this->assertFalse($evStart->other->isStart);
    }

    public function testEventAddSegmentHorizontal(): void
    {
        $segment = $this->intersecter->newSegment(new Point(0.0, 1.0), new Point(5.0, 1.0));
        $evStart = $this->intersecter->eventAddSegment($segment, primary: false);

        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertFalse($evStart->primary);
        $this->assertEquals(0.0, $evStart->pt->getArray()[0]);
        $this->assertEquals(1.0, $evStart->pt->getArray()[1]);
    }

    public function testEventAddSegmentVertical(): void
    {
        $segment = $this->intersecter->newSegment(new Point(1.0, 0.0), new Point(1.0, 5.0));
        $evStart = $this->intersecter->eventAddSegment($segment, primary: true);

        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertEquals(1.0, $evStart->pt->getArray()[0]);
        $this->assertEquals(0.0, $evStart->pt->getArray()[1]);
    }

    public function testEventAddSegmentThrowsOnZeroLength(): void
    {
        $this->expectException(PolyBoolException::class);
        $ri  = new RegionIntersecter();
        $seg = new Segment(new Point(1, 1), new Point(1, 1), new Fill());
        $ri->eventAddSegment($seg, true);
    }

    public function testEventAddSegmentNormalizesDirection(): void
    {
        $ri  = new RegionIntersecter();
        $seg = new Segment(new Point(5, 5), new Point(1, 1), new Fill());
        $ri->eventAddSegment($seg, true);
        $this->assertSame(1.0, $seg->start->x);
        $this->assertSame(1.0, $seg->start->y);
    }

    // ── RegionIntersecter ────────────────────────────────────────────────────

    public function testRegionIntersecterProducesSegmentsForSquare(): void
    {
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0), new Point(1, 0),
            new Point(1, 1), new Point(0, 1),
        ]);
        // calculate2() eliminado → calculate(isInverted, false)
        $segs = $ri->calculate(false, false);
        $this->assertCount(4, $segs);
    }

    public function testRegionIntersecterAllSegmentsAreSegmentInstances(): void
    {
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0), new Point(2, 0),
            new Point(2, 2), new Point(0, 2),
        ]);
        foreach ($ri->calculate(false, false) as $s) {
            $this->assertInstanceOf(Segment::class, $s);
        }
    }

    public function testRegionIntersecterSkipsDegenerateEdge(): void
    {
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0),
            new Point(0, 0), // duplicado → borde de longitud 0, se omite
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 1),
        ]);
        $segs = $ri->calculate(false, false);
        $this->assertCount(4, $segs);
    }

    // ── Intersecter(false) reemplaza a SegmentIntersecter ───────────────────

    public function testSegmentIntersecterSeparatedSquares(): void
    {
        $poly1 = $this->square(0, 0, 1);
        $poly2 = $this->square(5, 5, 1); // sin solapamiento

        // Algorithm::combine() devuelve array directamente (no CombinedPolySegments)
        $segs = Algorithm::combine(
            Algorithm::segments($poly1),
            Algorithm::segments($poly2)
        );

        // Sin cruce → 8 segmentos (4+4), sin subdivisiones
        $this->assertCount(8, $segs);
    }

    public function testSegmentIntersecterOverlappingSquares(): void
    {
        $poly1 = $this->square(0, 0, 2);
        $poly2 = $this->square(1, 0, 2); // solapan en x=[1,2]

        $segs = Algorithm::combine(
            Algorithm::segments($poly1),
            Algorithm::segments($poly2)
        );

        // Los segmentos solapados se subdividen → más de 8 segmentos
        $this->assertGreaterThan(8, count($segs));
    }

    // ── calculate ────────────────────────────────────────────────────────────

    public function testCalculateWithEmptyList(): void
    {
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCalculateWithSingleSegment(): void
    {
        $segment = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 1.0));
        $this->intersecter->eventAddSegment($segment, primary: true);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Segment::class, $result[0]);
    }

    public function testCalculateWithNonIntersectingSegments(): void
    {
        $s1 = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 0.0));
        $s2 = $this->intersecter->newSegment(new Point(0.0, 2.0), new Point(1.0, 2.0));
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: false);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testCalculateWithIntersectingSegments(): void
    {
        $s1 = $this->intersecter->newSegment(new Point(0.0, 1.0), new Point(2.0, 1.0));
        $s2 = $this->intersecter->newSegment(new Point(1.0, 0.0), new Point(1.0, 2.0));
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: false);

        $result = $this->intersecter->calculate(false, false);

        // Deben dividirse en el punto de intersección (1,1)
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function testCalculateWithCollinearSegments(): void
    {
        $s1 = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(2.0, 0.0));
        $s2 = $this->intersecter->newSegment(new Point(1.0, 0.0), new Point(3.0, 0.0));
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: false);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testSelfIntersectionEnabled(): void
    {
        $intersecter = new Intersecter(selfIntersection: true);
        $s1 = $intersecter->newSegment(new Point(0.0, 0.0), new Point(2.0, 2.0));
        $s2 = $intersecter->newSegment(new Point(0.0, 2.0), new Point(2.0, 0.0));
        $intersecter->eventAddSegment($s1, primary: true);
        $intersecter->eventAddSegment($s2, primary: true);

        $result = $intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testSegmentsSharingEndpoint(): void
    {
        $shared  = new Point(1.0, 1.0);
        $s1      = $this->intersecter->newSegment(new Point(0.0, 0.0), $shared);
        $s2      = $this->intersecter->newSegment($shared, new Point(2.0, 2.0));
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: false);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testMultipleParallelSegments(): void
    {
        $segs = [
            $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 0.0)),
            $this->intersecter->newSegment(new Point(0.0, 1.0), new Point(1.0, 1.0)),
            $this->intersecter->newSegment(new Point(0.0, 2.0), new Point(1.0, 2.0)),
            $this->intersecter->newSegment(new Point(0.0, 3.0), new Point(1.0, 3.0)),
        ];
        $primaries = [true, true, false, false];
        foreach ($segs as $i => $seg) {
            $this->intersecter->eventAddSegment($seg, primary: $primaries[$i]);
        }

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(4, $result);
    }

    public function testInvertedPolygons(): void
    {
        $segment = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 1.0));
        $this->intersecter->eventAddSegment($segment, primary: true);

        $result = $this->intersecter->calculate(primaryPolyInverted: true, secondaryPolyInverted: false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertNotNull($result[0]->myFill);
    }

    public function testSmallButValidSegments(): void
    {
        $segment = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1e-8, 1e-8));
        $this->intersecter->eventAddSegment($segment, primary: true);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testZeroLengthSegmentThrowsException(): void
    {
        $this->expectException(PolyBoolException::class);
        $this->expectExceptionMessage('Zero-length segment detected');

        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(Algorithm::TOLERANCE / 100.0, Algorithm::TOLERANCE / 100.0)
        );
        $this->intersecter->eventAddSegment($segment, primary: true);
        $this->intersecter->calculate(false, false);
    }

    public function testNegativeCoordinates(): void
    {
        $s1 = $this->intersecter->newSegment(new Point(-2.0, -2.0), new Point(-1.0, -1.0));
        $s2 = $this->intersecter->newSegment(new Point(-2.0, -1.0), new Point(-1.0, -2.0));
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: false);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testComplexDecimalCoordinates(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.123456789, 0.987654321),
            new Point(1.111111111, 2.222222222)
        );
        $this->intersecter->eventAddSegment($segment, primary: true);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testEventOrderingMaintained(): void
    {
        // Insertar segmentos en orden no espacial
        $s3 = $this->intersecter->newSegment(new Point(4.0, 0.0), new Point(5.0, 0.0));
        $s1 = $this->intersecter->newSegment(new Point(0.0, 0.0), new Point(1.0, 0.0));
        $s2 = $this->intersecter->newSegment(new Point(2.0, 0.0), new Point(3.0, 0.0));
        $this->intersecter->eventAddSegment($s3, primary: true);
        $this->intersecter->eventAddSegment($s1, primary: true);
        $this->intersecter->eventAddSegment($s2, primary: true);

        $result = $this->intersecter->calculate(false, false);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    // ── Algorithm::segments y API de alto nivel ──────────────────────────────

    public function testAlgorithmSegmentsReturnsPolySegments(): void
    {
        $poly = $this->square(0, 0, 1);
        $ps   = Algorithm::segments($poly);
        $this->assertInstanceOf(PolySegments::class, $ps);
        $this->assertNotEmpty($ps->segments);
    }

    public function testUnionDisjointSquares(): void
    {
        $result = Algorithm::union($this->square(0, 0, 1), $this->square(5, 5, 1));
        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertCount(2, $result->regions);
    }

    public function testIntersectOverlappingSquares(): void
    {
        $result = Algorithm::intersect($this->square(0, 0, 2), $this->square(1, 0, 2));
        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertNotEmpty($result->regions);
    }

    public function testDifferenceProducesNonEmptyResult(): void
    {
        $result = Algorithm::difference($this->square(0, 0, 4), $this->square(1, 1, 2));
        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertNotEmpty($result->regions);
    }

    // ── Algorithm::combine devuelve array (no CombinedPolySegments) ─────────

    public function testCombineReturnsArray(): void
    {
        $segs = Algorithm::combine(
            Algorithm::segments($this->square(0, 0, 2)),
            Algorithm::segments($this->square(1, 1, 2))
        );
        $this->assertIsArray($segs);
        $this->assertNotEmpty($segs);
        foreach ($segs as $seg) {
            $this->assertInstanceOf(Segment::class, $seg);
        }
    }

    // ── Algorithm::selectUnion/Intersect/Difference/Xor ─────────────────────
    // Ahora reciben (array, bool, bool) en lugar de CombinedPolySegments.

    public function testSelectUnionReturnsPolySegments(): void
    {
        $s1      = Algorithm::segments($this->square(0, 0, 2));
        $s2      = Algorithm::segments($this->square(1, 1, 2));
        $combined = Algorithm::combine($s1, $s2);

        $result = Algorithm::selectUnion($combined, $s1->isInverted, $s2->isInverted);

        $this->assertInstanceOf(PolySegments::class, $result);
        $this->assertNotEmpty($result->segments);
    }

    public function testSelectIntersectReturnsPolySegments(): void
    {
        $s1      = Algorithm::segments($this->square(0, 0, 2));
        $s2      = Algorithm::segments($this->square(1, 1, 2));
        $combined = Algorithm::combine($s1, $s2);

        $result = Algorithm::selectIntersect($combined, $s1->isInverted, $s2->isInverted);

        $this->assertInstanceOf(PolySegments::class, $result);
    }

    public function testSelectDifferenceReturnsPolySegments(): void
    {
        $s1      = Algorithm::segments($this->square(0, 0, 4));
        $s2      = Algorithm::segments($this->square(1, 1, 2));
        $combined = Algorithm::combine($s1, $s2);

        $result = Algorithm::selectDifference($combined, $s1->isInverted, $s2->isInverted);

        $this->assertInstanceOf(PolySegments::class, $result);
        $this->assertNotEmpty($result->segments);
    }
}
