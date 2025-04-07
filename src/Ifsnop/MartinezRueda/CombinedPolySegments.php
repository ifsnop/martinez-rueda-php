<?php

namespace Ifsnop\MartinezRueda;

class CombinedPolySegments {
    public $combined;
    public $isInverted1;
    public $isInverted2;

    public function __construct(array $combined = null, bool $isInverted1 = false, bool $isInverted2 = false) {
        $this->combined = $combined;
        $this->isInverted1 = $isInverted1;
        $this->isInverted2 = $isInverted2;
    }
}
