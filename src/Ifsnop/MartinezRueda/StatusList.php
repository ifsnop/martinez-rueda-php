<?php
declare(strict_types=1);
namespace Ifsnop\MartinezRueda;

final class StatusList
{
    /** @var Node[] */
    private array $arr = [];
    
    /** @var array<int,true> spl_object_id(Node)=>true (existence flag only) */
    private array $exists = [];
    
    private Node $root;
    
    public function __construct()
    {
        $this->root = new Node(isRoot: true);
    }
    
    public function exists(?Node $node): bool
    {
        if ($node === null || $node === $this->root) return false;
        return isset($this->exists[\spl_object_id($node)]);
    }
    
    public function isEmpty(): bool 
    { 
        return \count($this->arr) === 0; 
    }
    
    public function getHead(): ?Node 
    { 
        return $this->arr[0] ?? null; 
    }
    
    public function insertBefore(Node $node, callable $check): void
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;
        
        $pos = $this->binSearchFirstTrue($check);
        $this->arrayInsertAt($pos, $node);
        
        // Specialized remove closure for array-based removal
        $node->remove = function () use ($node) { 
            $this->arrayRemove($node); 
        };
    }
    
    public function findTransition(callable $check): Transition
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        $pos = $this->binSearchFirstTrue($check);
        $count = \count($this->arr);
        $before = ($pos > 0) ? $this->arr[$pos - 1] : null;
        $after = ($pos < $count) ? $this->arr[$pos] : null;

	// Devuelves un insert que captura el índice $pos en el momento de buscar.
	// Si alguien modifica el status entre findTransition() e insert(...), insertas donde no toca
	// porque realmente no estás insertando, sino generando una función que inserta.
	// esto obliga a buscar dos veces
        //$insertFunc = function (Node $node) use ($pos) {
        //    $this->arrayInsertAt($pos, $node);
        //    $node->remove = function () use ($node) { $this->arrayRemove($node); };
        //    return $node;
        //};

	$insertFunc = function(Node $node) use ($check) {
	    // Recalcular por si el status cambió:
	    $posNow = $this->binSearchFirstTrue($check);
	    $this->arrayInsertAt($posNow, $node);
	    $node->remove = function() use ($node) { $this->arrayRemove($node); };
	    return $node;
	};

        return new Transition(before: $before, after: $after, insert: $insertFunc);
    }

    public static function node(Node $data): Node
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;
        
        $data->previous = null;
        $data->next = null;
        
        // Safe default remove (no-op if not inserted)
        $data->remove = function () use ($data) {
            if ($data->previous === null && $data->next === null) return;
            
            $prev = $data->previous;
            $next = $data->next;
            
            if ($prev !== null) $prev->next = $next;
            if ($next !== null) $next->previous = $prev;
            
            $data->previous = null;
            $data->next = null;
        };
        
        return $data;
    }
    
    /* ================== Internal Helpers ================== */
    
    private function binSearchFirstTrue(callable $check): int
    {
        $lo = 0;
        $hi = \count($this->arr);
        
        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;
            
            // Safety check - should never happen but prevents null errors
            if (!isset($this->arr[$mid])) {
                $lo = $mid + 1;
                continue;
            }
            
            $here = $this->arr[$mid];
            
            if ($check($here)) {
                $hi = $mid;
            } else {
                $lo = $mid + 1;
            }
        }
        
        return $lo;
    }
    
    /**
     * Optimized insert - no full reindexing
     */
    private function arrayInsertAt(int $pos, Node $node): void
    {
        // Insert into array
        \array_splice($this->arr, $pos, 0, [$node]);
        
        // Mark as existing (O(1))
        $this->exists[\spl_object_id($node)] = true;
        
        // Update pointers
        $count = \count($this->arr);
        $prev = ($pos > 0) ? $this->arr[$pos - 1] : $this->root;
        $next = ($pos + 1 <= $count - 1) ? $this->arr[$pos + 1] : null;
        
        $node->previous = $prev;
        $node->next = $next;
        
        if ($prev === $this->root) {
            $this->root->next = $node;
        } else {
            $prev->next = $node;
        }
        
        if ($next !== null) {
            $next->previous = $node;
        }
    }
    
    /**
     * Optimized remove - lazy search instead of maintaining indices
     */
    private function arrayRemove(Node $node): void
    {
        $oid = \spl_object_id($node);
        
        if (!isset($this->exists[$oid])) return;
        
        // Find the node's position (only when removing)
        $i = $this->findNodeIndex($node);
        if ($i === -1) return;
        
        $count = \count($this->arr);
        $prev = ($i > 0) ? $this->arr[$i - 1] : $this->root;
        $next = ($i + 1 < $count) ? $this->arr[$i + 1] : null;
        
        // Link neighbors
        if ($prev === $this->root) {
            $this->root->next = $next;
        } else {
            $prev->next = $next;
        }
        
        if ($next !== null) {
            $next->previous = $prev;
        }
        
        // Remove from array and existence map
        \array_splice($this->arr, $i, 1);
        unset($this->exists[$oid]);
        
        // Clean up removed node pointers
        $node->previous = null;
        $node->next = null;
    }
    
    /**
     * Find node index using object identity
     * Only called during removal (infrequent operation)
     */
    private function findNodeIndex(Node $node): int
    {
        $count = \count($this->arr);
        for ($i = 0; $i < $count; $i++) {
            if ($this->arr[$i] === $node) {
                return $i;
            }
        }
        return -1;
    }
}
