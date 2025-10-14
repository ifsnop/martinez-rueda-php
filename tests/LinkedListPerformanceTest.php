<?php
// tests/LinkedListPerformanceTest.php

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\StatusList;
use Ifsnop\MartinezRueda\LinkedList;
use Ifsnop\MartinezRueda\Node;

/**
 * Suite completa de tests de rendimiento para LinkedList
 * Verifica que las operaciones sean eficientes incluso con grandes volúmenes de datos
 */
class LinkedListPerformanceTest extends TestCase
{
    private const SMALL_DATASET = 100;
    private const MEDIUM_DATASET = 1000;
    private const LARGE_DATASET = 10000;
    
    /**
     * Test: Rendimiento de inserción al final (append)
     * Complejidad esperada: O(n) por cada inserción
     */
    public function testInsertionPerformanceAtEnd(): void
    {
        $list = new LinkedList();
        $nodes = [];
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Insertar nodos al final
        for ($i = 0; $i < self::MEDIUM_DATASET; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $nodes[] = $node;
            $list->insertBefore($node, fn($n) => false);
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = $endTime - $startTime;
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        // Verificaciones
        $this->assertFalse($list->isEmpty());
        $this->assertSame($nodes[0], $list->getHead());
        
        // Tiempo razonable: menos de 100ms para 1000 inserciones
        $this->assertLessThan(0.1, $executionTime, 
            sprintf("Inserción al final demasiado lenta: %.4fs", $executionTime));
        
        // Memoria razonable: menos de 10MB para 1000 nodos
        $this->assertLessThan(10, $memoryUsed,
            sprintf("Uso de memoria excesivo: %.2f MB", $memoryUsed));
        
        echo sprintf("\n[PERF] Inserción al final (%d nodos): %.4fs, %.2f MB\n", 
            self::MEDIUM_DATASET, $executionTime, $memoryUsed);
    }
    
    /**
     * Test: Rendimiento de inserción al inicio
     * Complejidad esperada: O(1) por cada inserción
     */
    public function testInsertionPerformanceAtBeginning(): void
    {
        $list = new LinkedList();
        
        $startTime = microtime(true);
        
        // Insertar siempre al inicio
        for ($i = 0; $i < self::MEDIUM_DATASET; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list->insertBefore($node, fn($n) => true); // Siempre inserta al inicio
        }
        
        $executionTime = microtime(true) - $startTime;
        
        // Debe ser mucho más rápido que insertar al final
        $this->assertLessThan(0.05, $executionTime,
            sprintf("Inserción al inicio demasiado lenta: %.4fs", $executionTime));
        
        echo sprintf("\n[PERF] Inserción al inicio (%d nodos): %.4fs\n", 
            self::MEDIUM_DATASET, $executionTime);
    }
    
    /**
     * Test: Rendimiento de búsqueda (findTransition)
     * Complejidad esperada: O(n) en el peor caso
     */
    public function testSearchPerformance(): void
    {
        $list = new LinkedList();
        
        // Preparar lista con muchos nodos
        for ($i = 0; $i < self::MEDIUM_DATASET; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list->insertBefore($node, fn($n) => false);
        }
        
        // Caso mejor: encontrar el primero
        $startTime = microtime(true);
        $transition = $list->findTransition(fn($n) => $n->value === 0);
        $bestCase = microtime(true) - $startTime;
        $this->assertNotNull($transition->after);
        
        // Caso promedio: encontrar uno en el medio
        $startTime = microtime(true);
        $transition = $list->findTransition(fn($n) => $n->value === self::MEDIUM_DATASET / 2);
        $avgCase = microtime(true) - $startTime;
        $this->assertNotNull($transition->after);
        
        // Caso peor: encontrar el último
        $startTime = microtime(true);
        $transition = $list->findTransition(fn($n) => $n->value === self::MEDIUM_DATASET - 1);
        $worstCase = microtime(true) - $startTime;
        $this->assertNotNull($transition->after);
        
        // Caso peor: no encontrar nada (recorrer toda la lista)
        $startTime = microtime(true);
        $transition = $list->findTransition(fn($n) => $n->value === 999999);
        $notFoundCase = microtime(true) - $startTime;
        $this->assertNull($transition->after);
        
        // El mejor caso debe ser más rápido que el peor
        $this->assertLessThan($worstCase, $bestCase);
        
        echo sprintf("\n[PERF] Búsqueda (%d nodos):\n", self::MEDIUM_DATASET);
        echo sprintf("  - Mejor caso (primero):  %.6fs\n", $bestCase);
        echo sprintf("  - Caso promedio (medio): %.6fs\n", $avgCase);
        echo sprintf("  - Peor caso (último):    %.6fs\n", $worstCase);
        echo sprintf("  - No encontrado:         %.6fs\n", $notFoundCase);
    }
    
    /**
     * Test: Rendimiento de recorrido (traversal)
     * Complejidad esperada: O(n)
     */
    public function testTraversalPerformance(): void
    {
        $list = new LinkedList();
        
        // Preparar lista
        for ($i = 0; $i < self::LARGE_DATASET; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list->insertBefore($node, fn($n) => false);
        }
        
        // Recorrer hacia adelante
        $startTime = microtime(true);
        $current = $list->getHead();
        $count = 0;
        while ($current !== null) {
            $count++;
            $current = $current->next;
        }
        $forwardTime = microtime(true) - $startTime;
        
        $this->assertEquals(self::LARGE_DATASET, $count);
        
        // Recorrer hacia atrás desde el último nodo
        $startTime = microtime(true);
        // Primero llegar al final
        $current = $list->getHead();
        while ($current->next !== null) {
            $current = $current->next;
        }
        // Ahora recorrer hacia atrás
        $count = 0;
        while ($current !== null && !($current->isRoot ?? false)) {
            $count++;
            $current = $current->previous;
        }
        $backwardTime = microtime(true) - $startTime;
        
        $this->assertEquals(self::LARGE_DATASET, $count);
        
        // Ambos recorridos deben ser razonablemente rápidos
        $this->assertLessThan(0.1, $forwardTime,
            sprintf("Recorrido hacia adelante lento: %.4fs", $forwardTime));
        $this->assertLessThan(0.2, $backwardTime,
            sprintf("Recorrido hacia atrás lento: %.4fs", $backwardTime));
        
        echo sprintf("\n[PERF] Recorrido (%d nodos):\n", self::LARGE_DATASET);
        echo sprintf("  - Hacia adelante: %.4fs\n", $forwardTime);
        echo sprintf("  - Hacia atrás:    %.4fs\n", $backwardTime);
    }
    
    /**
     * Test: Rendimiento de eliminación (remove)
     * Complejidad esperada: O(1) por eliminación
     */
    public function testRemovalPerformance(): void
    {
        $list = new LinkedList();
        $nodes = [];
        
        // Preparar lista
        for ($i = 0; $i < self::MEDIUM_DATASET; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $nodes[] = $node;
            $list->insertBefore($node, fn($n) => false);
        }
        
        // Remover todos los nodos
        $startTime = microtime(true);
        foreach ($nodes as $node) {
            ($node->remove)();
        }
        $executionTime = microtime(true) - $startTime;
        
        $this->assertTrue($list->isEmpty());
        
        // Debe ser muy rápido (O(1) por operación)
        $this->assertLessThan(0.05, $executionTime,
            sprintf("Eliminación demasiado lenta: %.4fs", $executionTime));
        
        echo sprintf("\n[PERF] Eliminación (%d nodos): %.4fs\n", 
            self::MEDIUM_DATASET, $executionTime);
    }
    
    /**
     * Test: Rendimiento de insertBefore con búsqueda
     * Este es el caso más complejo: O(n) para buscar + O(1) para insertar
     */
    public function testInsertBeforeWithSearchPerformance(): void
    {
        $list = new LinkedList();
        
        // Preparar lista inicial
        for ($i = 0; $i < self::SMALL_DATASET; $i += 10) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list->insertBefore($node, fn($n) => false);
        }
        
        // Insertar nodos en posiciones específicas (requiere búsqueda)
        $startTime = microtime(true);
        for ($i = 1; $i < self::SMALL_DATASET; $i += 10) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            // Insertar antes del primer nodo mayor que este valor
            $list->insertBefore($node, fn($n) => $n->value > $i);
        }
        $executionTime = microtime(true) - $startTime;
        
        // Verificar que están ordenados
        $current = $list->getHead();
        $lastValue = -1;
        while ($current !== null) {
            $this->assertGreaterThan($lastValue, $current->value);
            $lastValue = $current->value;
            $current = $current->next;
        }
        
        echo sprintf("\n[PERF] Insert con búsqueda (%d inserciones): %.4fs\n", 
            self::SMALL_DATASET / 10, $executionTime);
    }
    
    /**
     * Test: Stress test con dataset muy grande
     * @group slow
     */
    public function testStressTestWithLargeDataset(): void
    {
        $list = new LinkedList(); //LinkedList(LinkedList::MODE_STATUS);
	// $list = new LinkedList();
        $nodeCount = 50000;
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Insertar muchos nodos
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list->insertBefore($node, fn($n) => false);
        }
        
        $insertTime = microtime(true) - $startTime;
        
        // Realizar búsquedas aleatorias
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $searchValue = rand(0, $nodeCount - 1);
            $list->findTransition(fn($n) => $n->value === $searchValue);
        }
        $searchTime = microtime(true) - $startTime;
        
        // Recorrer toda la lista
        $startTime = microtime(true);
        $current = $list->getHead();
        $count = 0;
        while ($current !== null) {
            $count++;
            $current = $current->next;
        }
        $traverseTime = microtime(true) - $startTime;
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // MB
        
        $this->assertEquals($nodeCount, $count);
        
        echo sprintf("\n[STRESS] Dataset grande (%d nodos):\n", $nodeCount);
        echo sprintf("  - Inserción:        %.4fs\n", $insertTime);
        echo sprintf("  - 100 búsquedas:    %.4fs\n", $searchTime);
        echo sprintf("  - Recorrido:        %.4fs\n", $traverseTime);
        echo sprintf("  - Memoria usada:    %.2f MB\n", $memoryUsed);
        
        // Límites razonables para 50k nodos
        $this->assertLessThan(50.0, $insertTime, "Inserción muy lenta");
        $this->assertLessThan(2.0, $searchTime, "Búsqueda muy lenta");
        $this->assertLessThan(1.0, $traverseTime, "Recorrido muy lento");
        $this->assertLessThan(100, $memoryUsed, "Uso de memoria excesivo");
    }
    
    /**
     * Test: Comparación de rendimiento entre diferentes estrategias de inserción
     */
    public function testInsertionStrategyComparison(): void
    {
        $nodeCount = self::MEDIUM_DATASET;
        
        // Estrategia 1: Siempre al final
        $list1 = new LinkedList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list1->insertBefore($node, fn($n) => false);
        }
        $timeAtEnd = microtime(true) - $start;
        
        // Estrategia 2: Siempre al inicio
        $list2 = new LinkedList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list2->insertBefore($node, fn($n) => true);
        }
        $timeAtStart = microtime(true) - $start;
        
        // Estrategia 3: Insertado ordenado (peor caso)
        $list3 = new LinkedList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = LinkedList::node(new Node());
            $node->value = $i;
            $list3->insertBefore($node, fn($n) => $n->value > $i);
        }
        $timeOrdered = microtime(true) - $start;
        
        echo sprintf("\n[COMP] Estrategias de inserción (%d nodos):\n", $nodeCount);
        echo sprintf("  - Siempre al final:   %.4fs (base)\n", $timeAtEnd);
        echo sprintf("  - Siempre al inicio:  %.4fs (%.1fx más rápido)\n", 
            $timeAtStart, $timeAtEnd / $timeAtStart);
        echo sprintf("  - Inserción ordenada: %.4fs (%.1fx más lento)\n", 
            $timeOrdered, $timeOrdered / $timeAtEnd);
        
        // Al inicio debe ser más rápido que al final
        $this->assertLessThan($timeAtEnd, $timeAtStart);
        
        // Ordenado debe ser el más lento (requiere búsqueda)
        $this->assertGreaterThan($timeAtEnd, $timeOrdered);
    }
}
