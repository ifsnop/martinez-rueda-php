<?php


namespace Ifsnop\MartinezRueda\Tests;

use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Node;
use PHPUnit\Framework\TestCase;

ini_set('memory_limit', '384M');

/**
 * Pruebas específicas del modo STATUS (array + búsqueda binaria).
 * Se saltan automáticamente si la LinkedList no soporta este modo.
 */
final class LinkedListStatusTest extends TestCase
{
    protected function setUp(): void
    {
        if (!\defined(\Ifsnop\MartinezRueda\LinkedList::class . '::MODE_STATUS')) {
            $this->markTestSkipped('LinkedList::MODE_STATUS no disponible; se omiten pruebas de STATUS.');
        }
    }

    public function testOrderedInsertViaFindTransition(): void
    {
        $ll = new LinkedList(LinkedList::MODE_STATUS);

        // Insertar valores ordenados por "value" usando búsqueda binaria en findTransition
        foreach ([30, 10, 50, 40, 20] as $v) {
            $node = LinkedList::node(new Node());
            $node->value = $v;

            // Primera posición donde $here->value >= $v
            $tr = $ll->findTransition(static function (Node $here) use ($v): bool {
                return $here->value >= $v;
            });

            $insert = $tr->insert;
            $insert($node);
        }

        // Recorremos por next desde head y esperamos 10,20,30,40,50
        $expected = [10, 20, 30, 40, 50];
        $values   = [];

        for ($cur = $ll->getHead(); $cur !== null; $cur = $cur->next) {
            $values[] = $cur->value;
        }

        $this->assertSame($expected, $values);
    }

    public function testRemoveMiddleAndExistsIndex(): void
    {
        $ll = new LinkedList(LinkedList::MODE_STATUS);

        $a = LinkedList::node(new Node()); $a->value = 10;
        $b = LinkedList::node(new Node()); $b->value = 20;
        $c = LinkedList::node(new Node()); $c->value = 30;

        // Insertamos en orden
        foreach ([$a, $b, $c] as $n) {
            $tr = $ll->findTransition(static function (Node $here) use ($n): bool {
                return $here->value >= $n->value;
            });
            ($tr->insert)($n);
        }

        // Vecinos esperados: A <-> B <-> C
        $this->assertSame($b, $a->next);
        $this->assertSame($a, $b->previous);
        $this->assertSame($c, $b->next);
        $this->assertSame($b, $c->previous);

        // Eliminar B
        ($b->remove)();

        // A <-> C
        $this->assertSame($c, $a->next);
        $this->assertSame($a, $c->previous);
        $this->assertNull($b->previous);
        $this->assertNull($b->next);

        // En STATUS, exists() debe reflejar pertenencia al array (false tras eliminar)
        $this->assertFalse($ll->exists($b));
        $this->assertTrue($ll->exists($a));
        $this->assertTrue($ll->exists($c));
    }

    public function testRemoveOnStandaloneNodeIsNoopInStatus(): void
    {
        $ll = new LinkedList(LinkedList::MODE_STATUS);

        $n = LinkedList::node(new Node());
        $n->value = 99;

        // remove() sin insertar: no debe fallar, ni cambiar punteros
        ($n->remove)();
        $this->assertNull($n->previous);
        $this->assertNull($n->next);

        // Insertar y volver a eliminar
        $tr = $ll->findTransition(static fn(Node $here): bool => false); // append
        ($tr->insert)($n);
        ($n->remove)();

        $this->assertNull($n->previous);
        $this->assertNull($n->next);
        $this->assertFalse($ll->exists($n));
    }
}

