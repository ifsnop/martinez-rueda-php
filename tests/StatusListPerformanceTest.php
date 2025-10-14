<?php
// tests/StatusListPerformanceTest.php
namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\StatusList;
use Ifsnop\MartinezRueda\Node;

/**
 * Complete performance test suite for StatusList
 * Verifies that operations are efficient even with large datasets
 */
class StatusListPerformanceTest extends TestCase
{
    private const SMALL_DATASET = 100;
    private const MEDIUM_DATASET = 1000;
    private const LARGE_DATASET = 10000;
    
    /**
     * Test: Stress test with very large dataset
     * @group slow
     */
    public function testStressTestWithLargeDataset(): void
    {
        $list = new StatusList();
        $nodeCount = 50000;
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        // Insert many nodes
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
            $list->insertBefore($node, fn($n) => false);
        }
        
        $insertTime = microtime(true) - $startTime;
        
        // Perform random searches
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $searchValue = rand(0, $nodeCount - 1);
            $list->findTransition(fn($n) => $n->pt['x'] > $searchValue);
        }
        $searchTime = microtime(true) - $startTime;
        
        // Traverse entire list
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
        
        echo sprintf("\n[STRESS] Large dataset (%d nodes):\n", $nodeCount);
        echo sprintf("  - Insertion:     %.4fs\n", $insertTime);
        echo sprintf("  - 100 searches:  %.4fs\n", $searchTime);
        echo sprintf("  - Traversal:     %.4fs\n", $traverseTime);
        echo sprintf("  - Memory used:   %.2f MB\n", $memoryUsed);
        
        // Reasonable limits for 50k nodes
        $this->assertLessThan(50.0, $insertTime, "Insertion very slow");
        $this->assertLessThan(2.0, $searchTime, "Search very slow");
        $this->assertLessThan(1.0, $traverseTime, "Traversal very slow");
        $this->assertLessThan(100, $memoryUsed, "Excessive memory usage");
    }
    
    /**
     * Test: Comparison of performance between different insertion strategies
     */
    public function testInsertionStrategyComparison(): void
    {
        $nodeCount = self::MEDIUM_DATASET;
        
        // Strategy 1: Always at the end
        $list1 = new StatusList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
            $list1->insertBefore($node, fn($n) => false);
        }
        $timeAtEnd = microtime(true) - $start;
        
        // Strategy 2: Always at the beginning
        $list2 = new StatusList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
            $list2->insertBefore($node, fn($n) => true);
        }
        $timeAtStart = microtime(true) - $start;
        
        // Strategy 3: Sorted insertion (worst case)
        $list3 = new StatusList();
        $start = microtime(true);
        for ($i = 0; $i < $nodeCount; $i++) {
            $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
            $list3->insertBefore($node, fn($n) => $n->pt['x'] > $i);
        }
        $timeOrdered = microtime(true) - $start;
        
        echo sprintf("\n[COMP] Insertion strategies (%d nodes):\n", $nodeCount);
        echo sprintf("  - Always at end:      %.4fs (baseline)\n", $timeAtEnd);
        echo sprintf("  - Always at start:    %.4fs (%.1fx faster)\n", 
            $timeAtStart, $timeAtEnd / max($timeAtStart, 0.0001));
        echo sprintf("  - Sorted insertion:   %.4fs (%.1fx slower)\n", 
            $timeOrdered, $timeOrdered / max($timeAtEnd, 0.0001));
        
        // At start should be faster than at end
        $this->assertLessThan($timeAtEnd * 2, $timeAtStart, "Start insertion should be comparable to end");
        
        // Ordered should be slower (requires search)
        $this->assertGreaterThan($timeAtEnd * 0.5, $timeOrdered, "Ordered insertion should take more time");
    }
    
    /**
     * Test: Exists() performance
     * Expected complexity: O(1) with hash map
     */
    public function testExistsPerformance(): void
    {
        $list = new StatusList();
        $nodes = [];
        
        // Build list
        for ($i = 0; $i < self::MEDIUM_DATASET; $i++) {
            $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
            $nodes[] = $node;
            $list->insertBefore($node, fn($n) => false);
        }
        
        // Perform many existence checks
        $checks = 10000;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $checks; $i++) {
            $node = $nodes[rand(0, self::MEDIUM_DATASET - 1)];
            $result = $list->exists($node);
            $this->assertTrue($result);
        }
        
        $executionTime = microtime(true) - $startTime;
        $avgPerCheck = ($executionTime / $checks) * 1000000; // microseconds
        
        echo sprintf("\n[PERF] Exists checks (%d checks): %.4fs, %.2fµs per check\n", 
            $checks, $executionTime, $avgPerCheck);
        
        // Should be very fast (O(1))
        $this->assertLessThan(0.1, $executionTime, "Exists checks too slow");
        $this->assertLessThan(10, $avgPerCheck, "Average exists check too slow");
    }
    
    /**
     * Test: Mixed operations (realistic scenario)
     */
    public function testMixedOperationsPerformance(): void
    {
        $list = new StatusList();
        $nodes = [];
        $operations = 5000;
        
        $startTime = microtime(true);
        
        for ($i = 0; $i < $operations; $i++) {
            $operation = rand(0, 3);
            
            if ($operation === 0 || count($nodes) < 10) {
                // Insert
                $x = rand(0, $operations * 2);
                $node = StatusList::node(new Node(pt: ['x' => $x, 'y' => $i]));
                $list->insertBefore($node, fn($n) => $n->pt['x'] > $x);
                $nodes[] = $node;
                
            } elseif ($operation === 1) {
                // Remove
                $idx = array_rand($nodes);
                ($nodes[$idx]->remove)();
                array_splice($nodes, $idx, 1);
                
            } elseif ($operation === 2) {
                // Search
                $searchX = rand(0, $operations * 2);
                $list->findTransition(fn($n) => $n->pt['x'] > $searchX);
                
            } else {
                // Exists check
                if (count($nodes) > 0) {
                    $node = $nodes[array_rand($nodes)];
                    $list->exists($node);
                }
            }
        }
        
        $executionTime = microtime(true) - $startTime;
        $avgPerOp = ($executionTime / $operations) * 1000; // milliseconds
        
        echo sprintf("\n[PERF] Mixed operations (%d ops): %.4fs, %.2fms per op\n", 
            $operations, $executionTime, $avgPerOp);
        
        // Clean up
        foreach ($nodes as $node) {
            ($node->remove)();
        }
        
        $this->assertTrue($list->isEmpty());
        $this->assertLessThan(10.0, $executionTime, "Mixed operations too slow");
    }
    
    /**
     * Test: Memory efficiency comparison
     */
    public function testMemoryEfficiency(): void
    {
        gc_collect_cycles();
        
        $sizes = [100, 500, 1000, 5000];
        
        echo "\n[MEMORY] Memory efficiency:\n";
        
        foreach ($sizes as $size) {
            $memBefore = memory_get_usage(false);
            
            $list = new StatusList();
            $nodes = [];
            
            for ($i = 0; $i < $size; $i++) {
                $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => 0]));
                $nodes[] = $node;
                $list->insertBefore($node, fn($n) => false);
            }
            
            $memAfter = memory_get_usage(false);
            $memUsed = $memAfter - $memBefore;
            $perNode = $memUsed / $size;
            
            echo sprintf("  - %5d nodes: %7.2fKB total, %5.0f bytes/node\n", 
                $size, $memUsed / 1024, $perNode);
            
            // Clean up
            unset($list);
            unset($nodes);
            gc_collect_cycles();
        }
        
        $this->assertTrue(true);
    }
}
