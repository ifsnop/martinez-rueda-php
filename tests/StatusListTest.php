<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests;

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\StatusList;
use Ifsnop\MartinezRueda\Node;
use Ifsnop\MartinezRueda\Transition;
use Ifsnop\MartinzRueda\Algorithm;

/**
 * Performance and correctness tests for StatusList
 */
class StatusListTest extends TestCase
{
    private StatusList $statusList;
    private $sizes = [100, 500, 1000, 2000, 5000, 10000];


    protected function setUp(): void
    {
        $this->statusList = new StatusList();
    }

    /* ================== CORRECTNESS TESTS ================== */

    public function testEmptyListIsEmpty(): void
    {
        $this->assertTrue($this->statusList->isEmpty());
        $this->assertNull($this->statusList->getHead());
    }

    public function testInsertSingleNode(): void
    {
        $node = StatusList::node(new Node(pt: ['x' => 1, 'y' => 1]));
        
        try {
            $this->statusList->insertBefore($node, fn($n) => true);
        } catch (\Throwable $e) {
            $this->fail("Insert failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        
        $this->assertFalse($this->statusList->isEmpty(), 'List should not be empty after insert');
        
        $head = $this->statusList->getHead();
        $this->assertNotNull($head, 'Head should not be null after insert');
        $this->assertSame($node, $head, 'Head should be the inserted node');
        $this->assertTrue($this->statusList->exists($node), 'Node should exist in the list');
    }

    public function testInsertMultipleNodesInOrder(): void
    {
        $nodes = [];
        for ($i = 0; $i < 5; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
        }

        // Insert in ascending order based on x coordinate
        foreach ($nodes as $node) {
            $this->statusList->insertBefore(
                $node,
                fn($n) => $n->pt['x'] > $node->pt['x']
            );
        }

        $head = $this->statusList->getHead();
        $this->assertNotNull($head, 'Head should not be null after insertions');
        $this->assertSame($nodes[0], $head);
        
        // Verify linked list integrity
        $current = $head;
        for ($i = 0; $i < 5; $i++) {
            $this->assertNotNull($current, "Node at position $i should not be null");
            $this->assertSame($nodes[$i], $current, "Node at position $i should match");
            $this->assertTrue($this->statusList->exists($nodes[$i]));
            $current = $current->next;
        }
        $this->assertNull($current);
    }

    public function testInsertReverseOrder(): void
    {
        $nodes = [];
        for ($i = 4; $i >= 0; $i--) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
        }

        // Insert in descending order
        foreach ($nodes as $i => $node) {
            $this->statusList->insertBefore(
                $node,
                fn($n) => $n->pt['x'] > $node->pt['x']
            );
        }

        // Verify order is correct (ascending)
        $current = $this->statusList->getHead();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($nodes[$i], $current);
            $current = $current->next;
        }
    }

    public function testRemoveNode(): void
    {
        $nodes = [];
        for ($i = 0; $i < 3; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            $this->statusList->insertBefore(
                $nodes[$i],
                fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']
            );
        }

        // Remove middle node
        ($nodes[1]->remove)();

        $this->assertFalse($this->statusList->exists($nodes[1]));
        $this->assertTrue($this->statusList->exists($nodes[0]));
        $this->assertTrue($this->statusList->exists($nodes[2]));

        // Verify linked list integrity
        $this->assertSame($nodes[2], $nodes[0]->next);
        $this->assertSame($nodes[0], $nodes[2]->previous);
    }

    public function testRemoveAllNodes(): void
    {
        $nodes = [];
        for ($i = 0; $i < 5; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            $this->statusList->insertBefore(
                $nodes[$i],
                fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']
            );
        }

        foreach ($nodes as $node) {
            ($node->remove)();
        }

        $this->assertTrue($this->statusList->isEmpty());
        foreach ($nodes as $node) {
            $this->assertFalse($this->statusList->exists($node));
        }
    }

    public function testFindTransition(): void
    {
        $nodes = [];
        for ($i = 0; $i < 5; $i += 2) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            $this->statusList->insertBefore(
                $nodes[$i],
                fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']
            );
        }

        // Find transition point where x would be 3
        $transition = $this->statusList->findTransition(fn($n) => $n->pt['x'] > 3);

        $this->assertNotNull($transition, 'Transition should not be null');
        $this->assertSame($nodes[2], $transition->before, 'Before node should be node with x=2');
        $this->assertSame($nodes[4], $transition->after, 'After node should be node with x=4');

        // Test insert via transition
        $newNode = StatusList::node(new Node(pt: ['x' => 3, 'y' => 3]));
        $insertedNode = ($transition->insert)($newNode);
        
        $this->assertNotNull($insertedNode, 'Inserted node should not be null');
        $this->assertTrue($this->statusList->exists($newNode));
        
        // Verify the node is properly linked
        $this->assertNotNull($newNode->previous, 'New node should have a previous pointer');
        $this->assertNotNull($newNode->next, 'New node should have a next pointer');
        $this->assertSame($nodes[2], $newNode->previous);
        $this->assertSame($nodes[4], $newNode->next);
    }

    /* ================== PERFORMANCE BENCHMARKS ================== */

    public function testPerformanceInsertSequential(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            $nodes = [];
            
            for ($i = 0; $i < $size; $i++) {
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            }

            $start = microtime(true);
            
            foreach ($nodes as $node) {
                $list->insertBefore($node, fn($n) => $n->pt['x'] > $node->pt['x']);
            }
            
            $duration = microtime(true) - $start;
            $avgPerInsert = ($duration / $size) * 1000000; // microseconds
            
            echo sprintf(
                "\n[Sequential Insert] Size: %d, Total: %.4fs, Avg: %.2fµs/insert",
                $size,
                $duration,
                $avgPerInsert
            );
            
            $this->assertLessThan(5, $duration, "Sequential insert of $size nodes took too long");
        }
    }

    public function testPerformanceInsertRandom(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            $nodes = [];
            
            // Create nodes with random x coordinates
            for ($i = 0; $i < $size; $i++) {
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => rand(0, $size * 10), 'y' => $i]));
            }

            $start = microtime(true);
            
            foreach ($nodes as $node) {
                $list->insertBefore($node, fn($n) => $n->pt['x'] > $node->pt['x']);
            }
            
            $duration = microtime(true) - $start;
            $avgPerInsert = ($duration / $size) * 1000000; // microseconds
            
            echo sprintf(
                "\n[Random Insert] Size: %d, Total: %.4fs, Avg: %.2fµs/insert",
                $size,
                $duration,
                $avgPerInsert
            );
            
            $this->assertLessThan(10, $duration, "Random insert of $size nodes took too long");
        }
    }

    public function testPerformanceRemove(): void
    {
        $sizes = $this->sizes;

        
        foreach ($sizes as $size) {
            $list = new StatusList();
            $nodes = [];
            
            // Insert nodes
            for ($i = 0; $i < $size; $i++) {
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
                $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']);
            }

            $start = microtime(true);
            
            // Remove all nodes
            foreach ($nodes as $node) {
                ($node->remove)();
            }
            
            $duration = microtime(true) - $start;
            $avgPerRemove = ($duration / $size) * 1000000; // microseconds
            
            echo sprintf(
                "\n[Remove] Size: %d, Total: %.4fs, Avg: %.2fµs/remove",
                $size,
                $duration,
                $avgPerRemove
            );
            
            $this->assertLessThan(10, $duration, "Remove of $size nodes took too long");
        }
    }

    public function testPerformanceMixedOperations(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            $nodes = [];
            
            $start = microtime(true);
            
            // Mixed operations: insert, search, remove
            for ($i = 0; $i < $size; $i++) {
                // Insert
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
                $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']);
                
                // Every 10 inserts, do a findTransition
                if ($i % 10 === 0 && $i > 0) {
                    $list->findTransition(fn($n) => $n->pt['x'] > $i / 2);
                }
                
                // Every 20 inserts, remove an old node
                if ($i % 20 === 0 && $i >= 20) {
                    ($nodes[$i - 20]->remove)();
                }
            }
            
            $duration = microtime(true) - $start;
            
            echo sprintf(
                "\n[Mixed Operations] Size: %d, Total: %.4fs",
                $size,
                $duration
            );
            
            $this->assertLessThan(15, $duration, "Mixed operations for $size items took too long");
        }
    }

    public function testPerformanceFindTransition(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            
            // Build list
            for ($i = 0; $i < $size; $i++) {
                $node = StatusList::node(new Node(pt: ['x' => $i * 2, 'y' => $i]));
                $list->insertBefore($node, fn($n) => $n->pt['x'] > $node->pt['x']);
            }

            $iterations = min($size, 500);
            $start = microtime(true);
            
            // Perform multiple findTransition operations
            for ($i = 0; $i < $iterations; $i++) {
                $searchX = rand(0, $size * 2);
                $list->findTransition(fn($n) => $n->pt['x'] > $searchX);
            }
            
            $duration = microtime(true) - $start;
            $avgPerSearch = ($duration / $iterations) * 1000000; // microseconds
            
            echo sprintf(
                "\n[FindTransition] Size: %d, Searches: %d, Avg: %.2fµs/search",
                $size,
                $iterations,
                $avgPerSearch
            );
            
            $this->assertLessThan(5, $duration, "FindTransition operations took too long");
        }
    }

    public function testMemoryUsage(): void
    {
        $sizes = $this->sizes;

        
        foreach ($sizes as $size) {
            // Force garbage collection to get accurate baseline
            gc_collect_cycles();
            
            $memBefore = memory_get_usage(false); // Use false for actual memory, not allocated chunks
            
            $list = new StatusList();
            $nodes = [];
            
            for ($i = 0; $i < $size; $i++) {
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
                $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']);
            }
            
            $memAfter = memory_get_usage(false);
            $memUsed = $memAfter - $memBefore;
            $perNode = $memUsed / $size;
            
            echo sprintf(
                "\n[Memory] Size: %d, Total: %.2fKB, Per node: %.0f bytes",
                $size,
                $memUsed / 1024,
                $perNode
            );
            
            // Detailed breakdown
            $peakMem = memory_get_peak_usage(false);
            echo sprintf(
                "\n         Peak: %.2fKB, Current: %.2fKB",
                $peakMem / 1024,
                $memAfter / 1024
            );
            
            // Clean up
            unset($list);
            unset($nodes);
            gc_collect_cycles();
        }
        
        $this->assertTrue(true);
    }

    /**
     * More detailed memory profiling with step-by-step tracking
     */
    public function testDetailedMemoryProfile(): void
    {
        gc_collect_cycles();
        
        $size = 1000;
        $checkpoints = [0, 100, 250, 500, 750, 1000];
        
        echo "\n[Detailed Memory Profile]";
        
        $memStart = memory_get_usage(false);
        $list = new StatusList();
        $memAfterList = memory_get_usage(false);
        
        echo sprintf(
            "\n  Empty StatusList: %.2fKB",
            ($memAfterList - $memStart) / 1024
        );
        
        $nodes = [];
        $lastMem = $memAfterList;
        
        for ($i = 0; $i < $size; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']);
            
            if (in_array($i + 1, $checkpoints)) {
                $currentMem = memory_get_usage(false);
                $deltaMem = $currentMem - $lastMem;
                $totalMem = $currentMem - $memStart;
                $avgPerNode = $totalMem / ($i + 1);
                
                echo sprintf(
                    "\n  After %4d inserts: Total: %6.2fKB, Delta: %6.2fKB, Avg/node: %6.0f bytes",
                    $i + 1,
                    $totalMem / 1024,
                    $deltaMem / 1024,
                    $avgPerNode
                );
                
                $lastMem = $currentMem;
            }
        }
        
        // Test memory impact of indexOf/exists map
        $reflector = new \ReflectionClass($list);
        
        // Try to access private properties for detailed analysis
        try {
            $arrProp = $reflector->getProperty('arr');
            $arrProp->setAccessible(true);
            $arr = $arrProp->getValue($list);
            
            echo sprintf(
                "\n  Internal array size: %d elements",
                count($arr)
            );
            
            // Check if indexOf or exists property exists
            if ($reflector->hasProperty('indexOf')) {
                $indexProp = $reflector->getProperty('indexOf');
                $indexProp->setAccessible(true);
                $indexOf = $indexProp->getValue($list);
                echo sprintf(
                    "\n  indexOf map size: %d entries (%.2fKB)",
                    count($indexOf),
                    (count($indexOf) * 16) / 1024 // rough estimate: key+value overhead
                );
            }
            
            if ($reflector->hasProperty('exists')) {
                $existsProp = $reflector->getProperty('exists');
                $existsProp->setAccessible(true);
                $exists = $existsProp->getValue($list);
                echo sprintf(
                    "\n  exists map size: %d entries (%.2fKB)",
                    count($exists),
                    (count($exists) * 12) / 1024 // rough estimate: key+bool overhead
                );
            }
            
        } catch (\ReflectionException $e) {
            echo "\n  (Could not access internal properties)";
        }
        
        $this->assertTrue(true);
    }

    /* ================== STRESS TESTS ================== */

    public function testStressLargeDataset(): void
    {
        $this->markTestSkipped('Enable manually for stress testing');
        
        $size = 10000;
        $list = new StatusList();
        $nodes = [];
        
        echo "\n[Stress Test] Inserting $size nodes...\n";
        $start = microtime(true);
        
        for ($i = 0; $i < $size; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => rand(0, $size * 10), 'y' => $i]));
            $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $nodes[$i]->pt['x']);
            
            if ($i % 1000 === 0) {
                echo "Inserted $i nodes...\n";
            }
        }
        
        $insertDuration = microtime(true) - $start;
        echo sprintf("Insert completed in %.4fs\n", $insertDuration);
        
        echo "Removing all nodes...\n";
        $start = microtime(true);
        
        foreach ($nodes as $node) {
            ($node->remove)();
        }
        
        $removeDuration = microtime(true) - $start;
        echo sprintf("Remove completed in %.4fs\n", $removeDuration);
        
        $this->assertTrue($list->isEmpty());
    }

    /* ================== REALISTIC SCENARIO TESTS ================== */

    /**
     * Simulates the event processing loop pattern:
     * - Process events from eventRoot
     * - Insert/remove from statusList based on sweep line
     * - Query transitions frequently
     */
    public function testRealisticEventProcessingPattern(): void
    {
        $sizes = $this->sizes;

        
        foreach ($sizes as $eventCount) {
            $statusList = new StatusList();
            $activeSegments = [];
            
            echo sprintf("\n[Event Processing] Simulating %d events", $eventCount);
            
            $start = microtime(true);
            
            for ($sweepY = 0; $sweepY < $eventCount; $sweepY++) {
                // Simulate processing an event at sweep line position
                $eventType = rand(0, 2);
                
                if ($eventType === 0 || count($activeSegments) < 5) {
                    // Start event - insert new segment into status
                    $x = rand(0, 1000);
                    $node = StatusList::node(new Node(
                        isStart: true,
                        pt: ['x' => $x, 'y' => $sweepY],
                        seg: ['id' => $sweepY]
                    ));
                    
                    $statusList->insertBefore($node, fn($n) => $n->pt['x'] > $x);
                    $activeSegments[] = $node;
                    
                } elseif ($eventType === 1 && count($activeSegments) > 0) {
                    // End event - remove segment from status
                    $idx = array_rand($activeSegments);
                    $node = $activeSegments[$idx];
                    ($node->remove)();
                    array_splice($activeSegments, $idx, 1);
                    
                } else {
                    // Intersection event - find transitions (above/below)
                    if (count($activeSegments) > 0) {
                        $searchX = rand(0, 1000);
                        $transition = $statusList->findTransition(fn($n) => $n->pt['x'] > $searchX);
                        
                        // Simulate checking both intersections
                        $above = $transition->after;
                        $below = $transition->before;
                    }
                }
                
                // Every 50 events, do some cleanup
                if ($sweepY % 50 === 0 && count($activeSegments) > 10) {
                    for ($i = 0; $i < 5; $i++) {
                        if (count($activeSegments) > 0) {
                            $idx = array_rand($activeSegments);
                            ($activeSegments[$idx]->remove)();
                            array_splice($activeSegments, $idx, 1);
                        }
                    }
                }
            }
            
            // Cleanup remaining
            foreach ($activeSegments as $node) {
                ($node->remove)();
            }
            
            $duration = microtime(true) - $start;
            
            echo sprintf(
                " - Total: %.4fs, Avg: %.2fµs/event\n",
                $duration,
                ($duration / $eventCount) * 1000000
            );
            
            $this->assertTrue($statusList->isEmpty());
            $this->assertLessThan(20, $duration, "Event processing simulation took too long");
        }
    }

    /**
     * Test worst-case scenario: repeatedly inserting at the beginning
     */
    public function testWorstCaseInsertAtBeginning(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            
            $start = microtime(true);
            
            // Insert in descending order (always at beginning)
            for ($i = $size - 1; $i >= 0; $i--) {
                $node = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
                $list->insertBefore($node, fn($n) => true); // Always insert at beginning
            }
            
            $duration = microtime(true) - $start;
            
            echo sprintf(
                "\n[Worst Case Insert] Size: %d, Total: %.4fs, Avg: %.2fµs/insert",
                $size,
                $duration,
                ($duration / $size) * 1000000
            );
            
            $this->assertLessThan(10, $duration, "Worst case insert took too long");
        }
    }

    /**
     * Test worst-case removal: removing from the beginning repeatedly
     */
    public function testWorstCaseRemoveFromBeginning(): void
    {
        $sizes = $this->sizes;
        
        foreach ($sizes as $size) {
            $list = new StatusList();
            $nodes = [];
            
            // Build list
            for ($i = 0; $i < $size; $i++) {
                $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
                $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $i);
            }
            
            $start = microtime(true);
            
            // Remove from beginning (worst case for array operations)
            for ($i = 0; $i < $size; $i++) {
                ($nodes[$i]->remove)();
            }
            
            $duration = microtime(true) - $start;
            
            echo sprintf(
                "\n[Worst Case Remove] Size: %d, Total: %.4fs, Avg: %.2fµs/remove",
                $size,
                $duration,
                ($duration / $size) * 1000000
            );
            
            $this->assertTrue($list->isEmpty());
            $this->assertLessThan(15, $duration, "Worst case remove took too long");
        }
    }

    /**
     * Test the pattern of maintaining a sweep line status
     */
    public function testSweepLinePattern(): void
    {
        $statusList = new StatusList();
        $sweepEvents = 200;
        
        $start = microtime(true);
        
        for ($y = 0; $y < $sweepEvents; $y++) {
            // At each sweep position, we might:
            // 1. Add new segments that start here
            $newSegments = rand(0, 3);
            $addedNodes = [];
            
            for ($i = 0; $i < $newSegments; $i++) {
                $x = rand(0, 1000);
                $node = StatusList::node(new Node(
                    pt: ['x' => $x, 'y' => $y],
                    isStart: true
                ));
                $statusList->insertBefore($node, fn($n) => $n->pt['x'] > $x);
                $addedNodes[] = $node;
            }
            
            // 2. Query for intersections (find transitions)
            if (!$statusList->isEmpty()) {
                $queries = rand(1, 3);
                for ($i = 0; $i < $queries; $i++) {
                    $searchX = rand(0, 1000);
                    $transition = $statusList->findTransition(fn($n) => $n->pt['x'] > $searchX);
                    
                    // Check neighbors for intersection
                    if ($transition->before !== null && $transition->after !== null) {
                        // This is where checkBothIntersections would happen
                    }
                }
            }
            
            // 3. Remove segments that end here
            foreach ($addedNodes as $node) {
                if (rand(0, 1) === 1) { // 50% chance to remove
                    ($node->remove)();
                }
            }
        }
        
        $duration = microtime(true) - $start;
        
        echo sprintf(
            "\n[Sweep Line Pattern] Events: %d, Total: %.4fs",
            $sweepEvents,
            $duration
        );
        
        $this->assertLessThan(10, $duration, "Sweep line pattern took too long");
    }

    /**
     * Test concurrent existence checks during operations
     */
    public function testExistsPerformance(): void
    {
        $size = 1000;
        $list = new StatusList();
        $nodes = [];
        
        // Build list
        for ($i = 0; $i < $size; $i++) {
            $nodes[$i] = StatusList::node(new Node(pt: ['x' => $i, 'y' => $i]));
            $list->insertBefore($nodes[$i], fn($n) => $n->pt['x'] > $i);
        }
        
        $checks = 10000;
        $start = microtime(true);
        
        // Perform many existence checks
        for ($i = 0; $i < $checks; $i++) {
            $node = $nodes[rand(0, $size - 1)];
            $list->exists($node);
        }
        
        $duration = microtime(true) - $start;
        
        echo sprintf(
            "\n[Exists Check] Checks: %d, Total: %.4fs, Avg: %.2fµs/check",
            $checks,
            $duration,
            ($duration / $checks) * 1000000
        );
        
        $this->assertLessThan(1, $duration, "Exists checks took too long");
    }
}
