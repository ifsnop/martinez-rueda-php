<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Segment;
use Ifsnop\MartinezRueda\Node;
use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Algorithm;

final class EventQueueTest extends TestCase
{
    private float $T;

    protected function setUp(): void
    {
        $this->T = Algorithm::TOLERANCE;
        $this->assertGreaterThan(0.0, $this->T);
    }

    /**
     * Réplica de Intersecter::eventCompare.
     * Devuelve -1 si p1 < p2, 0 si iguales, 1 si p1 > p2.
     */
    private static function eventCompare(
        bool $p1IsStart, Point $p11, Point $p12,
        bool $p2IsStart, Point $p21, Point $p22
    ): int {
        $comp = Point::compare($p11, $p21);
        if ($comp !== 0) {
            return $comp;
        }
        // Igualdad de "otros extremos" (tu código usa '==', no tolerancia; elegimos casos exactos)
        if ($p12 == $p22) {
            return 0;
        }
        if ($p1IsStart !== $p2IsStart) {
            return $p1IsStart ? 1 : -1; // END antes que START
        }
        $refL = $p2IsStart ? $p21 : $p22;
        $refR = $p2IsStart ? $p22 : $p21;
        return Point::pointAboveOrOnLine($p12, $refL, $refR) ? 1 : -1;
    }

    /**
     * Inserta en LinkedList con la misma condición que usa Intersecter::eventAdd($ev, $otherPt).
     */
    private static function enqueue(LinkedList $q, Node $ev, Point $otherPt): void
    {
        $check = function (Node $here) use ($ev, $otherPt): bool {
            $cmp = self::eventCompare(
                $ev->isStart,   $ev->pt,        $otherPt,
                $here->isStart, $here->pt,      $here->other->pt
            );
            return $cmp < 0; // insertar antes del primer 'mayor'
        };
        $q->insertBefore($ev, $check);
    }

    /** Recolecta la cola en orden, devolviendo arreglo de nodos */
    private static function dumpQueue(LinkedList $q): array
    {
        $out = [];
        $cur = $q->getHead();
        while ($cur !== null) {
            $out[] = $cur;
            $cur = $cur->next;
        }
        return $out;
    }

    public function testEndBeforeStartOnSamePoint(): void
    {
        $q = new LinkedList();

        // Segmento A: start en (0,0), end en (1,0) → start event en (0,0)
        $aStart = new Point(0, 0);
        $aEnd   = new Point(1, 0);
        $segA   = new Segment($aStart, $aEnd);

        $evAStart = LinkedList::node(new Node(isStart: true,  pt: $aStart, seg: $segA, primary: true));
        $evAEnd   = LinkedList::node(new Node(isStart: false, pt: $aEnd,   seg: $segA, primary: true, other: $evAStart));
        $evAStart->other = $evAEnd;

        // Segmento B: end en (0,0) (mismo punto), start en (-1,0) → end event en (0,0)
        $bStart = new Point(-1, 0);
        $bEnd   = new Point( 0, 0);
        $segB   = new Segment($bStart, $bEnd);

        $evBStart = LinkedList::node(new Node(isStart: true,  pt: $bStart, seg: $segB, primary: true));
        $evBEnd   = LinkedList::node(new Node(isStart: false, pt: $bEnd,   seg: $segB, primary: true, other: $evBStart));
        $evBStart->other = $evBEnd;

        // Encolamos primero el START de A y luego el END de B, mismo punto (0,0)
        self::enqueue($q, $evAStart, $aEnd);
        self::enqueue($q, $evBEnd,   $bStart);

        $arr = self::dumpQueue($q);

        // En (0,0) debe ir antes el END (evBEnd) que el START (evAStart)
        $this->assertSame($evBEnd,   $arr[0], 'END debe ir antes que START en empate de punto');
        $this->assertSame($evAStart, $arr[1]);
    }

    public function testOrientationTieBreakForTwoStartsAtSamePoint(): void
    {
        $q = new LinkedList();

        // Dos segmentos que empiezan en el mismo punto (0,0), direcciones distintas
        $p0 = new Point(0, 0);

        $s1 = new Segment($p0, new Point(1,  1)); // 45°
        $s2 = new Segment($p0, new Point(1, -1)); // -45°

        $ev1 = LinkedList::node(new Node(isStart: true, pt: $p0, seg: $s1, primary: true));
        $ev2 = LinkedList::node(new Node(isStart: true, pt: $p0, seg: $s2, primary: true));

        // "other" debe apuntar a su par (end); aquí creamos ends mínimos para cumplir el contrato:
        $end1 = LinkedList::node(new Node(isStart: false, pt: $s1->end, seg: $s1, primary: true, other: $ev1));
        $end2 = LinkedList::node(new Node(isStart: false, pt: $s2->end, seg: $s2, primary: true, other: $ev2));
        $ev1->other = $end1;
        $ev2->other = $end2;

        self::enqueue($q, $ev1, $s1->end);
        self::enqueue($q, $ev2, $s2->end);

        $arr = self::dumpQueue($q);

        // Calcula cuál debería ir primero según el comparador exacto:
        $cmp = self::eventCompare(true, $p0, $s1->end, true, $p0, $s2->end);

        if ($cmp < 0) {
            $this->assertSame($ev1, $arr[0]);
            $this->assertSame($ev2, $arr[1]);
        } elseif ($cmp > 0) {
            $this->assertSame($ev2, $arr[0]);
            $this->assertSame($ev1, $arr[1]);
        } else {
            // Igualdad total (muy improbable aquí), aceptamos cualquiera
            $this->assertContains($ev1, $arr);
            $this->assertContains($ev2, $arr);
        }
    }

    public function testReorderAfterEndMoved_simulatingEventUpdateEnd(): void
    {
        $q = new LinkedList();

        // Segmento A: (0,0) -> (10,0)
        $a0 = new Point(0, 0);
        $a1 = new Point(10, 0);
        $segA = new Segment($a0, $a1);

        $evAStart = LinkedList::node(new Node(isStart: true,  pt: $a0, seg: $segA, primary: true));
        $evAEnd   = LinkedList::node(new Node(isStart: false, pt: $a1, seg: $segA, primary: true, other: $evAStart));
        $evAStart->other = $evAEnd;

        // Segmento B: (2,0) -> (15,0)
        $b0 = new Point(2, 0);
        $b1 = new Point(15, 0);
        $segB = new Segment($b0, $b1);

        $evBStart = LinkedList::node(new Node(isStart: true,  pt: $b0, seg: $segB, primary: true));
        $evBEnd   = LinkedList::node(new Node(isStart: false, pt: $b1, seg: $segB, primary: true, other: $evBStart));
        $evBStart->other = $evBEnd;

        // Encolar: starts y ends
        self::enqueue($q, $evAStart, $a1);
        self::enqueue($q, $evAEnd,   $a0);

        self::enqueue($q, $evBStart, $b1);
        self::enqueue($q, $evBEnd,   $b0);

        // Antes de mover, A.end (x=10) debe ir antes que B.end (x=15)
        $arr1 = self::dumpQueue($q);
        $posAend1 = array_search($evAEnd, $arr1, true);
        $posBend1 = array_search($evBEnd, $arr1, true);
        $this->assertNotFalse($posAend1);
        $this->assertNotFalse($posBend1);
        $this->assertLessThan($posBend1, $posAend1, 'end(A) debe ir antes que end(B) con x=10<15');

        // Simular Intersecter::eventUpdateEnd(A, newEnd) ⇒ remove + actualizar + reinsert
        $newAend = new Point(20, 0);
        ($evAEnd->remove)();          // remove end(A)
        $segA->end = $newAend;        // actualizar segmento
        $evAEnd->pt = $newAend;       // actualizar evento end(A)
        self::enqueue($q, $evAEnd, $evAStart->pt); // reinsert con otherPt=start(A)

        // Ahora end(A) debe quedar DESPUÉS de end(B)
        $arr2 = self::dumpQueue($q);
        $posAend2 = array_search($evAEnd, $arr2, true);
        $posBend2 = array_search($evBEnd, $arr2, true);
        $this->assertGreaterThan($posBend2, $posAend2, 'end(A) debe reordenarse detrás de end(B) tras mover x=20');
    }

    public function testSortedByXThenYBasic(): void
    {
        $q = new LinkedList();

        // Tres eventos START en x ascendentes y/o y crecientes
        $p1 = new Point(0, 0);
        $p2 = new Point(0 + $this->T / 2.0, 1); // |dx|<T ⇒ ordena por y
        $p3 = new Point(1, -1);

        $s1 = new Segment($p1, new Point(2, 0));
        $s2 = new Segment($p2, new Point(2, 1));
        $s3 = new Segment($p3, new Point(2, 2));

        $e1 = LinkedList::node(new Node(isStart: true, pt: $p1, seg: $s1, primary: true));
        $e2 = LinkedList::node(new Node(isStart: true, pt: $p2, seg: $s2, primary: true));
        $e3 = LinkedList::node(new Node(isStart: true, pt: $p3, seg: $s3, primary: true));

        // crear "other" mínimos (end) para cumplir contrato:
        $e1->other = LinkedList::node(new Node(isStart: false, pt: $s1->end, seg: $s1, primary: true, other: $e1));
        $e2->other = LinkedList::node(new Node(isStart: false, pt: $s2->end, seg: $s2, primary: true, other: $e2));
        $e3->other = LinkedList::node(new Node(isStart: false, pt: $s3->end, seg: $s3, primary: true, other: $e3));

        self::enqueue($q, $e3, $s3->end);
        self::enqueue($q, $e2, $s2->end);
        self::enqueue($q, $e1, $s1->end);

        $arr = self::dumpQueue($q);
        // Orden esperado: (0,0) < (0+dx,1) < (1,-1) (por x y luego y)
        $this->assertSame($e1, $arr[0]);
        $this->assertSame($e2, $arr[1]);
        $this->assertSame($e3, $arr[2]);
    }
}
