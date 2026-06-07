<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Matcher
{
    public int $index;
    public bool $matchesHead;
    public bool$matchesPt1;

    public function __construct(int $index, bool $matchesHead, bool $matchesPt1)
    {
        $this->index = $index;
        $this->matchesHead = $matchesHead;
        $this->matchesPt1 = $matchesPt1;
    }
}
