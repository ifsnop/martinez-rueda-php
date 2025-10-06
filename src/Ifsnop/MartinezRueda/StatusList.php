<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class StatusList
{
    /** @var Node[] */
    private array $arr = [];
    /** @var array<int,int> spl_object_id(Node)=>index */
    private array $indexOf = [];
    private Node $root;

    public function __construct()
    {
        $this->root = new Node(isRoot: true);
    }

    public function exists(?Node $node): bool
    {
        if ($node === null || $node === $this->root) return false;
        return isset($this->indexOf[\spl_object_id($node)]);
    }

    public function isEmpty(): bool { return \count($this->arr) === 0; }

    public function getHead(): ?Node { return $this->arr[0] ?? null; }

    public function insertBefore(Node $node, callable $check): void
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        $pos = $this->binSearchFirstTrue($check);
        $this->arrayInsertAt($pos, $node);
        // remove especializado (array)
        $node->remove = function () use ($node) { $this->arrayRemove($node); };
    }

    public function findTransition(callable $check): Transition
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        $pos    = $this->binSearchFirstTrue($check);
        $before = ($pos - 1) >= 0                 ? $this->arr[$pos - 1] : null;
        $after  =  $pos      < \count($this->arr) ? $this->arr[$pos]     : null;

        $insertFunc = function (Node $node) use ($pos) {
            $this->arrayInsertAt($pos, $node);
            $node->remove = function () use ($node) { $this->arrayRemove($node); };
            return $node;
        };
        return new Transition(before: $before, after: $after, insert: $insertFunc);
    }

    public static function node(Node $data): Node
    {
        if (Algorithm::DEBUG) print __METHOD__ . PHP_EOL;

        $data->previous = null;
        $data->next     = null;
        // remove seguro por defecto (no-op si no insertado); se sobrescribe al insertar
        $data->remove = function () use ($data) {
            if ($data->previous === null && $data->next === null) return;
            $prev = $data->previous; $next = $data->next;
            if ($prev !== null) $prev->next = $next;
            if ($next !== null) $next->previous = $prev;
            $data->previous = null; $data->next = null;
        };
        return $data;
    }

    /* ================== helpers internos ================== */

    private function binSearchFirstTrue(callable $check): int
    {
        $lo = 0; $hi = \count($this->arr);
        while ($lo < $hi) {
            $mid  = ($lo + $hi) >> 1;
            $here = $this->arr[$mid] ?? null;
            if ($here !== null && $check($here)) $hi = $mid; else $lo = $mid + 1;
        }
        return $lo;
    }

    private function arrayInsertAt(int $pos, Node $node): void
    {
        \array_splice($this->arr, $pos, 0, [$node]);
        // reindex
        $n = \count($this->arr);
        for ($i = $pos; $i < $n; $i++) {
            $this->indexOf[\spl_object_id($this->arr[$i])] = $i;
        }
        // punteros
        $prev = ($pos > 0)            ? $this->arr[$pos - 1] : $this->root;
        $next = ($pos + 1 <= $n - 1)  ? $this->arr[$pos + 1] : null;

        $node->previous = $prev;
        $node->next     = $next;

        if ($prev === $this->root) { $this->root->next = $node; }
        else { $prev->next = $node; }

        if ($next !== null) { $next->previous = $node; }
    }

    private function arrayRemove(Node $node): void
    {
        $oid = \spl_object_id($node);
        if (!isset($this->indexOf[$oid])) return;

        $i = $this->indexOf[$oid];
        $prev = ($i > 0) ? $this->arr[$i - 1] : $this->root;
        $next = ($i + 1 < \count($this->arr)) ? $this->arr[$i + 1] : null;

        // enlazar vecinos
        if ($prev === $this->root) { $this->root->next = $next; }
        else { $prev->next = $next; }
        if ($next !== null) { $next->previous = $prev; }

        // quitar del array + reindex
        \array_splice($this->arr, $i, 1);
        unset($this->indexOf[$oid]);
        $n = \count($this->arr);
        for (; $i < $n; $i++) {
            $this->indexOf[\spl_object_id($this->arr[$i])] = $i;
        }

        // limpiar punteros del eliminado
        $node->previous = null;
        $node->next     = null;
    }
}
