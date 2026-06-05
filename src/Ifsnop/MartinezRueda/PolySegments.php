<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class PolySegments {
    public $segments;
    public $isInverted;
    public ?array $bounds;

    public function __construct(array $segments = [], bool $isInverted = false, ?array $bounds = null) {
        $this->segments = $segments;
        $this->isInverted = $isInverted;
        $this->bounds = $bounds;
    }


}
