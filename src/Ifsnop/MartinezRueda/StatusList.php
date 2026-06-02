<?php
declare(strict_types=1);
namespace Ifsnop\MartinezRueda;

final class StatusList
{
    /** @var Node[] */
    private array $arr    = [];
    /** @var array<int,true> */
    private array $exists = [];
    private Node  $root;

    public function __construct() { $this->root = new Node(isRoot: true); }

    public function exists(?Node $node): bool
    {
        if ($node === null || $node === $this->root) return false;
        return isset($this->exists[\spl_object_id($node)]);
    }

    public static function node(Node $data): Node
    {
        $data->previous = null;
        $data->next     = null;
        $data->remove   = static function () use ($data) {
            if ($data->previous === null && $data->next === null) return;
            $prev = $data->previous;
            $next = $data->next;
            if ($prev !== null) $prev->next = $next;
            if ($next !== null) $next->previous = $prev;
            $data->previous = $data->next = null;
        };
        return $data;
    }

    public function findTransition(Node $ev): Transition
    {
        $pos    = $this->binSearchPos($ev);
        $count  = \count($this->arr);
        $before = $pos > 0      ? $this->arr[$pos - 1] : null;
        $after  = $pos < $count ? $this->arr[$pos]     : null;

        $insert = function (Node $node) use ($ev): Node {
            $this->arrayInsertAt($this->binSearchPos($ev), $node);
            $node->remove = function () use ($node) { $this->arrayRemove($node); };
            return $node;
        };

        return new Transition(after: $after, before: $before, insert: $insert);
    }

    /** Primer índice donde statusCompare($ev, arr[i]->ev) > 0 (inlinado). */
    private function binSearchPos(Node $ev): int
    {
        $lo = 0;
        $hi = \count($this->arr);
        $a1 = $ev->seg->start;
        $a2 = $ev->seg->end;

        while ($lo < $hi) {
            $mid = ($lo + $hi) >> 1;
            $e2  = $this->arr[$mid]->ev;
            $b1  = $e2->seg->start;
            $b2  = $e2->seg->end;

            if (Point::collinear($a1, $b1, $b2)) {
                $check = !Point::collinear($a2, $b1, $b2) && Point::pointAboveOrOnLine($a2, $b1, $b2);
            } else {
                $check = Point::pointAboveOrOnLine($a1, $b1, $b2);
            }

            if ($check) { $hi = $mid; } else { $lo = $mid + 1; }
        }

        return $lo;
    }

    private function arrayInsertAt(int $pos, Node $node): void
    {
        \array_splice($this->arr, $pos, 0, [$node]);
        $this->exists[\spl_object_id($node)] = true;
        $count = \count($this->arr);
        $prev  = $pos > 0          ? $this->arr[$pos - 1] : $this->root;
        $next  = $pos + 1 < $count ? $this->arr[$pos + 1] : null;
        $node->previous = $prev;
        $node->next     = $next;
        $prev->next     = $node;
        if ($next !== null) $next->previous = $node;
    }

    private function arrayRemove(Node $node): void
    {
        $oid = \spl_object_id($node);
        if (!isset($this->exists[$oid])) return;
        $count = \count($this->arr);
        for ($i = 0; $i < $count; $i++) {
            if ($this->arr[$i] !== $node) continue;
            $prev = $i > 0          ? $this->arr[$i - 1] : $this->root;
            $next = $i + 1 < $count ? $this->arr[$i + 1] : null;
            $prev->next = $next;
            if ($next !== null) $next->previous = $prev;
            \array_splice($this->arr, $i, 1);
            unset($this->exists[$oid]);
            $node->previous = $node->next = null;
            return;
        }
    }
}
