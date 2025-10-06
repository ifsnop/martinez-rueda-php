<?php
namespace Ifsnop\MartinezRueda;

class LinkedList {
    /* ===== NUEVO: modos ===== */
    public const MODE_EVENTS = 0; // impl. enlazada (actual)
    public const MODE_STATUS = 1; // impl. array + búsqueda binaria

    private $mode;
    private $root;

    /* ===== Backend array (solo modo STATUS) ===== */
    /** @var ?Node[] */          private $arr    = null;   // nodos en orden
    /** @var ?array<int,int> */  private $indexOf = null;  // spl_object_id(Node) => idx

    public function __construct(int $mode = self::MODE_EVENTS) {
        $this->mode = $mode;
        $this->root = new Node(isRoot: true);

        if ($this->mode === self::MODE_STATUS) {
            $this->arr     = [];
            $this->indexOf = [];
        }
    }

    public function exists(?Node $node) {
        if ($node === null || $node === $this->root) return false;
        if ($this->mode === self::MODE_EVENTS) {
            // return true; // si nos pasan un Node enlazado distinto de root, existe
	    return $node !== $this->root;
        }
        // STATUS (array)
        return isset($this->indexOf[\spl_object_id($node)]);
    }

    public function isEmpty() {
        if ($this->mode === self::MODE_EVENTS) {
            return $this->root->next === null;
        }
        // STATUS
        return \count($this->arr) === 0;
    }

    public function getHead() {
        if ($this->mode === self::MODE_EVENTS) {
            return $this->root->next;
        }
        // STATUS
        return $this->arr[0] ?? null;
    }

    public function insertBefore(Node $node, callable $check) {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        if ($this->mode === self::MODE_EVENTS) {
            // === Impl. original (lista enlazada) ===
            $last = $this->root;
            $here = $this->root->next;

            while ($here !== null) {
                if ($check($here)) {
                    $node->previous = $here->previous;
                    $node->next     = $here;
                    $here->previous->next = $node;
                    $here->previous       = $node;
                    return;
                }
                $last = $here;
                $here = $here->next;
            }
            $last->next     = $node;
            $node->previous = $last;
            $node->next     = null;
            return;
        }

        // === STATUS (array + binaria) ===
        $pos = $this->binSearchFirstTrue($check); // primera posición donde $check($here) == true
        $this->arrayInsertAt($pos, $node);        // ajusta prev/next y root->next
        // definir un remove coherente con el backend array
        $node->remove = function () use ($node) { $this->arrayRemove($node); };
    }

    public function findTransition(callable $check) {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        if ($this->mode === self::MODE_EVENTS) {
            // === Impl. original (lista enlazada) ===
            $previous = $this->root;
            $here     = $this->root->next;

            while ($here !== null) {
                if ($check($here)) break;
                $previous = $here;
                $here     = $here->next;
            }

            $insertFunc = function(Node $node) use ($previous, $here) {
                $node->previous = $previous;
                $node->next     = $here;
                $previous->next = $node;
                if ($here !== null) $here->previous = $node;
                return $node;
            };

            return new Transition(
                before: $previous === $this->root ? null : $previous,
                after:  $here,
                insert: $insertFunc
            );
        }

        // === STATUS (array + binaria) ===
        $pos    = $this->binSearchFirstTrue($check);
        $before = ($pos - 1) >= 0                 ? $this->arr[$pos - 1] : null;
        $after  =  $pos      < \count($this->arr) ? $this->arr[$pos]     : null;

        $insertFunc = function(Node $node) use ($pos) {
            $this->arrayInsertAt($pos, $node);
            // override remove para backend array
            $node->remove = function () use ($node) { $this->arrayRemove($node); };
            return $node;
        };

        return new Transition(before: $before, after: $after, insert: $insertFunc);
    }

    public static function node(Node $data) {
        if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
        $data->previous = null;
        $data->next     = null;

        // remove por defecto (punteros); en modo STATUS lo sobrescribimos al insertar
        $removeFunc = function() use ($data) {
	    if ( $data->previous === null && $data->next === null ) {
		return;
	    }


	    $prev = $data->previous;
	    $next = $data->next;
	    if ($prev !== null) { $prev->next = $next; }
	    if ($next !== null) { $next->previous = $prev; }
	    $data->previous = null;
	    $data->next     = null;

        };

        $data->remove = $removeFunc;
        return $data;
    }

    /* ===================== Helpers modo STATUS ===================== */

    private function binSearchFirstTrue(callable $check): int {
        // Hallar primer índice i donde $check($arr[i]) === true (monótono F...F T...T)
        $lo = 0; $hi = \count($this->arr);
        while ($lo < $hi) {
            $mid  = ($lo + $hi) >> 1;
            $here = $this->arr[$mid] ?? null;
            if ($here !== null && $check($here)) {
                $hi = $mid;
            } else {
                $lo = $mid + 1;
            }
        }
        return $lo;
    }

    private function arrayInsertAt(int $pos, Node $node): void {
        \array_splice($this->arr, $pos, 0, [$node]);
        // Reindexar a partir de $pos
        $n = \count($this->arr);
        for ($i = $pos; $i < $n; $i++) {
            $this->indexOf[\spl_object_id($this->arr[$i])] = $i;
        }

        // Ajuste de punteros previous/next
        $prev         = ($pos > 0)      ? $this->arr[$pos - 1] : $this->root;
        $next         = ($pos + 1 <= $n - 1) ? $this->arr[$pos + 1] : null;

        $node->previous = $prev;
        $node->next     = $next;

        if ($prev === $this->root) {
            $this->root->next = $node;
        } else {
            $prev->next = $node;
        }
        if ($next !== null) {
            $next->previous = $node;
        }
    }

    private function arrayRemove(Node $node): void {
        $oid = \spl_object_id($node);
        if (!isset($this->indexOf[$oid])) return;

        $i    = $this->indexOf[$oid];
        $prev = ($i > 0) ? $this->arr[$i - 1] : $this->root;
        $next = ($i + 1 < \count($this->arr)) ? $this->arr[$i + 1] : null;

        // Unir vecinos
        if ($prev === $this->root) {
            $this->root->next = $next;
        } else {
            $prev->next = $next;
        }
        if ($next !== null) {
            $next->previous = $prev;
        }

        // Quitar del array y reindexar
        \array_splice($this->arr, $i, 1);
        unset($this->indexOf[$oid]);
        $n = \count($this->arr);
        for (; $i < $n; $i++) {
            $this->indexOf[\spl_object_id($this->arr[$i])] = $i;
        }

        // Limpiar punteros del eliminado
        $node->previous = null;
        $node->next     = null;
    }
}

