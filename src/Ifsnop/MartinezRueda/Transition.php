<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Transition
{
    public ?Node $after;
    public ?Node $before;

    public function __construct(?Node $after, ?Node $before)
    {
        $this->after = $after;
        $this->before = $before;
    }
}
