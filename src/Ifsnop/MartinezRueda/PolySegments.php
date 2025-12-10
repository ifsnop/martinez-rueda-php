<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class PolySegments {
    public $segments;
    public $isInverted;

    public function __construct(array $segments = null, bool $isInverted = false) {
        $this->segments = $segments;
        $this->isInverted = $isInverted;
    }
}
