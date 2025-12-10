<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

class Intersecter {
    private $selfIntersection;
    private $eventRoot;

    public function __construct(bool $selfIntersection) {
        $this->selfIntersection = $selfIntersection;
        $this->eventRoot = new StatusList();
    }

    public function newSegment(Point $start, Point $end): Segment {
        return new Segment(start: $start, end: $end, myFill: new Fill());
    }

    public function segmentCopy(Point $start, Point $end, Segment $seg): Segment {
        return new Segment(
            start: $start, end: $end, myFill: new Fill($seg->myFill->below, $seg->myFill->above)
        );
    }

    private function eventAdd(Node $ev, Point $otherPt): void
    {
	// Cachear valores de $ev que son invariantes durante la búsqueda
	$p1IsStart = $ev->isStart;
	$p11       = $ev->pt;       // punto de este evento
	$p12       = $otherPt;      // el otro punto de su segmento

	// Closure estática: no captura $this
	$checkFunc = static function (Node $here) use ($p1IsStart, $p11, $p12): bool {
	    // Cachear propiedades de $here para reducir accesos repetidos
	    $hPt      = $here->pt;
	    $hOtherPt = $here->other->pt;
	    $hIsStart = $here->isStart;

	    // === Lógica inlined de eventCompare(...) ===
	    // Criterio END antes que START en empates de p11
	    // 1) Orden primario por $p11 vs $hPt
	    $comp = Point::compare($p11, $hPt);
	    if (0 !== $comp) {
		return $comp < 0;
	    }

	    // 2) Si el otro extremo es igual, son el mismo evento → comp=0 → no insertar antes
	    $comp = Point::compare($p12, $hOtherPt);
	    if (0 === $comp) {
		return false; // equivale a (comp < 0) cuando comp == 0
	    }

	    // 3) Si uno es start y el otro end, la prioridad la da isStart
	    if ($p1IsStart !== $hIsStart) {
		// eventCompare devolvería (p1IsStart ? 1 : -1)
		// y nosotros devolvemos (comp < 0)
		return !$p1IsStart; // true si p1 es end (comp = -1)
	    }

	    // 4) Geométrico: por encima de la línea => +1; si no => -1
	    // Queremos comp < 0, o sea, NOT "pointAboveOrOnLine"
	    if ( $hIsStart ) {
		$lineA = $hPt;
		$lineB = $hOtherPt;
	    } else {
		$lineA = $hOtherPt;
		$lineB = $hPt;
	    }
	    //$lineA = $hIsStart ? $hPt : $hOtherPt;
	    //$lineB = $hIsStart ? $hOtherPt : $hPt;

	    return !Point::pointAboveOrOnLine($p12, $lineA, $lineB);
	};

	$this->eventRoot->insertBefore($ev, $checkFunc);
    }


    private function eventAddSegmentStart(Segment $segment, bool $primary): Node {
        $evStart = StatusList::node(
            new Node(
                isStart :true,
                pt : $segment->start,
                seg : $segment,
                primary : $primary
            )
        );
        $this->eventAdd($evStart, $segment->end);

	//Debug::log("ADD START: %s (primary=%s)", Debug::evStr($evStart), $primary ? 'Y' : 'N'); Debug::dumpEventQueue($this->eventRoot);
        return $evStart;
    }

    private function eventAddSegmentEnd(Node $evStart, Segment $segment, bool $primary): void {
        $evEnd = StatusList::node(
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
	//Debug::log("ADD END  : %s (primary=%s)", Debug::evStr($evEnd), $primary ? 'Y' : 'N'); Debug::dumpEventQueue($this->eventRoot);
    }

    public function eventAddSegment(Segment $segment, bool $primary): Node {

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

    private function eventUpdateEnd(Node $ev, Point $end): void {
        //call_user_func($ev->other->remove);
	($ev->other->remove)();
        $ev->seg->end = $end;
	$ev->seg->recalcBounds();
        $ev->other->pt = $end;
        $this->eventAdd($ev->other, $ev->pt);
    }

    private function eventDivide(Node $ev, Point $pt): Node {

	// No dividir en los extremos: evita segmentos de longitud 0
	$seg = $ev->seg;
	if ($pt->__eq($seg->start) || $pt->__eq($seg->end)) {
	    return $ev; // o $ev->other; cualquiera es válido aquí
	}
	// División real
        $ns = $this->segmentCopy($pt, $seg->end, $seg);

        $this->eventUpdateEnd($ev, $pt);
        return $this->eventAddSegment($ns, $ev->primary);
    }

    private static function statusCompare(Node $ev1, Node $ev2): int {
        $a1 = $ev1->seg->start; $a2 = $ev1->seg->end;
        $b1 = $ev2->seg->start; $b2 = $ev2->seg->end;

	if ( Point::collinear($a1, $b1, $b2) ) {
	    if ( Point::collinear($a2, $b1, $b2) ) {
		// Igualdad geométrica: 0 o desempate estable
		return 0;
	    }
	    return Point::pointAboveOrOnLine($a2, $b1, $b2) ? 1 : -1;
	}
	return Point::pointAboveOrOnLine($a1, $b1, $b2) ? 1 : -1;
    }

    private function statusFindSurrounding(StatusList $statusRoot, Node $ev): ?Transition {
        $checkFunc = static function(Node $here) use ($ev):bool {
            return self::statusCompare($ev, $here->ev) > 0;
        };
        return $statusRoot->findTransition($checkFunc);
    }

    // Nota: -2/+2 significan que la proyección cae fuera del segmento lejos del extremo.
    // Solo actuamos para {-1,0,1}: -1/1 dividimos en el extremo; 0 dividimos en el punto interior.
    private function checkIntersection(Node $ev1, Node $ev2): ?Node {
        $seg1 = $ev1->seg;
        $seg2 = $ev2->seg;


	// --- Rechazo temprano por AABB cacheada ---
	if ($seg1->maxX < $seg2->minX || $seg2->maxX < $seg1->minX ||
	    $seg1->maxY < $seg2->minY || $seg2->maxY < $seg1->minY) {
	    return null;
	}

        $a1 = $seg1->start;
        $a2 = $seg1->end;
        $b1 = $seg2->start;
        $b2 = $seg2->end;

	// Rechazo temprano por bbox
	//if (!self::bboxOverlap($a1, $a2, $b1, $b2)) {
        //    return null;
        //}

        $i = Point::linesIntersect($a1, $a2, $b1, $b2);
        if ($i === null) {
            if (!Point::collinear($a1, $a2, $b1)) {
                return null;
            }
	    if ($a1->__eq($b2) || $a2->__eq($b1)) {
 		return null;
	    }
	    $a1EquB1 = $a1->__eq($b1);
	    $a2EquB2 = $a2->__eq($b2);

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
            }

	    if ($a1Between) {
                if (!$a2EquB2) {
                    if ($a2Between) {
                        $this->eventDivide($ev2, $a2);
                    } else {
                        $this->eventDivide($ev1, $b2);
                    }
                }
                $this->eventDivide($ev2, $a1);
            }
        } else { // $i != null
            if ($i->alongA === 0) {
                if ($i->alongB === -1) {
                    $this->eventDivide($ev1, $b1);
                } elseif ($i->alongB === 0) {
                    $this->eventDivide($ev1, $i->point);
                } elseif ($i->alongB === 1) {
                    $this->eventDivide($ev1, $b2);
                }
            }
            if ($i->alongB === 0) {
                if ($i->alongA === -1) {
                    $this->eventDivide($ev2, $a1);
                } elseif ($i->alongA === 0) {
                    $this->eventDivide($ev2, $i->point);
                } elseif ($i->alongA === 1) {
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
        return $below !== null ? $this->checkIntersection($ev, $below) : null;
    }

    public function calculate(bool $primaryPolyInverted, bool $secondaryPolyInverted): array {
	$statusRoot = new StatusList();
        $segments = [];

        while (!$this->eventRoot->isEmpty()) {
	    $ev = $this->eventRoot->getHead();
	    if ($ev->isStart) {
	    // Backup: si un segmento degenerado ha entrado, detectarlo aquí también
		if ($ev->seg !== null && $ev->seg->start->__eq($ev->seg->end)) {
		//Debug::log("!! START WITH ZERO-LEN SEG: %s", Debug::segStr($ev->seg));
		    throw new PolyBoolException(
			"PolyBool: Zero-length segment detected during processing; check input/TOLERANCE"
		    );
		}

                $surrounding = $this->statusFindSurrounding($statusRoot, $ev);

		if ($surrounding === null || !is_object($surrounding) || !is_callable($surrounding->insert ?? null)) {
		    throw new PolyBoolException(
			"Invalid Transition from StatusList::findTransition"
		    );
		}

                $above = $surrounding->before !== null ? $surrounding->before->ev : null;
                $below = $surrounding->after !== null ? $surrounding->after->ev : null;

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
		    //Debug::log("  → Head changed by division; continue");
                    continue;
                }

                if ($this->selfIntersection) {
                    $toggle = false;
		    $seg = $ev->seg;
		    $mf = $seg->myFill;
                    if ($seg === null || $mf === null || $mf->below === null) {
                        $toggle = true;
                    } else {
                        $toggle = $mf->above !== $mf->below;
                    }

                    if ($below === null) {
                        $mf->below = $primaryPolyInverted;
                    } else {
                        $mf->below = $below->seg->myFill->above;
                    }

                    if ($toggle) {
                        $mf->above = !$mf->below;
                    } else {
                        $mf->above = $mf->below;
                    }

		    //Debug::log("  FILL(self): my[below=%s, above=%s]", $ev->seg->myFill->below ? '1':'0', $ev->seg->myFill->above ? '1':'0');
                } else { // !$this->selfIntersection

		    // Evitar utilizar sementos no inicializados
		    if ($ev->seg->myFill === null) {
			$ev->seg->myFill = new Fill();
		    }

		    if ($ev->seg->myFill->below === null || $ev->seg->myFill->above === null) {
			// Buscar el vecino inferior del MISMO polígono en el status
                        $sameBelowEv = null;
                        $cursor = $surrounding->after; // nodo de StatusList (no el ev)
                        while ($cursor !== null) {
			    $curEv = $cursor->ev;
			    if ($curEv !== null && $curEv->primary === $ev->primary) {
				$sameBelowEv = $curEv;
                                break;
                            }
			    $cursor = $cursor->next;
                         }
                         $baseline = $ev->primary ? $primaryPolyInverted : $secondaryPolyInverted;
                         $belowInsideOwn =
                             $sameBelowEv !== null && $sameBelowEv->seg !== null && $sameBelowEv->seg->myFill !== null
                                 ? (bool)$sameBelowEv->seg->myFill->above
                                 : (bool)$baseline;
                         // En polígonos simples, cada borde invierte el interior propio
                         $ev->seg->myFill->below = $belowInsideOwn;
                         $ev->seg->myFill->above = !$belowInsideOwn;
                    }
		    // 2) Asegurar otherFill (interior respecto al otro polígono), como ya hacías

		    if ($ev->seg->otherFill === null) {
            		$inside = false;
			if ($below === null) {
	    		    $inside = $ev->primary ? $secondaryPolyInverted : $primaryPolyInverted;
                        } else {
	                    if ($ev->primary === $below->primary) {
                                if ($below->seg->otherFill === null) {
                                    // defensivo: evita null deref
                                    $below->seg->otherFill = new Fill(false, false);
                                }
                                $inside = (bool)$below->seg->otherFill->above;
                            } else {
                                $inside = (bool)$below->seg->myFill->above;
                            }
                        }

			$ev->seg->otherFill = new Fill($inside, $inside);
		    }
		}

		$ev->other->status = ($surrounding->insert)(StatusList::node(new Node(ev : $ev)));
	    } else { // !$ev->isStart
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

		    //Debug::log("  CHECK neighbors intersection (prev-next)");
                    $this->checkIntersection($st->previous->ev, $st->next->ev);
                }
		($st->remove)();

		if (!$ev->primary) {
		    $s = $ev->seg->myFill;
		    $ev->seg->myFill = $ev->seg->otherFill;
		    $ev->seg->otherFill = $s;
		}
		$segments[] = $ev->seg;
	    }
	    ($this->eventRoot->getHead()->remove)();
	}
        return $segments;
    }
}
