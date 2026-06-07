<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Node
{
    public $status;
    public $other;
    public $ev;
    public $previous;
    public $next;
    public $isRoot;
    public $isStart;
    public $pt;
    public $seg;
    public $primary;
    public bool $inStatus = false;
    /** Wrapper del nodo en la skip list (EventList o StatusList): back-pointer para borrado O(altura) sin closure. */
    public ?SkipNode $snode = null;

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
        Node $status = null
    ) {
        $this->status = $status;
        $this->other = $other;
        $this->ev = $ev;
        $this->previous = $previous;
        $this->next = $next;
        $this->isRoot = $isRoot;
        $this->isStart = $isStart;
        $this->pt = $pt;
        $this->seg = $seg;
        $this->primary = $primary;
    }
}
