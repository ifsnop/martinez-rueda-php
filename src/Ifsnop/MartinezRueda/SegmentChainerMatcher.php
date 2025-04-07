<?php

namespace Ifsnop\MartinezRueda;

class SegmentChainerMatcher {
    public $firstMatch;
    public $secondMatch;
    public $nextMatch;

    public function __construct() {
        $this->firstMatch = new Matcher(0, false, false);
        $this->secondMatch = new Matcher(0, false, false);

        $this->nextMatch = $this->firstMatch;
    }

    public function setMatch(int $index, bool $matchesHead, bool $matchesPt1) {
        $this->nextMatch->index = $index;
        $this->nextMatch->matchesHead = $matchesHead;
        $this->nextMatch->matchesPt1 = $matchesPt1;
        if ($this->nextMatch === $this->firstMatch) {
            $this->nextMatch = $this->secondMatch;
            return false;
        }
        $this->nextMatch = null;
        return true;
    }
}
