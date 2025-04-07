<?php

namespace Ifsnop\MartinezRueda;

class Transition {
    public $after;
    public $before;
    public $insert;

    public function __construct(?Node $after, ?Node $before, callable $insert) {
        $this->after = $after;
        $this->before = $before;
        $this->insert = $insert;
    }
}
