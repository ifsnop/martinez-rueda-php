<?php

namespace Ifsnop\MartinezRueda;

class PolySegments {


    /** @var Segment[] */
    public array $segments;
    public bool $isInverted;
    /** @var array<string, bool> key(x,y)=>true */
    public array $touchVertices;

    public function __construct(array $segments, bool $isInverted, array $touchVertices = [])
    {
        $this->segments      = $segments;
        $this->isInverted    = $isInverted;
        $this->touchVertices = $touchVertices;
    }
}

