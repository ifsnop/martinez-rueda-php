<?php

namespace Ifsnop\MartinezRueda;

class Intersecter {
    private $selfIntersection;
    private $eventRoot;

    public function __construct(bool $selfIntersection) {
        $this->selfIntersection = $selfIntersection;
        $this->eventRoot = new LinkedList();
    }

    public function newSegment(Point $start, Point $end): Segment {
        return new Segment(start: $start, end: $end, myFill: new Fill());
    }

    public function segmentCopy(Point $start, Point $end, Segment $seg): Segment {
        return new Segment(
            start: $start, end: $end, myFill: new Fill($seg->myFill->below, $seg->myFill->above)
        );
    }

    private function eventCompare(
        bool $p1IsStart,
        Point $p11,
        Point $p12,
        bool $p2IsStart,
        Point $p21,
        Point $p22
    ) {
        $comp = Point::compare($p11, $p21);
        if ($comp != 0) {
            return $comp;
        }

        if ($p12 == $p22) {
            return 0;
        }

        if ($p1IsStart != $p2IsStart) {
            return $p1IsStart ? 1 : -1;
        }

        return Point::pointAboveOrOnLine(
            $p12, $p2IsStart ? $p21 : $p22, $p2IsStart ? $p22 : $p21
        ) ? 1 : -1;
    }

    private function eventAdd(Node $ev, Point $otherPt) {
        $checkFunc = function(Node $here) use ($ev, $otherPt) {
            $comp = $this->eventCompare(
                $ev->isStart, $ev->pt, $otherPt, $here->isStart, $here->pt, $here->other->pt
            );
            return $comp < 0;
        };

        $this->eventRoot->insertBefore($ev, $checkFunc);
    }

    private function eventAddSegmentStart(Segment $segment, bool $primary): Node {
        $evStart = LinkedList::node(
            new Node(
                isStart :true,
                pt : $segment->start,
                seg : $segment,
                primary : $primary
            )
        );
        $this->eventAdd($evStart, $segment->end);
        return $evStart;
    }

    private function eventAddSegmentEnd(Node $evStart, Segment $segment, bool $primary) {
        $evEnd = LinkedList::node(
            new Node(
                isStart : false,
                pt : $segment->end,
                seg : $segment,
                primary : $primary,
                other : $evStart
            )
        );
        $evStart->other = $evEnd;
        $this->eventAdd($evEnd, $evStart->pt);
    }

    public function eventAddSegment(Segment $segment, bool $primary): Node {
        $evStart = $this->eventAddSegmentStart($segment, $primary);
        $this->eventAddSegmentEnd($evStart, $segment, $primary);
        return $evStart;
    }

    private function eventUpdateEnd(Node $ev, Point $end) {
        call_user_func($ev->other->remove);
        $ev->seg->end = $end;
        $ev->other->pt = $end;
        $this->eventAdd($ev->other, $ev->pt);
    }

    private function eventDivide(Node $ev, Point $pt): Node {
        $ns = $this->segmentCopy($pt, $ev->seg->end, $ev->seg);
        $this->eventUpdateEnd($ev, $pt);
        return $this->eventAddSegment($ns, $ev->primary);
    }

    private function statusCompare(Node $ev1, Node $ev2): int {
	echo __METHOD__ . PHP_EOL;
        $a1 = $ev1->seg->start;
        $a2 = $ev1->seg->end;
        $b1 = $ev2->seg->start;
        $b2 = $ev2->seg->end;

        if (Point::collinear($a1, $b1, $b2)) {
            if (Point::collinear($a2, $b1, $b2)) {
                return 1;
            }
            return Point::pointAboveOrOnLine($a2, $b1, $b2) ? 1 : -1;
        }
        return Point::pointAboveOrOnLine($a1, $b1, $b2) ? 1 : -1;
    }

    private function statusFindSurrounding(LinkedList $statusRoot, Node $ev): ?Transition { // ?Node {
        $checkFunc = function(Node $here) use ($ev) {
            return $this->statusCompare($ev, $here->ev) > 0;
        };

        return $statusRoot->findTransition($checkFunc);
    }

    private function checkIntersection(Node $ev1, Node $ev2): ?Node {
        $seg1 = $ev1->seg;
        $seg2 = $ev2->seg;
        $a1 = $seg1->start;
        $a2 = $seg1->end;
        $b1 = $seg2->start;
        $b2 = $seg2->end;

        $i = Point::linesIntersect($a1, $a2, $b1, $b2);
        if ($i === null) {
            if (!Point::collinear($a1, $a2, $b1)) {
                return null;
            }
            if ($a1 == $b2 || $a2 == $b1) {
                return null;
            }
            $a1EquB1 = $a1 == $b1;
            $a2EquB2 = $a2 == $b2;
            if ($a1EquB1 && $a2EquB2) {
                return $ev2;
            }

            $a1Between = !$a1EquB1 && Point::between($a1, $b1, $b2);
            $a2Between = !$a2EquB2 && Point::between($a2, $b1, $b2);

            if ($a1EquB1) {
                if ($a2Between) {
                    $this->eventDivide($ev2, $a2);
                } else {
                    $this->eventDivide($ev1, $b2);
                }
                return $ev2;
            } elseif ($a1Between) {
                if (!$a2EquB2) {
                    if ($a2Between) {
                        $this->eventDivide($ev2, $a2);
                    } else {
                        $this->eventDivide($ev1, $b2);
                    }
                }
                $this->eventDivide($ev2, $a1);
            }
        } else {
            if ($i->alongA == 0) {
                if ($i->alongB == -1) {
                    $this->eventDivide($ev1, $b1);
                } elseif ($i->alongB == 0) {
                    $this->eventDivide($ev1, $i->point);
                } elseif ($i->alongB == 1) {
                    $this->eventDivide($ev1, $b2);
                }
            }
            if ($i->alongB == 0) {
                if ($i->alongA == -1) {
                    $this->eventDivide($ev2, $a1);
                } elseif ($i->alongA == 0) {
                    $this->eventDivide($ev2, $i->point);
                } elseif ($i->alongA == 1) {
                    $this->eventDivide($ev2, $a2);
                }
            }
        }
        return null;
    }

    private function checkBothIntersections(?Node $above, Node $ev, ?Node $below): ?Node {
        if ($above !== null) {
            $eve = $this->checkIntersection($ev, $above);
            if ($eve !== null) {
                return $eve;
            }
        }
        if ($below !== null) {
            return $this->checkIntersection($ev, $below);
        }
        return null;
    }

    public function calculate(bool $primaryPolyInverted, bool $secondaryPolyInverted): array {
        echo __METHOD__ . PHP_EOL;
        $statusRoot = new LinkedList();
        $segments = [];

        $cnt = 0;

        while (!$this->eventRoot->isEmpty()) {
            // echo __METHOD__.":cnt=".$cnt . PHP_EOL;
            $cnt++;
            $ev = $this->eventRoot->getHead();
            if ($ev->isStart) {
                $surrounding = $this->statusFindSurrounding($statusRoot, $ev);
                $above = $surrounding->before !== null ? $surrounding->before->ev : null;
                $below = $surrounding->after !== null ? $surrounding->after->ev : null;

                // if ( null === $above ) print __METHOD__.":above=null" . PHP_EOL; else print __METHOD__.":above!=null" . PHP_EOL;
                // if ( null === $below ) print __METHOD__.":below=null" . PHP_EOL; else print __METHOD__.":below!=null" . PHP_EOL;

                $eve = $this->checkBothIntersections($above, $ev, $below);
                if ($eve !== null) {
                    if ($this->selfIntersection) {
                        $toggle = false;
                        if (/*$ev->seg->myFill === null || */$ev->seg->myFill->below === null) {
                            $toggle = true;
                        } else {
                            $toggle = $ev->seg->myFill->above !== $ev->seg->myFill->below;
                        }

                        if ($toggle) {
                            $eve->seg->myFill->above = !$eve->seg->myFill->above;
                        }
                    } else {
                        $eve->seg->otherFill = $ev->seg->myFill;
                    }
                    call_user_func($ev->other->remove);
                    call_user_func($ev->remove);
                }

                if ($this->eventRoot->getHead() !== $ev) {
                    continue;
                }

                if ($this->selfIntersection) {
                    $toggle = false;
                    if ($ev->seg === null || $ev->seg->myFill === null || $ev->seg->myFill->below === null) {
                        $toggle = true;
                    } else {
                        $toggle = $ev->seg->myFill->above !== $ev->seg->myFill->below;
                    }

                    if ($below === null) {
                        $ev->seg->myFill->below = $primaryPolyInverted;
                    } else {
                        $ev->seg->myFill->below = $below->seg->myFill->above;
                    }

                    if ($toggle) {
                        $ev->seg->myFill->above = !$ev->seg->myFill->below;
                    } else {
                        $ev->seg->myFill->above = $ev->seg->myFill->below;
                    }
                } else {
                    if ($ev->seg->otherFill === null) {
                        $inside = false;
                        if ($below === null) {
                            $inside = $ev->primary ? $secondaryPolyInverted : $primaryPolyInverted;
                        } else {
                            $inside = $ev->primary === $below->primary ? $below->seg->otherFill->above : $below->seg->myFill->above;
                        }
                        $ev->seg->otherFill = new Fill($inside, $inside);
                    }
                }
		$ev->other->status = call_user_func_array(
		    $surrounding->insert,
		    array(LinkedList::node(new Node(ev : $ev)))
		);
            } else {
                $st = $ev->status;
                if ($st === null) {
                    throw new PolyBoolException("PolyBool: Zero-length segment detected; your epsilon is probably too small or too large");
                }
                if ($statusRoot->exists($st->previous) && $statusRoot->exists($st->next)) {
                    $this->checkIntersection($st->previous->ev, $st->next->ev);
                }
                call_user_func($st->remove);

                if (!$ev->primary) {
                    $s = $ev->seg->myFill;
                    $ev->seg->myFill = $ev->seg->otherFill;
                    $ev->seg->otherFill = $s;
                }
                $segments[] = $ev->seg;
            }
            call_user_func($this->eventRoot->getHead()->remove);
        }
        // print_r($segments);
        // var_dump($segments);
        return $segments;
    }
}
