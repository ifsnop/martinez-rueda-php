<?php

namespace Ifsnop\MartinezRueda;

class SegmentIntersecter extends Intersecter {
    public function __construct() {
        parent::__construct(false);
    }

    public function calculate2(array $segments1, bool $isInverted1, array $segments2, bool $isInverted2) {
        foreach ($segments1 as $segment) {
            $this->eventAddSegment($this->segmentCopy($segment->start, $segment->end, $segment), true);
        }

        foreach ($segments2 as $segment) {
            $this->eventAddSegment($this->segmentCopy($segment->start, $segment->end, $segment), false);
        }

        return parent::calculate($isInverted1, $isInverted2);
    }
}
