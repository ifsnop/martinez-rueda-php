<?php
// tests/IntersecterTest.php

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Intersecter;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Segment;
use Ifsnop\MartinezRueda\Fill;
use Ifsnop\MartinezRueda\Node;

class IntersecterTest extends TestCase
{
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
            new Point(1e-11, 1e-11)  // Menor que TOLERANCE
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
