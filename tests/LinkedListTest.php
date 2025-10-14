<?php
// tests/LinkedListTest.php

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Node;
use Ifsnop\MartinezRueda\Transition;
use Ifsnop\MartinezRueda\Algorithm;

class LinkedListTest extends TestCase
{
    private LinkedList $list;

    protected function setUp(): void
    {
        $this->list = new LinkedList();
    }

    /**
     * Test: Una lista recién creada debe estar vacía
     */
    public function testNewListIsEmpty(): void
    {
        $this->assertTrue($this->list->isEmpty());
        $this->assertNull($this->list->getHead());
    }

    /**
     * Test: exists() debe retornar false para nodos null o root
     */
    public function testExistsReturnsFalseForNull(): void
    {
        $this->assertFalse($this->list->exists(null));
    }

    /**
     * Test: exists() debe retornar true para un nodo válido
     */
    public function testExistsReturnsTrueForValidNode(): void
    {
        $node = LinkedList::node(new Node());
        $node->value = 1;
        $this->list->insertBefore($node, fn($n) => false);
        
        $this->assertTrue($this->list->exists($node));
    }

    /**
     * Test: Insertar un nodo en una lista vacía
     */
    public function testInsertIntoEmptyList(): void
    {
        $node = LinkedList::node(new Node());
        $node->value = 1;
        $this->list->insertBefore($node, fn($n) => false);
        
        $this->assertFalse($this->list->isEmpty());
        $this->assertSame($node, $this->list->getHead());
        $this->assertNull($node->next);
    }

    /**
     * Test: Insertar múltiples nodos al final (check siempre retorna false)
     */
    public function testInsertMultipleNodesAtEnd(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        $node3 = LinkedList::node(new Node());
        $node3->value = 3;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        $this->list->insertBefore($node3, fn($n) => false);
        
        $head = $this->list->getHead();
        $this->assertSame($node1, $head);
        $this->assertSame($node2, $head->next);
        $this->assertSame($node3, $head->next->next);
        $this->assertNull($head->next->next->next);
    }

    /**
     * Test: Insertar un nodo antes de uno específico usando check
     */
    public function testInsertBeforeSpecificNode(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 3;
        $node3 = LinkedList::node(new Node());
        $node3->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        // Insertar node3 antes del nodo con value = 3
        $this->list->insertBefore($node3, fn($n) => $n->value === 3);
        
        $head = $this->list->getHead();
        $this->assertSame($node1, $head);
        $this->assertSame($node3, $head->next);
        $this->assertSame($node2, $head->next->next);
    }

    /**
     * Test: Verificar enlaces bidireccionales después de insertar
     */
    public function testBidirectionalLinksAfterInsert(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        // node1 es el primer nodo, su previous apunta al root (no es null)
        $this->assertNotNull($node1->previous);
        $this->assertTrue($node1->previous->isRoot);
        // node1->next apunta a node2
        $this->assertSame($node2, $node1->next);
        // node2->previous apunta a node1
        $this->assertSame($node1, $node2->previous);
        // node2 es el último, su next es null
        $this->assertNull($node2->next);
    }

    /**
     * Test: findTransition en una lista vacía
     */
    public function testFindTransitionInEmptyList(): void
    {
        $transition = $this->list->findTransition(fn($n) => true);
        
        $this->assertNull($transition->before);
        $this->assertNull($transition->after);
        $this->assertIsCallable($transition->insert);
    }

    /**
     * Test: findTransition cuando no se encuentra condición
     */
    public function testFindTransitionNotFound(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $this->list->insertBefore($node1, fn($n) => false);
        
        $transition = $this->list->findTransition(fn($n) => $n->value === 999);
        
        $this->assertSame($node1, $transition->before);
        $this->assertNull($transition->after);
    }

    /**
     * Test: findTransition encuentra el primer nodo que cumple condición
     */
    public function testFindTransitionFindsMatch(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        $node3 = LinkedList::node(new Node());
        $node3->value = 3;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        $this->list->insertBefore($node3, fn($n) => false);
        
        $transition = $this->list->findTransition(fn($n) => $n->value === 2);
        
        $this->assertSame($node1, $transition->before);
        $this->assertSame($node2, $transition->after);
    }

    /**
     * Test: Usar el insert de Transition para agregar un nodo
     */
    public function testTransitionInsertFunction(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 3;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        $transition = $this->list->findTransition(fn($n) => $n->value === 3);
        $newNode = LinkedList::node(new Node());
        $newNode->value = 2;
        $result = ($transition->insert)($newNode);
        
        $this->assertSame($newNode, $result);
        $this->assertSame($node1, $newNode->previous);
        $this->assertSame($node2, $newNode->next);
    }

    /**
     * Test: node() inicializa correctamente previous y next
     */
    public function testNodeInitialization(): void
    {
        $nodeData = new Node();
        $node = LinkedList::node($nodeData);
        
        $this->assertNull($node->previous);
        $this->assertNull($node->next);
        $this->assertIsCallable($node->remove);
    }

    /**
     * Test: Función remove de un nodo
     */
    public function testNodeRemoveFunction(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        $node3 = LinkedList::node(new Node());
        $node3->value = 3;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        $this->list->insertBefore($node3, fn($n) => false);
        
        // Remover el nodo del medio
        ($node2->remove)();
        
        $this->assertNull($node2->previous);
        $this->assertNull($node2->next);
        $this->assertSame($node3, $node1->next);
        $this->assertSame($node1, $node3->previous);
    }

    /**
     * Test: Remover el primer nodo
     */
    public function testRemoveFirstNode(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        ($node1->remove)();
        
        // Después de remover node1, node2 debería ser el head
        $this->assertSame($node2, $this->list->getHead());
        // node2->previous debería apuntar al root (no null)
        $this->assertNotNull($node2->previous);
        // node1 debería estar desconectado
        $this->assertNull($node1->previous);
        $this->assertNull($node1->next);
    }

    /**
     * Test: Remover el último nodo
     */
    public function testRemoveLastNode(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        ($node2->remove)();
        
        $this->assertNull($node1->next);
        $this->assertNull($node2->previous);
        $this->assertNull($node2->next);
    }

    /**
     * Test: Insertar en la primera posición (check retorna true en el primer nodo)
     */
    public function testInsertAtBeginning(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 0;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => true); // Siempre inserta antes del primero
        
        $this->assertSame($node2, $this->list->getHead());
        $this->assertSame($node1, $node2->next);
    }

    /**
     * Test: Escenario complejo - múltiples inserciones y verificación de orden
     */
    public function testComplexScenario(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 5; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i * 10;
            $nodes[$i] = $node;
        }
        
        // Insertar en orden: 10, 20, 30, 40, 50
        foreach ($nodes as $node) {
            $this->list->insertBefore($node, fn($n) => false);
        }
        
        // Verificar orden
        $current = $this->list->getHead();
        $expectedValues = [10, 20, 30, 40, 50];
        $index = 0;
        
        while ($current !== null) {
            $this->assertEquals($expectedValues[$index], $current->value);
            $current = $current->next;
            $index++;
        }
        
        $this->assertEquals(5, $index);
    }
}
