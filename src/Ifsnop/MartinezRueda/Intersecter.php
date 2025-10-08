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


    // Criterio END antes que START en empates de p11
    private function eventCompare(
        bool $p1IsStart,
        Point $p11,
        Point $p12,
        bool $p2IsStart,
        Point $p21,
        Point $p22
    ) {
        $comp = Point::compare($p11, $p21);
        if ( 0 != $comp ) {
            return $comp;
        }

        $comp = Point::compare($p12, $p22);
        // if ($p12 == $p22) {
	if ( 0 == $comp ) {
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
        // $evStart = $this->eventAddSegmentStart($segment, $primary);
        // $this->eventAddSegmentEnd($evStart, $segment, $primary);
        // return $evStart;

	// parche de copilot


	if ($segment->start->__eq($segment->end)) {
	    throw new PolyBoolException(
		"PolyBool: Zero-length segment detected; check input or adjust Algorithm::TOLERANCE"
	    );
	}


	// Si has procesado un evento de final (END) antes de que su evento de inicio (START)
	//  hubiera sido insertado en la StatusList, por lo que $ev->status === null y lanza esa excepción.
	// Zero-length segment detected.
	// Normaliza el sentido del segmento: START = extremo "izquierdo" (o menor lexicográfico)
	if (Point::compare($segment->start, $segment->end) > 0) {
	    $tmp = $segment->start;
	    $segment->start = $segment->end;
	    $segment->end = $tmp;
	}

	$evStart = $this->eventAddSegmentStart($segment, $primary);
	$this->eventAddSegmentEnd($evStart, $segment, $primary);
	return $evStart;
    }

    private function eventUpdateEnd(Node $ev, Point $end) {
        //call_user_func($ev->other->remove);
	($ev->other->remove)();
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
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
        $a1 = $ev1->seg->start; $a2 = $ev1->seg->end;
        $b1 = $ev2->seg->start; $b2 = $ev2->seg->end;
	// Cambio recomendado (arreglar antisimetria en statusCompare
        //if (Point::collinear($a1, $b1, $b2)) {
        //    if (Point::collinear($a2, $b1, $b2)) {
        //        return 1;
        //    }
        //    return Point::pointAboveOrOnLine($a2, $b1, $b2) ? 1 : -1;
        //}
        //return Point::pointAboveOrOnLine($a1, $b1, $b2) ? 1 : -1;

	$a1OnB = Point::collinear($a1, $b1, $b2);
	$a2OnB = Point::collinear($a2, $b1, $b2);

	// para evitar 0, esto sería un desempate estable:
	//if ($a1OnB && $a2OnB)
	//    return $ev1 === $ev2 ? 0 : (\spl_object_id($ev1) < \spl_object_id($ev2) ? -1 : 1);


	if ($a1OnB) {
	    if ($a2OnB) {
		// Igualdad geométrica: 0 o desempate estable
		// die("este caso es una optimización de copilot, anteriormente se devolvía 1\n");
		return 0; // o usar identidad de objetos si prefieres estabilidad estricta
	    }
	    return Point::pointAboveOrOnLine($a2, $b1, $b2) ? 1 : -1;
	}
	return Point::pointAboveOrOnLine($a1, $b1, $b2) ? 1 : -1;
    }

    private function statusFindSurrounding(StatusList $statusRoot, Node $ev): ?Transition { // ?Node {
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
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
        // $statusRoot = new LinkedList();
	// $statusRoot = new LinkedList(LinkedList::MODE_STATUS);
	$statusRoot = new StatusList(); // LinkedList(LinkedList::MODE_STATUS);
        $segments = [];

        // $cnt = 0;

        while (!$this->eventRoot->isEmpty()) {
            // echo __METHOD__.":cnt=".$cnt . PHP_EOL;
            // $cnt++;
            $ev = $this->eventRoot->getHead();
            if ($ev->isStart) {


		// Backup: si un segmento degenerado ha entrado, detectarlo aquí también
		if ($ev->seg !== null && $ev->seg->start->__eq($ev->seg->end)) {
		    throw new PolyBoolException(
			"PolyBool: Zero-length segment detected during processing; check input/TOLERANCE"
		    );
		}

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
                    //call_user_func($ev->other->remove);
		    ($ev->other->remove)();
                    //call_user_func($ev->remove);
		    ($ev->remove)();
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
		    // Sugerencia de COPILOT para evitar utilizar sementos no inicializados
		    // nos ha aparecido al ejecutar unos casos de prueba de COPILOT
                    if ($ev->seg->otherFill === null) {
                        $inside = false;
                        if ($below === null) {
                            $inside = $ev->primary ? $secondaryPolyInverted : $primaryPolyInverted;
                        } else {
                            $inside = $ev->primary === $below->primary ? $below->seg->otherFill->above : $below->seg->myFill->above;
                        }
                        $ev->seg->otherFill = new Fill($inside, $inside);
                    }


		//    if ($ev->seg->otherFill === null) {
		//	$inside = false;
		//	if ($below === null) {
		//	    $inside = $ev->primary ? $secondaryPolyInverted : $primaryPolyInverted;
		//	} else {
		//	    if ($ev->primary === $below->primary) {
		//		// misma procedencia → usamos el estado "respecto al otro polígono"
		//		$inside = $below->seg->otherFill?->above ?? false;
		//	    } else {
		//		// distinta procedencia:
		//		// preferimos myFill si ya se definió; si no, caemos a otherFill (que sí existe en START)
		//		$inside = $below->seg->myFill?->above
		//		    ?? $below->seg->otherFill?->above
		//		    ?? false;
		//	    }
		//	}
		//	$ev->seg->otherFill = new Fill($inside, $inside);
		//    }
                }
		/*
		$ev->other->status = call_user_func_array(
		    $surrounding->insert,
		    array(StatusList::node(new Node(ev : $ev)))
		);
		*/
		$ev->other->status = ($surrounding->insert)(StatusList::node(new Node(ev : $ev)));
            } else {
                $st = $ev->status;
		// en lugar de lanzar simplemente la excepcion, comprobamos si hubo un problema al tratar
		// los certices por estar mal orientados (que ya no debería pasar por el parche que
		// hemos implementado de copilot. sea como sea, hacemos el error más amable.
                if ($st === null) {
		    $lenZero = $ev->seg !== null && $ev->seg->start->__eq($ev->seg->end);
		    $detail  = $lenZero ? 'zero-length segment' : 'end event reached before its start (segment orientation?)';
		    throw new PolyBoolException("PolyBool: $detail; check segment orientation or TOLERANCE");
                }
                if ($statusRoot->exists($st->previous) && $statusRoot->exists($st->next)) {
                    $this->checkIntersection($st->previous->ev, $st->next->ev);
                }
		// call_user_func($st->remove);
		($st->remove)();


                if (!$ev->primary) {
                    $s = $ev->seg->myFill;
                    $ev->seg->myFill = $ev->seg->otherFill;
                    $ev->seg->otherFill = $s;
                }
                $segments[] = $ev->seg;
            }
            //call_user_func($this->eventRoot->getHead()->remove);
	    ($this->eventRoot->getHead()->remove)();
        }
        // print_r($segments);
        // var_dump($segments);
        return $segments;
    }
/*
    // usado en intersecter cuando hay un polígono encima de otro
    // En la rama no self-intersection de tu Intersecter::calculate() (cuando 
    // selfIntersection === false), el inside que asignas a otherFill del segmento
    // se calcula leyendo los fills del vecino below:
    // if ($below === null) {    $inside = $ev->primary ? $secondaryPolyInverted : $primaryPolyInverted;} 
    // else {    $inside = $ev->primary === $below->primary        ? $below->seg->otherFill->above        : 
    // $below->seg->myFill->above;   // ← cuando el vecino es del otro polígono}$ev->seg->otherFill = new Fill($inside, $inside);
    // Problema: cuando el below es del otro polígono, con frecuencia su myFill todavía no está inicializado
    // (en tu flujo se inicializa para el polígono secundario cuando llega su END por el swap, y
    // el polígono primario ni siquiera lo inicializa en la rama no-self).
    // Así, en subtramos donde debería dar true (p. ej., el vertical de A entre (4,2)-(4,4),
    // que está dentro de B), obtienes false.
    private function parityInsideAtPosition(
    StatusList $statusRoot,
    ?Node $after,           // nodo de StatusList que queda justo "debajo" del hueco de inserción (surrounding->after)
    bool $otherPrimary,     // true si el "otro" polígono es primary, false si es secondary
    bool $otherPolyInverted // primaryPolyInverted o secondaryPolyInverted según corresponda
): bool {
    // Baseline = fuera si no hay cruces (o invertido si así se pide)
    $inside = $otherPolyInverted;

    $cur = $statusRoot->getHead(); // primer nodo del status (StatusList::getHead)
    while ($cur !== null && $cur !== $after) {
        if ($cur->ev !== null && $cur->ev->primary === $otherPrimary) {
            // Cada borde del otro polígono cambia el estado inside/outside
            $inside = !$inside;
        }
        $cur = $cur->next;
    }
    return $inside;
}

*/

}
