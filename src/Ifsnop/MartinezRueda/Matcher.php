<?php

namespace Ifsnop\MartinezRueda;

class Matcher {
    public $index;
    public $matchesHead;
    public $matchesPt1;

    public function __construct(int $index, bool $matchesHead, bool $matchesPt1) {
        $this->index = $index;
        $this->matchesHead = $matchesHead;
        $this->matchesPt1 = $matchesPt1;
    }
}
