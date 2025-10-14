<?php
// tests/LinkedListAdditionalTest.php

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Node;

class LinkedListAdditionalTest extends TestCase
{
    private LinkedList $list;

    protected function setUp(): void
    {
        $this->list = new LinkedList();
    }

    /**
     * Test: Verificar que linkNodes crea enlaces correctos (test indirecto)
     * Insertamos 3 nodos y verificamos que todos los enlaces están correctos
     */
    public function testLinkNodesCreatesCorrectBidirectionalLinks(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 3; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $nodes[] = $node;
            $this->list->insertBefore($node, fn($n) => false);
        }

        // Verificar enlaces hacia adelante
        $current = $this->list->getHead();
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame($nodes[$i], $current);
            if ($i < 2) {
                $this->assertSame($nodes[$i + 1], $current->next);
            } else {
                $this->assertNull($current->next);
            }
            $current = $current->next;
        }

        // Verificar enlaces hacia atrás
        $current = $nodes[2];
        for ($i = 2; $i >= 0; $i--) {
            $this->assertSame($nodes[$i], $current);
            if ($i > 0) {
                $this->assertSame($nodes[$i - 1], $current->previous);
            } else {
                $this->assertNotNull($current->previous); // Apunta al root
                $this->assertTrue($current->previous->isRoot);
            }
            $current = $current->previous;
            if ($current !== null && $current->isRoot) {
                break;
            }
        }
    }

    /**
     * Test: Remover nodo que ya tiene previous y next en null (edge case)
     * No debería causar errores
     */
    public function testRemoveAlreadyDisconnectedNode(): void
    {
        $node = LinkedList::node(new Node());
        $node->value = 1;
        
        // El nodo ya está desconectado (previous y next son null)
        $this->assertNull($node->previous);
        $this->assertNull($node->next);
        
        // Llamar a remove no debería causar error
        ($node->remove)();

        $this->assertNull($node->previous);
        $this->assertNull($node->next);
    }

    /**
     * Test: Remover nodo sin next (último nodo)
     */
    public function testRemoveNodeWithoutNext(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        // node2 no tiene next (es el último)
        $this->assertNull($node2->next);
        
        ($node2->remove)();
        
        // Verificar que node1 ahora es el último
        $this->assertNull($node1->next);
        $this->assertNull($node2->previous);
    }

    /**
     * Test: Insertar múltiples nodos en posiciones específicas
     */
    public function testInsertMultipleNodesAtSpecificPositions(): void
    {
        // Crear lista: 10, 30, 50
        $node10 = LinkedList::node(new Node());
        $node10->value = 10;
        $node30 = LinkedList::node(new Node());
        $node30->value = 30;
        $node50 = LinkedList::node(new Node());
        $node50->value = 50;
        
        $this->list->insertBefore($node10, fn($n) => false);
        $this->list->insertBefore($node30, fn($n) => false);
        $this->list->insertBefore($node50, fn($n) => false);
        
        // Insertar 20 antes de 30
        $node20 = LinkedList::node(new Node());
        $node20->value = 20;
        $this->list->insertBefore($node20, fn($n) => $n->value === 30);
        
        // Insertar 40 antes de 50
        $node40 = LinkedList::node(new Node());
        $node40->value = 40;
        $this->list->insertBefore($node40, fn($n) => $n->value === 50);
        
        // Verificar orden: 10, 20, 30, 40, 50
        $expected = [10, 20, 30, 40, 50];
        $current = $this->list->getHead();
        $index = 0;
        
        while ($current !== null) {
            $this->assertEquals($expected[$index], $current->value);
            $current = $current->next;
            $index++;
        }
        
        $this->assertEquals(5, $index);
    }

    /**
     * Test: findTransition con múltiples nodos que cumplen la condición
     * Debe encontrar solo el primero
     */
    public function testFindTransitionFindsFirstMatch(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 5;
        $node2 = LinkedList::node(new Node());
        $node2->value = 10;
        $node3 = LinkedList::node(new Node());
        $node3->value = 15;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        $this->list->insertBefore($node3, fn($n) => false);
        
        // Buscar nodos con value >= 10 (node2 y node3 califican)
        $transition = $this->list->findTransition(fn($n) => $n->value >= 10);
        
        // Debe encontrar node2 (el primero que cumple)
        $this->assertSame($node1, $transition->before);
        $this->assertSame($node2, $transition->after);
    }

    /**
     * Test: Usar findTransition->insert múltiples veces en la misma posición
     */
    public function testMultipleInsertsAtSameTransition(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node3 = LinkedList::node(new Node());
        $node3->value = 3;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node3, fn($n) => false);
        
        // Insertar 2a antes de 3
        $transition = $this->list->findTransition(fn($n) => $n->value === 3);
        $node2a = LinkedList::node(new Node());
        $node2a->value = 2;
        ($transition->insert)($node2a);
        
        // Insertar 2b antes de 3 (ahora 3 está después de 2a)
        $transition2 = $this->list->findTransition(fn($n) => $n->value === 3);
        $node2b = LinkedList::node(new Node());
        $node2b->value = 2.5;
        ($transition2->insert)($node2b);
        
        // Orden esperado: 1, 2, 2.5, 3
        $expected = [1, 2, 2.5, 3];
        $current = $this->list->getHead();
        $index = 0;
        
        while ($current !== null) {
            $this->assertEquals($expected[$index], $current->value);
            $current = $current->next;
            $index++;
        }
    }

    /**
     * Test de rendimiento: Insertar muchos nodos
     */
    public function testPerformanceWithManyNodes(): void
    {
        $nodeCount = 1000;
        $nodes = [];
        
        $startTime = microtime(true);
        
        // Insertar 1000 nodos
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $nodes[] = $node;
            $this->list->insertBefore($node, fn($n) => false);
        }
        
        $insertTime = microtime(true) - $startTime;
        
        // Verificar que no está vacía
        $this->assertFalse($this->list->isEmpty());
        
        // Verificar que el primer y último nodo son correctos
        $this->assertSame($nodes[0], $this->list->getHead());
        
        // Recorrer todos los nodos
        $startTime = microtime(true);
        $current = $this->list->getHead();
        $count = 0;
        
        while ($current !== null) {
            $this->assertEquals($count, $current->value);
            $current = $current->next;
            $count++;
        }
        
        $traverseTime = microtime(true) - $startTime;
        
        $this->assertEquals($nodeCount, $count);
        
        // Asegurar que las operaciones son razonablemente rápidas
        // (menos de 100ms para 1000 nodos)
        $this->assertLessThan(0.1, $insertTime, "Inserción demasiado lenta");
        $this->assertLessThan(0.1, $traverseTime, "Recorrido demasiado lento");
    }

    /**
     * Test: Insertar al inicio usando check que siempre retorna true
     */
    public function testInsertAtBeginningAlways(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 3;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        $node3 = LinkedList::node(new Node());
        $node3->value = 1;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => true); // Siempre al inicio
        $this->list->insertBefore($node3, fn($n) => true); // Siempre al inicio
        
        // Orden esperado: 1, 2, 3 (último insertado primero)
        $this->assertSame($node3, $this->list->getHead());
        $this->assertSame($node2, $node3->next);
        $this->assertSame($node1, $node2->next);
    }

    /**
     * Test: Verificar que exists() funciona correctamente después de remover
     */
    public function testExistsAfterRemove(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->assertTrue($this->list->exists($node1));
        
        ($node1->remove)();
        
        // Después de remover, el nodo ya no está en la lista
        // pero exists() solo verifica que no sea null ni root
        $this->assertTrue($this->list->exists($node1)); // Sigue siendo un nodo válido
        $this->assertTrue($this->list->isEmpty()); // Pero la lista está vacía
    }

    /**
     * Test: Remover todos los nodos uno por uno
     */
    public function testRemoveAllNodesSequentially(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 5; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $nodes[] = $node;
            $this->list->insertBefore($node, fn($n) => false);
        }
        
        $this->assertFalse($this->list->isEmpty());
        
        // Remover todos los nodos
        foreach ($nodes as $node) {
            ($node->remove)();
        }
        
        $this->assertTrue($this->list->isEmpty());
        $this->assertNull($this->list->getHead());
    }

    /**
     * Test: Insertar nodo con check que evalúa propiedades complejas
     */
    public function testInsertWithComplexCheck(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 10;
        $node1->isStart = true;
        
        $node2 = LinkedList::node(new Node());
        $node2->value = 20;
        $node2->isStart = false;
        
        $node3 = LinkedList::node(new Node());
        $node3->value = 15;
        $node3->isStart = false;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        // Insertar node3 antes del primer nodo que no sea start y tenga value >= 20
        $this->list->insertBefore(
            $node3, 
            fn($n) => !$n->isStart && $n->value >= 20
        );
        
        // Orden esperado: 10, 15, 20
        $this->assertSame($node1, $this->list->getHead());
        $this->assertSame($node3, $node1->next);
        $this->assertSame($node2, $node3->next);
    }

    /**
     * Test: findTransition al inicio de la lista
     */
    public function testFindTransitionAtListStart(): void
    {
        $node1 = LinkedList::node(new Node());
        $node1->value = 1;
        $node2 = LinkedList::node(new Node());
        $node2->value = 2;
        
        $this->list->insertBefore($node1, fn($n) => false);
        $this->list->insertBefore($node2, fn($n) => false);
        
        // Buscar transición antes del primer nodo
        $transition = $this->list->findTransition(fn($n) => $n->value === 1);
        
        $this->assertNull($transition->before); // before es null (no el root)
        $this->assertSame($node1, $transition->after);
        
        // Insertar un nodo al inicio usando la transición
        $node0 = LinkedList::node(new Node());
        $node0->value = 0;
        ($transition->insert)($node0);
        
        $this->assertSame($node0, $this->list->getHead());
    }
}
