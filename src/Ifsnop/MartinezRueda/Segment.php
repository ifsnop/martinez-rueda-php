<?php

namespace Ifsnop\MartinezRueda;

class Segment {
    public $start;
    public $end;
    public $myFill;
    public $otherFill;

    public function __construct(Point $start, Point $end, Fill $myFill = null, Fill $otherFill = null) {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	$this->start = $start;
	$this->end = $end;
	$this->myFill = $myFill;
	$this->otherFill = $otherFill;
    }

    public function __toString() {
	return "S: {$this->start}, E: {$this->end}";
    }

    public function __debugInfo() {
	return ["start" => $this->start, "end" => $this->end, 'myFill' => $this->myFill, 'otherFill' => $this->otherFill];
    }
}

