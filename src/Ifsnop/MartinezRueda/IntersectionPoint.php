<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class IntersectionPoint
{
    public $alongA;
    public $alongB;
    public $point;

    public function __construct(int $alongA, int $alongB, Point $point)
    {
        $this->alongA = $alongA;
        $this->alongB = $alongB;
        $this->point = $point;
    }
}
