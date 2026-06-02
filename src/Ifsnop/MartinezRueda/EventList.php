<?php
declare(strict_types=1);
namespace Ifsnop\MartinezRueda;

final class EventList
{
    /** @var Node[] */
    private array $arr    = [];
    /** @var array<int,true> */
    private array $exists = [];
    private Node  $root;

    public function __construct() { $this->root = new Node(isRoot: true); }

    public function isEmpty(): bool  { return \count($this->arr) === 0; }
    public function getHead(): ?Node { return $this->arr[0] ?? null;    }

    public function insertBefore(Node $ev, Point $otherPt): void
    {
        $lo        = 0;
        $hi        = \count($this->arr);
        $p1IsStart = $ev->isStart;
        $p11       = $ev->pt;
        $p12       = $otherPt;

        while ($lo < $hi) {
            $mid      = ($lo + $hi) >> 1;
            $here     = $this->arr[$mid];
            $hPt      = $here->pt;
            $hOtherPt = $here->other->pt;
            $hIsStart = $here->isStart;

            $comp = Point::compare($p11, $hPt);
            if ($comp !== 0) {
                $check = $comp < 0;
            } elseif (0 === Point::compare($p12, $hOtherPt)) {
                $check = false;
            } elseif ($p1IsStart !== $hIsStart) {
                $check = !$p1IsStart;
            } else {
                $lineA = $hIsStart ? $hPt : $hOtherPt;
                $lineB = $hIsStart ? $hOtherPt : $hPt;
                $check = !Point::pointAboveOrOnLine($p12, $lineA, $lineB);
            }

            if ($check) { $hi = $mid; } else { $lo = $mid + 1; }
        }

        $this->arrayInsertAt($lo, $ev);
        $ev->remove = function () use ($ev) { $this->arrayRemove($ev); };
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
