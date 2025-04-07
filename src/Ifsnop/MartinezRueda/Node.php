<?php

namespace Ifsnop\MartinezRueda;

class Node {
    public $status;
    public $other;
    public $ev;
    public $previous;
    public $next;
    public $isRoot;
    public $remove;
    public $isStart;
    public $pt;
    public $seg;
    public $primary;

    public function __construct(
        bool $isRoot = false,
        bool $isStart = false,
        $pt = null,
        $seg = null,
        bool $primary = false,
        Node $next = null,
        Node $previous = null,
        Node $other = null,
        Node $ev = null,
        Node $status = null,
        callable $remove = null
    ) {
        $this->status = $status;
        $this->other = $other;
        $this->ev = $ev;
        $this->previous = $previous;
        $this->next = $next;
        $this->isRoot = $isRoot;
        $this->remove = $remove;
        $this->isStart = $isStart;
        $this->pt = $pt;
        $this->seg = $seg;
        $this->primary = $primary;
    }
}
