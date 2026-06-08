<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('autoloader.php');

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\SegmentIntersecter;
use Ifsnop\MartinezRueda\RegionIntersecter;
use Ifsnop\MartinezRueda\Fill;
use Ifsnop\MartinezRueda\Intersecter;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\PolySegments;
use Ifsnop\MartinezRueda\PolyBoolException;
use Ifsnop\MartinezRueda\Segment;
use Ifsnop\MartinezRueda\StatusList;
use Ifsnop\MartinezRueda\Node;

// ═════════════════════════════════════════════════════════════════════════════
// 5. INTERSECTER (vía RegionIntersecter y SegmentIntersecter)
// ─────────────────────────────────────────────────────────────────────────────
// Intersecter es el corazón del algoritmo Bentley-Ottmann.  Los tests validan
// el contrato público observable: que el número y geometría de los segmentos
// de salida sean correctos para entradas canónicas.
// ═════════════════════════════════════════════════════════════════════════════
class IntersecterTest extends TestCase
{

    // ── Helper: crea un Polygon simple (cuadrado) ────────────────────────────
    private function square(float $x, float $y, float $size): Polygon
    {
        return Polygon::create()->fillFromArray([[
            new Point($x,         $y),
            new Point($x + $size, $y),
            new Point($x + $size, $y + $size),
            new Point($x,         $y + $size),
        ]]);
    }

    // ── RegionIntersecter ────────────────────────────────────────────────────

    public function testRegionIntersecterProducesSegmentsForSquare(): void
    {
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0),
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 1),
        ]);
        $segs = $ri->calculate2(false);
        // Un cuadrado tiene 4 lados → 4 segmentos
        $this->assertCount(4, $segs);
    }

    public function testRegionIntersecterAllSegmentsAreSegmentInstances(): void
    {
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0), new Point(2, 0),
            new Point(2, 2), new Point(0, 2),
        ]);
        foreach ($ri->calculate2(false) as $s) {
            $this->assertInstanceOf(Segment::class, $s);
        }
    }

    public function testRegionIntersecterSkipsDegenerateEdge(): void
    {
        // Un "cuadrado" con un punto repetido → ese borde es degenerado y se salta
        $ri = new RegionIntersecter();
        $ri->addRegion([
            new Point(0, 0),
            new Point(0, 0), // duplicado → borde de longitud 0
            new Point(1, 0),
            new Point(1, 1),
            new Point(0, 1),
        ]);
        $segs = $ri->calculate2(false);
        // El borde degenerado se omite; los 4 válidos quedan
        $this->assertCount(4, $segs);
    }

    // ── eventAddSegment: detección de segmento de longitud 0 ────────────────

    public function testEventAddSegmentThrowsOnZeroLength(): void
    {
        $this->expectException(PolyBoolException::class);

        $ri  = new RegionIntersecter();
        $seg = new Segment(new Point(1, 1), new Point(1, 1), new Fill());
        $ri->eventAddSegment($seg, true);
    }

    // ── eventAddSegment: normaliza dirección ────────────────────────────────

    public function testEventAddSegmentNormalizesDirection(): void
    {
        // Si start > end lexicográficamente, deben invertirse
        $ri  = new RegionIntersecter();
        $seg = new Segment(new Point(5, 5), new Point(1, 1), new Fill());
        $ri->eventAddSegment($seg, true);
        // Después de normalizar: start debe ser el punto "menor"
        $this->assertSame(1.0, $seg->start->x);
        $this->assertSame(1.0, $seg->start->y);
    }

    // ── SegmentIntersecter: dos cuadrados separados ──────────────────────────

    public function testSegmentIntersecterSeparatedSquares(): void
    {
        $poly1 = $this->square(0, 0, 1);
        $poly2 = $this->square(5, 5, 1); // sin solapamiento

        $segs1 = Algorithm::segments($poly1);
        $segs2 = Algorithm::segments($poly2);

        $si   = new SegmentIntersecter();
        $segs = $si->calculate2(
            $segs1->segments, false,
            $segs2->segments, false
        );
        // Sin cruce → 8 segmentos (4+4), sin subdivisiones
        $this->assertCount(8, $segs);
    }

    // ── SegmentIntersecter: dos cuadrados solapados ──────────────────────────

    public function testSegmentIntersecterOverlappingSquares(): void
    {
        $poly1 = $this->square(0, 0, 2);
        $poly2 = $this->square(1, 0, 2); // solapan en x=[1,2]

        $segs1 = Algorithm::segments($poly1);
        $segs2 = Algorithm::segments($poly2);

        $si   = new SegmentIntersecter();
        $segs = $si->calculate2(
            $segs1->segments, false,
            $segs2->segments, false
        );

        // Los segmentos solapados se subdividen → más de 8 segmentos
        $this->assertGreaterThan(8, count($segs));
    }

    // ── Algorithm::segments devuelve PolySegments válido ────────────────────

    public function testAlgorithmSegmentsReturnsPolySegments(): void
    {
        $poly = $this->square(0, 0, 1);
        $ps   = Algorithm::segments($poly);
        $this->assertInstanceOf(PolySegments::class, $ps);
        $this->assertNotEmpty($ps->segments);
    }

    // ── Algorithm alto nivel: unión de cuadrados separados ──────────────────

    public function testUnionDisjointSquares(): void
    {
        $poly1 = $this->square(0, 0, 1);
        $poly2 = $this->square(5, 5, 1);

        $result = Algorithm::union($poly1, $poly2);

        $this->assertInstanceOf(Polygon::class, $result);
        // Resultado: dos regiones independientes
        $this->assertCount(2, $result->regions);
    }

    // ── Algorithm alto nivel: intersección de cuadrados solapados ───────────

    public function testIntersectOverlappingSquares(): void
    {
        $poly1 = $this->square(0, 0, 2);
        $poly2 = $this->square(1, 0, 2); // intersección = [1,2]×[0,2]

        $result = Algorithm::intersect($poly1, $poly2);

        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertNotEmpty($result->regions);
    }

    // ── Algorithm alto nivel: diferencia deja un solo polígono ──────────────

    public function testDifferenceProducesNonEmptyResult(): void
    {
        $poly1 = $this->square(0, 0, 4);
        $poly2 = $this->square(1, 1, 2); // cuadrado pequeño interior

        $result = Algorithm::difference($poly1, $poly2);

        $this->assertInstanceOf(Polygon::class, $result);
        $this->assertNotEmpty($result->regions);
    }

    // ── newSegment y segmentCopy ─────────────────────────────────────────────

    public function testNewSegmentHasFill(): void
    {
        $ri  = new RegionIntersecter();
        $seg = $ri->newSegment(new Point(0, 0), new Point(1, 1));
        $this->assertInstanceOf(Fill::class, $seg->myFill);
    }

    public function testSegmentCopyCopiesFill(): void
    {
        $ri      = new RegionIntersecter();
        $fill    = new Fill(true, false);
        $orig    = new Segment(new Point(0, 0), new Point(1, 1), $fill);
        $copy    = $ri->segmentCopy(new Point(0, 0), new Point(1, 1), $orig);
        $this->assertTrue($copy->myFill->below);
        $this->assertFalse($copy->myFill->above);
    }

    public function testSegmentCopyIsIndependent(): void
    {
        $ri   = new RegionIntersecter();
        $fill = new Fill(true, false);
        $orig = new Segment(new Point(0, 0), new Point(1, 1), $fill);
        $copy = $ri->segmentCopy(new Point(0, 0), new Point(1, 1), $orig);

        // Mutar la copia no afecta al original
        $copy->myFill->below = false;
        $this->assertTrue($orig->myFill->below);
    }
private Intersecter $intersecter;

    protected function setUp(): void
    {
        $this->intersecter = new Intersecter(selfIntersection: false);
    }

    /**
     * Test: Constructor inicializa correctamente
     */
    public function testConstructorInitialization(): void
    {
        $intersecter1 = new Intersecter(selfIntersection: true);
        $intersecter2 = new Intersecter(selfIntersection: false);
        
        $this->assertInstanceOf(Intersecter::class, $intersecter1);
        $this->assertInstanceOf(Intersecter::class, $intersecter2);
    }

    /**
     * Test: newSegment crea un segmento nuevo correctamente
     */
    public function testNewSegmentCreation(): void
    {
        $start = new Point(0.0, 0.0);
        $end = new Point(1.0, 1.0);
        
        $segment = $this->intersecter->newSegment($start, $end);
        
        $this->assertInstanceOf(Segment::class, $segment);
        $this->assertSame($start, $segment->start);
        $this->assertSame($end, $segment->end);
        $this->assertInstanceOf(Fill::class, $segment->myFill);
        $this->assertNull($segment->myFill->below);
        $this->assertNull($segment->myFill->above);
        $this->assertNull($segment->otherFill);
    }

    /**
     * Test: segmentCopy copia un segmento correctamente
     */
    public function testSegmentCopy(): void
    {
        $start = new Point(0.0, 0.0);
        $end = new Point(1.0, 1.0);
        $originalSegment = $this->intersecter->newSegment($start, $end);
        $originalSegment->myFill->below = true;
        $originalSegment->myFill->above = false;
        
        $newStart = new Point(0.5, 0.5);
        $newEnd = new Point(1.5, 1.5);
        $copiedSegment = $this->intersecter->segmentCopy($newStart, $newEnd, $originalSegment);
        
        $this->assertInstanceOf(Segment::class, $copiedSegment);
        $this->assertSame($newStart, $copiedSegment->start);
        $this->assertSame($newEnd, $copiedSegment->end);
        $this->assertEquals($originalSegment->myFill->below, $copiedSegment->myFill->below);
        $this->assertEquals($originalSegment->myFill->above, $copiedSegment->myFill->above);
    }

    /**
     * Test: eventAddSegment agrega un segmento al evento
     */
    public function testEventAddSegment(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 1.0)
        );
        
        $evStart = $this->intersecter->eventAddSegment($segment, primary: true);
        
        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertTrue($evStart->isStart);
        $this->assertTrue($evStart->primary);
        $this->assertSame($segment, $evStart->seg);
        $this->assertNotNull($evStart->other);
        $this->assertFalse($evStart->other->isStart);
    }

    /**
     * Test: eventAddSegment con segmento horizontal
     */
    public function testEventAddSegmentHorizontal(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 1.0),
            new Point(5.0, 1.0)
        );
        
        $evStart = $this->intersecter->eventAddSegment($segment, primary: false);
        
        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertFalse($evStart->primary);
        $this->assertEquals(0.0, $evStart->pt->getArray()[0]);
        $this->assertEquals(1.0, $evStart->pt->getArray()[1]);
    }

    /**
     * Test: eventAddSegment con segmento vertical
     */
    public function testEventAddSegmentVertical(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(1.0, 0.0),
            new Point(1.0, 5.0)
        );
        
        $evStart = $this->intersecter->eventAddSegment($segment, primary: true);
        
        $this->assertInstanceOf(Node::class, $evStart);
        $this->assertEquals(1.0, $evStart->pt->getArray()[0]);
        $this->assertEquals(0.0, $evStart->pt->getArray()[1]);
    }

    /**
     * Test: calculate con lista vacía
     */
    public function testCalculateWithEmptyList(): void
    {
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test: calculate con un solo segmento
     */
    public function testCalculateWithSingleSegment(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 1.0)
        );
        
        $this->intersecter->eventAddSegment($segment, primary: true);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Segment::class, $result[0]);
    }

    /**
     * Test: calculate con dos segmentos que no se intersectan
     */
    public function testCalculateWithNonIntersectingSegments(): void
    {
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 0.0)
        );
        
        $segment2 = $this->intersecter->newSegment(
            new Point(0.0, 2.0),
            new Point(1.0, 2.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test: calculate con dos segmentos que se cruzan
     */
    public function testCalculateWithIntersectingSegments(): void
    {
        // Segmento horizontal: (0,1) -> (2,1)
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 1.0),
            new Point(2.0, 1.0)
        );
        
        // Segmento vertical: (1,0) -> (1,2)
        $segment2 = $this->intersecter->newSegment(
            new Point(1.0, 0.0),
            new Point(1.0, 2.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        // Deben dividirse en el punto de intersección (1,1)
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    /**
     * Test: calculate con segmentos colineales
     */
    public function testCalculateWithCollinearSegments(): void
    {
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(2.0, 0.0)
        );
        
        $segment2 = $this->intersecter->newSegment(
            new Point(1.0, 0.0),
            new Point(3.0, 0.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test: Self-intersection habilitado
     */
    public function testSelfIntersectionEnabled(): void
    {
        $intersecter = new Intersecter(selfIntersection: true);
        
        $segment1 = $intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(2.0, 2.0)
        );
        
        $segment2 = $intersecter->newSegment(
            new Point(0.0, 2.0),
            new Point(2.0, 0.0)
        );
        
        $intersecter->eventAddSegment($segment1, primary: true);
        $intersecter->eventAddSegment($segment2, primary: true);
        
        $result = $intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test: Segmentos que comparten un punto final
     */
    public function testSegmentsSharingEndpoint(): void
    {
        $sharedPoint = new Point(1.0, 1.0);
        
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            $sharedPoint
        );
        
        $segment2 = $this->intersecter->newSegment(
            $sharedPoint,
            new Point(2.0, 2.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test: Múltiples segmentos paralelos sin intersección
     */
    public function testMultipleParallelSegments(): void
    {
        // Crear segmentos paralelos horizontales
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 0.0)
        );
        
        $segment2 = $this->intersecter->newSegment(
            new Point(0.0, 1.0),
            new Point(1.0, 1.0)
        );
        
        $segment3 = $this->intersecter->newSegment(
            new Point(0.0, 2.0),
            new Point(1.0, 2.0)
        );
        
        $segment4 = $this->intersecter->newSegment(
            new Point(0.0, 3.0),
            new Point(1.0, 3.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: true);
        $this->intersecter->eventAddSegment($segment3, primary: false);
        $this->intersecter->eventAddSegment($segment4, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertCount(4, $result);
    }

    /**
     * Test: Polígonos invertidos
     */
    public function testInvertedPolygons(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 1.0)
        );
        
        $this->intersecter->eventAddSegment($segment, primary: true);
        
        $result1 = $this->intersecter->calculate(
            primaryPolyInverted: true,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result1);
        $this->assertNotEmpty($result1);
        
        // Verificar que myFill se establece correctamente
        $this->assertNotNull($result1[0]->myFill);
    }

    /**
     * Test: Segmentos pequeños pero válidos (por encima de TOLERANCE)
     */
    public function testSmallButValidSegments(): void
    {
        // Crear un segmento pequeño pero por encima de TOLERANCE (1e-10)
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1e-8, 1e-8)  // Más grande que TOLERANCE
        );
        
        $this->intersecter->eventAddSegment($segment, primary: true);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
    
    /**
     * Test: Excepción con segmento de longitud cero
     */
    public function testZeroLengthSegmentThrowsException(): void
    {
        $this->expectException(\Ifsnop\MartinezRueda\PolyBoolException::class);
        $this->expectExceptionMessage('Zero-length segment detected');
        
        // Crear un segmento por debajo de TOLERANCE (causará error)
        $segment = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(Algorithm::TOLERANCE/100.0,
	Algorithm::TOLERANCE/100.0)  // Menor que TOLERANCE
        );
        
        $this->intersecter->eventAddSegment($segment, primary: true);
        
        // Esto debería lanzar PolyBoolException
        $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
    }

    /**
     * Test: Segmentos con coordenadas negativas
     */
    public function testNegativeCoordinates(): void
    {
        $segment1 = $this->intersecter->newSegment(
            new Point(-2.0, -2.0),
            new Point(-1.0, -1.0)
        );
        
        $segment2 = $this->intersecter->newSegment(
            new Point(-2.0, -1.0),
            new Point(-1.0, -2.0)
        );
        
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: false);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test: Segmentos con coordenadas decimales complejas
     */
    public function testComplexDecimalCoordinates(): void
    {
        $segment = $this->intersecter->newSegment(
            new Point(0.123456789, 0.987654321),
            new Point(1.111111111, 2.222222222)
        );
        
        $this->intersecter->eventAddSegment($segment, primary: true);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test: Orden de los eventos se mantiene correcto
     */
    public function testEventOrderingMaintained(): void
    {
        // Agregar segmentos en orden no espacial
        $segment3 = $this->intersecter->newSegment(
            new Point(4.0, 0.0),
            new Point(5.0, 0.0)
        );
        
        $segment1 = $this->intersecter->newSegment(
            new Point(0.0, 0.0),
            new Point(1.0, 0.0)
        );
        
        $segment2 = $this->intersecter->newSegment(
            new Point(2.0, 0.0),
            new Point(3.0, 0.0)
        );
        
        $this->intersecter->eventAddSegment($segment3, primary: true);
        $this->intersecter->eventAddSegment($segment1, primary: true);
        $this->intersecter->eventAddSegment($segment2, primary: true);
        
        $result = $this->intersecter->calculate(
            primaryPolyInverted: false,
            secondaryPolyInverted: false
        );
        
        // Los segmentos deben procesarse en orden espacial correcto
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

}
