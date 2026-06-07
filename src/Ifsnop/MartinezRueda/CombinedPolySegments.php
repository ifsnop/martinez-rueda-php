<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class CombinedPolySegments
{
    public ?array $combined;
    public bool $isInverted1;
    public bool $isInverted2;
    
    public function __construct(?array $combined = null, bool $isInverted1 = false, bool $isInverted2 = false)
    {
        $this->combined = $combined;
        $this->isInverted1 = $isInverted1;
        $this->isInverted2 = $isInverted2;
    }
}
