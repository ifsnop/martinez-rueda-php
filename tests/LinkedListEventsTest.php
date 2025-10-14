<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests;

use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Node;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas para la LinkedList en su comportamiento clásico (lista enlazada).
 * No requiere el modo STATUS.
 */
final class LinkedListEventsTest extends TestCase
{
    public function testRemoveOnStandaloneNodeIsNoop(): void
    {
        $n = LinkedList::node(new Node());
        $n->value = 1;

        // No debe lanzar error aunque el nodo no esté insertado
        ($n->remove)();

        // Sigue “suelt@”: punteros nulos
        $this->assertNull($n->previous);
        $this->assertNull($n->next);
    }

    public function testInsertBeforeAppendsAndPointers(): void
    {
        $ll = new LinkedList(); // modo por defecto (EVENTS)

        $a = LinkedList::node(new Node()); $a->value = 'A';
        $b = LinkedList::node(new Node()); $b->value = 'B';
        $c = LinkedList::node(new Node()); $c->value = 'C';

        // insertBefore con check siempre false => inserta al final (append)
        $ll->insertBefore($a, static fn(Node $here): bool => false);
        $ll->insertBefore($b, static fn(Node $here): bool => false);
        $ll->insertBefore($c, static fn(Node $here): bool => false);

        $head = $ll->getHead();
        $this->assertSame($a, $head);
        $this->assertSame($b, $a->next);
        $this->assertSame($c, $b->next);
        $this->assertNull($c->next);

        $this->assertSame($a, $b->previous);
        $this->assertSame($b, $c->previous);
    }

    public function testFindTransitionInsertBeforeExistingNode(): void
    {
        $ll = new LinkedList();

        $a = LinkedList::node(new Node()); $a->value = 'A';
        $b = LinkedList::node(new Node()); $b->value = 'B';

        // Construimos [A, B]
        $ll->insertBefore($a, static fn(Node $here): bool => false);
        $ll->insertBefore($b, static fn(Node $here): bool => false);

        // Queremos insertar X antes de B usando findTransition
        $x = LinkedList::node(new Node()); $x->value = 'X';

        $tr = $ll->findTransition(
            // primera coincidencia donde $here === $b
            static fn(Node $here): bool => $here === $b
        );

        $this->assertSame($a, $tr->before);
        $this->assertSame($b, $tr->after);

        $ins = $tr->insert;
        $ins($x);

        // Lista esperada: A -> X -> B
        $head = $ll->getHead();
        $this->assertSame($a, $head);
        $this->assertSame($x, $a->next);
        $this->assertSame($b, $x->next);
        $this->assertSame($x, $b->previous);
        $this->assertSame($a, $x->previous);
    }

    public function testRemoveMiddleUpdatesNeighbors(): void
    {
        $ll = new LinkedList();

        $a = LinkedList::node(new Node()); $a->value = 'A';
        $b = LinkedList::node(new Node()); $b->value = 'B';
        $c = LinkedList::node(new Node()); $c->value = 'C';

        $ll->insertBefore($a, static fn(Node $here): bool => false);
        $ll->insertBefore($b, static fn(Node $here): bool => false);
        $ll->insertBefore($c, static fn(Node $here): bool => false);

        // Eliminar B
        ($b->remove)();

        $this->assertSame($c, $a->next);
        $this->assertSame($a, $c->previous);
        $this->assertNull($b->previous);
        $this->assertNull($b->next);
    }

    public function testRemoveIdempotent(): void
    {
        $ll = new LinkedList();

        $n = LinkedList::node(new Node());
        // 1) remove en suelto => no-op
        ($n->remove)();
        $this->assertNull($n->previous);
        $this->assertNull($n->next);

        // 2) insert y remove => punteros nulos
        $ll->insertBefore($n, static fn(Node $here): bool => false);
        ($n->remove)();
        $this->assertNull($n->previous);
        $this->assertNull($n->next);

        // 3) remove de nuevo => sigue no-op
        ($n->remove)();
        $this->assertNull($n->previous);
        $this->assertNull($n->next);
    }
}
