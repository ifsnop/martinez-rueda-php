<?php

namespace Ifsnop\MartinezRueda;

class Algorithm {
    public const TOLERANCE = 1e-12; //9;
    public const DEBUG = false;


    public static function reverseChain(&$chains, int $index) {
	$chains[$index] = array_reverse($chains[$index]);
    }

    public static function appendChain(&$chains, int $index1, int $index2) {

 // concatenar y eliminar el origen
//    array_splice($chains[$iDst], count($chains[$index1]), 0, $chains[$index2]);
//    array_splice($chains, $index2, 1);
//}


	$chain1 = &$chains[$index1];
	$chain2 = &$chains[$index2];
	$tail = end($chain1);
	$tail2 = $chain1[count($chain1) - 2];
	$head = $chain2[0];
	$head2 = $chain2[1];
	if (Point::collinear($tail2, $tail, $head)) {
	    array_pop($chain1);
	    $tail = $tail2;
	}
	if (Point::collinear($tail, $head, $head2)) {
	    array_shift($chain2);
	}
	$chains[$index1] = array_merge($chain1, $chain2);
	array_splice($chains, $index2, 1);
    }


/*


    public static function segmentChainer(array $segments): array {
	$regions = [];
	$chains = [];
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;

	foreach ($segments as $k => $segment) {
	    $point1 = $segment->start;
	    $point2 = $segment->end;
	    if ($point1->__eq($point2)) {
		continue;
	    }

	    $segmentChainerMatcher = new SegmentChainerMatcher();

	    for ($i = 0; $i < count($chains); $i++) {
		$chain = &$chains[$i];
		$head = $chain[0];
		$tail = end($chain);

		if ($head->__eq($point1)) {
		    if ($segmentChainerMatcher->setMatch($i, true, true)) {
			break;
		    }
		} elseif ($head->__eq($point2)) {
		    if ($segmentChainerMatcher->setMatch($i, true, false)) {
			break;
		    }
		} elseif ($tail->__eq($point1)) {
		    if ($segmentChainerMatcher->setMatch($i, false, true)) {
			break;
		    }
		} elseif ($tail->__eq($point2)) {
		    if ($segmentChainerMatcher->setMatch($i, false, false)) {
			break;
		    }
		}
	    }

	    if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->firstMatch) {
		$chains[] = [$point1, $point2];
		continue;
	    }

	    if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->secondMatch) {
		$index = $segmentChainerMatcher->firstMatch->index;
		$point = $segmentChainerMatcher->firstMatch->matchesPt1 ? $point2 : $point1;
		$addToHead = $segmentChainerMatcher->firstMatch->matchesHead;

		$chain = &$chains[$index];
		$grow = $addToHead ? $chain[0] : end($chain);
		$grow2 = $addToHead ? $chain[1] : $chain[count($chain) - 2];
		$opposite = $addToHead ? end($chain) : $chain[0];
		$opposite2 = $addToHead ? $chain[count($chain) - 2] : $chain[1];
		if (Point::collinear($grow2, $grow, $point)) {
		    if ($addToHead) {
			array_shift($chain);
		    } else {
			array_pop($chain);
		    }
		    $grow = $grow2;
		}
		if ($opposite == $point) {
		    array_splice($chains, $index, 1);
		    if (Point::collinear($opposite2, $opposite, $grow)) {
			if ($addToHead) {
			    array_pop($chain);
			} else {
			    array_shift($chain);
			}
		    }
		    $regions[] = $chain;
		    continue;
		}
		if ($addToHead) {
		    $ret = array_unshift($chain, $point);
		} else {
		    $chain[] = $point;
		}
		continue; // esto cambia de segmento
	    }
	    $firstIndex = $segmentChainerMatcher->firstMatch->index;
	    $secondIndex = $segmentChainerMatcher->secondMatch->index;

	    $reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);
	    if ($segmentChainerMatcher->firstMatch->matchesHead) {
		if ($segmentChainerMatcher->secondMatch->matchesHead) {
		    if ($reverseFirst) {
			self::reverseChain($chains, $firstIndex);
			self::appendChain($chains, $firstIndex, $secondIndex);
		    } else {
			self::reverseChain($chains, $secondIndex);
			self::appendChain($chains, $secondIndex, $firstIndex);
		    }
		} else {
		    self::appendChain($chains, $secondIndex, $firstIndex);
		}
	    } else {
		if ($segmentChainerMatcher->secondMatch->matchesHead) {
		    self::appendChain($chains, $firstIndex, $secondIndex);
		} else {
		    if ($reverseFirst) {
			self::reverseChain($chains, $firstIndex);
			self::appendChain($chains, $secondIndex, $firstIndex);
		    } else {
			self::reverseChain($chains, $secondIndex);
			self::appendChain($chains, $firstIndex, $secondIndex);
		    }
		}
	    }
	}
	return $regions;
    }
*/

//COPILOT CAMBIO QUIRURGICO

public static function segmentChainer(array $segments, array $touchVertices = []): array {
    $regions = [];
    $chains  = [];
    // meta por cadena: fuente en head/tail (A/B), claves para lookup rápido
    $chainHeadSrc = [];   // bool|null por índice de chain
    $chainTailSrc = [];
    $key = fn(Point $p) => sprintf('%.12f,%.12f', $p->x(), $p->y());
    $isTouch = fn(Point $p) => isset($touchVertices[$key($p)]);

    if (Algorithm::DEBUG) { print __METHOD__.PHP_EOL; }


    $nullSrc = 0;
    foreach ($segments as $s) { if ($s->sourcePrimary === null) $nullSrc++; }
    print "Segments with null sourcePrimary: $nullSrc" . PHP_EOL;


    print "segments: ".count($segments) . PHP_EOL;

    foreach ($segments as $k => $segment) {

	print "VUELTA $k]" . json_encode($chains) . PHP_EOL;

        $p1 = $segment->start;
        $p2 = $segment->end;
        if ($p1->__eq($p2)) continue;

        $segSrc = $segment->sourcePrimary; // true=A, false=B, null=desconocido

        $matcher = new SegmentChainerMatcher();

        // Buscar coincidencias de extremos con cadenas existentes
        for ($i = 0; $i < count($chains); $i++) {
            $chain = &$chains[$i];
            $head  = $chain[0];
            $tail  = end($chain);

            // En vértice TOUCH NO mezclar fuentes A/B
            $canMatchHeadWith = function(Point $pt) use ($isTouch, $segSrc, $chainHeadSrc, $i) {
                if (!$isTouch($pt)) return true;
                // Si el vértice es TOUCH y ya tenemos fuente en el head, no mezclar
                return !isset($chainHeadSrc[$i]) || $chainHeadSrc[$i] === $segSrc;
            };
            $canMatchTailWith = function(Point $pt) use ($isTouch, $segSrc, $chainTailSrc, $i) {
                if (!$isTouch($pt)) return true;
                return !isset($chainTailSrc[$i]) || $chainTailSrc[$i] === $segSrc;
            };

            if ($head->__eq($p1) && $canMatchHeadWith($head)) {
                if ($matcher->setMatch($i, true, true)) break;
            } elseif ($head->__eq($p2) && $canMatchHeadWith($head)) {
                if ($matcher->setMatch($i, true, false)) break;
            } elseif ($tail->__eq($p1) && $canMatchTailWith($tail)) {
                if ($matcher->setMatch($i, false, true)) break;
            } elseif ($tail->__eq($p2) && $canMatchTailWith($tail)) {
                if ($matcher->setMatch($i, false, false)) break;
            }
        }

	// 1) No encaja en ninguna cadena -> crear una nueva
        if ($matcher->nextMatch === $matcher->firstMatch) {
            // No hubo match -> iniciar cadena nueva con la fuente de este segmento
            $chains[] = [$p1, $p2];
            $idx = count($chains) - 1;
            $chainHeadSrc[$idx] = $segSrc;
            $chainTailSrc[$idx] = $segSrc;
            continue;
        }

	// 2) Encaja en una cadena -> crecer esa cadena
        if ($matcher->nextMatch === $matcher->secondMatch) {
            // Solo una cadena coincidió -> crecer esa cadena
            $index = $matcher->firstMatch->index;
            $point = $matcher->firstMatch->matchesPt1 ? $p2 : $p1;
            $addToHead = $matcher->firstMatch->matchesHead;

            $chain = &$chains[$index];
            $grow  = $addToHead ? $chain[0] : end($chain);
            $grow2 = $addToHead ? $chain[1] : $chain[count($chain) - 2];
            $opposite  = $addToHead ? end($chain) : $chain[0];
            $opposite2 = $addToHead ? $chain[count($chain) - 2] : $chain[1];

            // *** No eliminar el vértice si es TOUCH ***
            if (!$isTouch($grow) && Point::collinear($grow2, $grow, $point)) {
                if ($addToHead) {
                    array_shift($chain);
                } else {
                    array_pop($chain);
                }
                $grow = $grow2;
            }

	    print $opposite->x() . " " . $opposite->y() . "==" . $point->x() . " " . $point->y() . PHP_EOL; // DEBUG
	    if ($opposite->__eq($point)) {
            //if ($opposite == $point) {
            // if ($opposite == $point) {
                // Cerramos un anillo
                array_splice($chains, $index, 1);
                if (!$isTouch($opposite) && Point::collinear($opposite2, $opposite, $grow)) {
                    if ($addToHead) {
                        array_pop($chain);
                    } else {
                        array_shift($chain);
                    }
                }
                $regions[] = $chain;
		print "CLOSE RING: ".count($chain)." vertices" . PHP_EOL;

                // Limpiar meta
                unset($chainHeadSrc[$index], $chainTailSrc[$index]);
                continue;
            }

            // Añadir punto y actualizar fuente en el extremo correspondiente
            if ($addToHead) {
                array_unshift($chain, $point);
                $chainHeadSrc[$index] = $segSrc ?? ($chainHeadSrc[$index] ?? null);
            } else {
                $chain[] = $point;
                $chainTailSrc[$index] = $segSrc ?? ($chainTailSrc[$index] ?? null);
            }
            continue;
        }

	
/*
        // Dos cadenas coinciden -> fusionarlas
        $firstIndex  = $matcher->firstMatch->index;
        $secondIndex = $matcher->secondMatch->index;

        // *** En vértice TOUCH, no fusionar si las fuentes difieren ***
        $fusionPoint =
            ($matcher->firstMatch->matchesHead ? $chains[$firstIndex][0] : end($chains[$firstIndex]));
        $isTouchFusion = $isTouch($fusionPoint);
        if ($isTouchFusion) {
            $src1 = $matcher->firstMatch->matchesHead ? ($chainHeadSrc[$firstIndex] ?? null) : ($chainTailSrc[$firstIndex] ?? null);
            $src2 = $matcher->secondMatch->matchesHead ? ($chainHeadSrc[$secondIndex] ?? null) : ($chainTailSrc[$secondIndex] ?? null);
            if ($src1 !== null && $src2 !== null && $src1 !== $src2) {
                // No fusionar cadenas de A y B en un vértice TOUCH -> inicia cadena nueva
                $chains[] = [$p1, $p2];
                $idx = count($chains) - 1;
                $chainHeadSrc[$idx] = $segSrc;
                $chainTailSrc[$idx] = $segSrc;
                continue;
            }
        }

        // Lógica original de fusionado (con posibles reversos)
        $reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);
        if ($matcher->firstMatch->matchesHead) {
            if ($matcher->secondMatch->matchesHead) {
                if ($reverseFirst) {
                    self::reverseChain($chains, $firstIndex);
                    self::appendChain($chains, $firstIndex, $secondIndex);
                } else {
                    self::reverseChain($chains, $secondIndex);
                    self::appendChain($chains, $secondIndex, $firstIndex);
                }
            } else {
                self::appendChain($chains, $secondIndex, $firstIndex);
            }
        } else {
            if ($matcher->secondMatch->matchesHead) {
                self::appendChain($chains, $firstIndex, $secondIndex);
            } else {
                if ($reverseFirst) {
                    self::reverseChain($chains, $firstIndex);
                    self::appendChain($chains, $secondIndex, $firstIndex);
                } else {
                    self::reverseChain($chains, $secondIndex);
                    self::appendChain($chains, $firstIndex, $secondIndex);
                }
            }
        }

        // Actualiza meta fuentes tras fusionar (simplificado: calcula de extremos)
        $iNew = $firstIndex; // según cómo appendChain lo deje
        $newHead = $chains[$iNew][0];
        $newTail = end($chains[$iNew]);
        $chainHeadSrc[$iNew] = $chainHeadSrc[$iNew] ?? $segSrc;
        $chainTailSrc[$iNew] = $chainTailSrc[$iNew] ?? $segSrc;

*/


	// 3) Dos cadenas encajan -> fusionarlas (con barrera TOUCH y recomputo de fuentes)
        
	// --- FUSIÓN DE DOS CADENAS ---
	$firstIndex  = $matcher->firstMatch->index;
	$secondIndex = $matcher->secondMatch->index;

	// Punto de fusión para comprobar TOUCH
	$fusionPoint = ($matcher->firstMatch->matchesHead ? $chains[$firstIndex][0] : end($chains[$firstIndex]));
	$isTouchFusion = $isTouch($fusionPoint);
	if ($isTouchFusion) {
	    $src1 = $matcher->firstMatch->matchesHead ? ($chainHeadSrc[$firstIndex] ?? null) : ($chainTailSrc[$firstIndex] ?? null);
	    $src2 = $matcher->secondMatch->matchesHead ? ($chainHeadSrc[$secondIndex] ?? null) : ($chainTailSrc[$secondIndex] ?? null);
	    if ($src1 !== null && $src2 !== null && $src1 !== $src2) {
	        // No fusionar A↔B en vértice TOUCH
	        $chains[] = [$p1, $p2];
	        $idx = count($chains) - 1;
	        $chainHeadSrc[$idx] = $segSrc;
	        $chainTailSrc[$idx] = $segSrc;
		print 'CLOSE RING with '.count($chain).' vertices' . PHP_EOL;
	        continue;
	    }
	}

	// Guarda extremos y fuentes ANTES de fusionar (para recalcular luego)
	$firstHeadPt = $chains[$firstIndex][0];
	$firstTailPt = end($chains[$firstIndex]);
	$secondHeadPt = $chains[$secondIndex][0];
	$secondTailPt = end($chains[$secondIndex]);
	$firstHeadSrc = $chainHeadSrc[$firstIndex] ?? null;
	$firstTailSrc = $chainTailSrc[$firstIndex] ?? null;
	$secondHeadSrc = $chainHeadSrc[$secondIndex] ?? null;
	$secondTailSrc = $chainTailSrc[$secondIndex] ?? null;
	
	// Lógica original de reversos y append
	$reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);
	if ($matcher->firstMatch->matchesHead) {
	    if ($matcher->secondMatch->matchesHead) {
	        if ($reverseFirst) {
	            self::reverseChain($chains, $firstIndex);
	            self::appendChain($chains, $firstIndex, $secondIndex);
	        } else {
	            self::reverseChain($chains, $secondIndex);
	            self::appendChain($chains, $secondIndex, $firstIndex);
	            // Nota: en esta rama el índice resultante es $secondIndex
	            $tmp = $firstIndex; $firstIndex = $secondIndex; $secondIndex = $tmp;
	        }
	    } else {
	        self::appendChain($chains, $secondIndex, $firstIndex);
	        // El índice resultante es $secondIndex
	        $tmp = $firstIndex; $firstIndex = $secondIndex; $secondIndex = $tmp;
	    }
	} else {
	    if ($matcher->secondMatch->matchesHead) {
	        self::appendChain($chains, $firstIndex, $secondIndex);
	        // Resultante: $firstIndex
	    } else {
	        if ($reverseFirst) {
	            self::reverseChain($chains, $firstIndex);
	            self::appendChain($chains, $secondIndex, $firstIndex);
	            $tmp = $firstIndex; $firstIndex = $secondIndex; $secondIndex = $tmp;
	        } else {
	            self::reverseChain($chains, $secondIndex);
	            self::appendChain($chains, $firstIndex, $secondIndex);
	            // Resultante: $firstIndex
	        }
	    }
	}




if (!isset($chains[$iNew]) || !is_array($chains[$iNew]) || count($chains[$iNew]) === 0) {
    // Recupera el índice del resultado: si se eliminó una anterior, el destino puede haber cambiado.
    // Intenta usar el otro índice como fallback:
    $alt = $secondIndex ?? null;
    if ($alt !== null && isset($chains[$alt]) && is_array($chains[$alt]) && count($chains[$alt]) > 0) {
        $iNew = $alt;
    } else {
        // Como último recurso, continúa con el siguiente segmento
        continue;
    }
}

// ¡Nunca uses end() sobre null! Usa la longitud:
$chainRef = &$chains[$iNew];
$newHeadPt = $chainRef[0];
$newTailPt = $chainRef[count($chainRef)-1];







	// Recalcular fuentes de head/tail **tras** la fusión
	$iNew = $firstIndex;                 // índice de la cadena resultante (ver intercambios arriba)
	$newHeadPt = $chains[$iNew][0];
	$newTailPt = end($chains[$iNew]);

	print "DEBUG" . PHP_EOL;
	print $newHeadPt->x() . " " . $newHeadPt->y() . "=" . $newTailPt->x() . " " . $newTailPt->y() . PHP_EOL;



if ($newHeadPt->__eq($newTailPt)) {
    // anillo cerrado tras la fusión
    $regions[] = $chains[$iNew];
    print "CLOSE RING: ".count($chain)." vertices" . PHP_EOL;

    // elimina la cadena consumida
    array_splice($chains, $iNew, 1);
    unset($chainHeadSrc[$iNew], $chainTailSrc[$iNew]);

    // Continúa con el siguiente segmento
    continue;
}




	// Dado que append/remove eliminó $secondIndex, determinamos las fuentes comparando puntos
	$headSrc = null; $tailSrc = null;
	
	if ($newHeadPt->__eq($firstHeadPt)) $headSrc = $firstHeadSrc;
	if ($newHeadPt->__eq($firstTailPt)) $headSrc = $firstTailSrc;
	if ($newHeadPt->__eq($secondHeadPt)) $headSrc = $secondHeadSrc;
	if ($newHeadPt->__eq($secondTailPt)) $headSrc = $secondTailSrc;

	if ($newTailPt->__eq($firstHeadPt)) $tailSrc = $firstHeadSrc;
	if ($newTailPt->__eq($firstTailPt)) $tailSrc = $firstTailSrc;
	if ($newTailPt->__eq($secondHeadPt)) $tailSrc = $secondHeadSrc;
	if ($newTailPt->__eq($secondTailPt)) $tailSrc = $secondTailSrc;

	// Asigna fuentes recalculadas
	$chainHeadSrc[$iNew] = $headSrc ?? ($chainHeadSrc[$iNew] ?? null);
	$chainTailSrc[$iNew] = $tailSrc ?? ($chainTailSrc[$iNew] ?? null);
	
	// Borra meta del índice ya eliminado
	unset($chainHeadSrc[$secondIndex], $chainTailSrc[$secondIndex]);

	

    }

    print 'REGIONS built: ' . count($regions) . PHP_EOL; // DEBUG
    return $regions;
}



/*
    public static function __select(array $segments, array $selection): array {
	$result = [];
	foreach ($segments as $segment) {
	    $index = (
		($segment->myFill->above ? 8 : 0) +
		($segment->myFill->below ? 4 : 0) +
		($segment->otherFill !== null && $segment->otherFill->above ? 2 : 0) +
		($segment->otherFill !== null && $segment->otherFill->below ? 1 : 0)
	    );

	    if ($selection[$index] !== 0) {
		$result[] = new Segment(
		    start : $segment->start,
		    end : $segment->end,
		    myFill : new Fill($selection[$index] == 2, $selection[$index] == 1)
		);
	    }
	}
	return $result;
    }
*/

    // core API
/*
    public static function segments($polygon) {
	$regionIntersecter = new RegionIntersecter();
	foreach ($polygon->regions as $region) {
	    $regionIntersecter->addRegion($region);
	}
	return new PolySegments($regionIntersecter->calculate2($polygon->isInverted), $polygon->isInverted);
    }
    public static function combine($segments1, $segments2) {
	$segmentIntersecter = new SegmentIntersecter();
	return new CombinedPolySegments(
	    $segmentIntersecter->calculate2(
		$segments1->segments,
		$segments1->isInverted,
		$segments2->segments,
		$segments2->isInverted
	    ),
	    $segments1->isInverted,
	    $segments2->isInverted
	);
    }


    public static function polygon($segments) {
	$s = self::segmentChainer($segments->segments);
	$p = Polygon::create()->fillFromArray($s , $segments->isInverted);
	return $p;
    }



*/


private static function appendChainsAndReturnIndex(
    array &$chains,
    array &$chainHeadSrc,
    array &$chainTailSrc,
    int $dst,
    int $src
): int {
    if ($dst === $src) {
        return $dst;
    }

    // Concatena: destino = destino + fuente
    array_splice($chains[$dst], count($chains[$dst]), 0, $chains[$src]);

    // Eliminamos la cadena fuente
    array_splice($chains, $src, 1);

    // Actualiza metadatos: conservamos la fuente del head/tail de $dst
    // (si necesitas mayor precisión, puedes recalcular mirando puntos head/tail)
    unset($chainHeadSrc[$src], $chainTailSrc[$src]);

    // IMPORTANTE: si eliminamos un índice menor, los mayores se desplazan
    if ($src < $dst) {
        $dst -= 1;
    }

    // Asegurar que los arrays de meta mantienen índices válidos
    // (opcional) Reindexar metadatos para coincidir con $chains:
    // Esto es más limpio: reconstruimos las metas según el nuevo orden
    $newHeadSrc = []; $newTailSrc = [];
    foreach (array_keys($chains) as $newIdx) {
        $newHeadSrc[$newIdx] = $chainHeadSrc[$newIdx] ?? ($newHeadSrc[$newIdx] ?? null);
        $newTailSrc[$newIdx] = $chainTailSrc[$newIdx] ?? ($newTailSrc[$newIdx] ?? null);
    }
    $chainHeadSrc = $newHeadSrc;
    $chainTailSrc = $newTailSrc;

    return $dst;
}




public static function segments($polygon): PolySegments
{
    $regionIntersecter = new RegionIntersecter(); // <- debe heredar de Intersecter y exponer getTouchVertices()
    foreach ($polygon->regions as $region) {
        $regionIntersecter->addRegion($region);
    }

    $segList = $regionIntersecter->calculate2($polygon->isInverted);

    // Recupera los vértices TOUCH detectados durante el sweep de este polígono
    $touch = method_exists($regionIntersecter, 'getTouchVertices')
        ? $regionIntersecter->getTouchVertices()
        : [];

    // PolySegments debe aceptar touchVertices en su ctor
    return new PolySegments(
        segments:     $segList,
        isInverted:   $polygon->isInverted,
        touchVertices:$touch
    );
}


public static function combine($segments1, $segments2): CombinedPolySegments
{
    $segmentIntersecter = new SegmentIntersecter(); // hereda Intersecter

    $combinedSegs = $segmentIntersecter->calculate2(
        $segments1->segments,
        $segments1->isInverted,
        $segments2->segments,
        $segments2->isInverted
    );

    // TOUCH detectados entre A y B (los que más nos importan para el “cosido”)
    $touch = method_exists($segmentIntersecter, 'getTouchVertices')
        ? $segmentIntersecter->getTouchVertices()
        : [];

    return new CombinedPolySegments(
        combined:      $combinedSegs,
        isInverted1:   $segments1->isInverted,
        isInverted2:   $segments2->isInverted,
        touchVertices: $touch
    );
}


public static function polygon(PolySegments $segments): Polygon
{
    // Pasar los TOUCH al chainer
    $s = self::segmentChainer(
        $segments->segments,
    //    $segments->touchVertices ?? []
	[]
    );

    // Construir el polígono a partir de los anillos resultantes
    $p = Polygon::create()->fillFromArray($s, $segments->isInverted);
    return $p;
}

/*

    public static function __operate($polygon1, $polygon2, $selector) {
	$firstPolygonRegions = self::segments($polygon1);
	$secondPolygonRegions = self::segments($polygon2);
	$combinedSegments = self::combine($firstPolygonRegions, $secondPolygonRegions);
	$selectedSegments = self::$selector($combinedSegments);
	$p = self::polygon($selectedSegments);
	return $p;
    }
    // helper functions for common operations
    public static function union(...$args):Polygon {
	if (count($args) === 1 && is_array($args[0])) {
	    $polygons = $args[0];
	    $firstSegments = self::segments($polygons[0]);
	    for ($i = 1; $i < count($polygons); $i++) {
		$secondSegments = self::segments($polygons[$i]);
		$combined = self::combine($firstSegments, $secondSegments);
		$firstSegments = self::selectUnion($combined);
	    }
	    return polygon($firstSegments);
	} elseif (count($args) === 2 &&
	    is_a($args[0], 'Ifsnop\MartinezRueda\Polygon') &&
	    is_a($args[1], 'Ifsnop\MartinezRueda\Polygon')) {
	    return self::__operate($args[0], $args[1], 'selectUnion');
	} else {
	    return Polygon::create()->fillFromArray([], true);
	}
    }
*/


public static function __operate($polygon1, $polygon2, $selector): Polygon
{
    // 1) Descomponer en segmentos (cada uno con su set TOUCH propio)
    $firstPolygonRegions  = self::segments($polygon1);
    $secondPolygonRegions = self::segments($polygon2);

    // 2) Combinar (genera los TOUCH “entre” A y B)
    $combinedSegments = self::combine($firstPolygonRegions, $secondPolygonRegions);

    // 3) Seleccionar operación (selectUnion / selectIntersect / selectDifference / selectXor)
    //    IMPORTANTE: el selector debe PROPAGAR touchVertices desde $combinedSegments a PolySegments.
    $selectedSegments = self::$selector($combinedSegments);

    // 4) Encadenar usando los TOUCH y devolver Polygon
    return self::polygon($selectedSegments);
}

// helper functions for common operations
public static function union(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons      = $args[0];
        $firstSegments = self::segments($polygons[0]);

        for ($i = 1; $i < count($polygons); $i++) {
            $secondSegments = self::segments($polygons[$i]);
            $combined       = self::combine($firstSegments, $secondSegments);
            // selectUnion debe devolver PolySegments con touchVertices del combinado
            $firstSegments  = self::selectUnion($combined);
        }

        // OJO: usar self::polygon(...)
        return self::polygon($firstSegments);

    } elseif (
        count($args) === 2 &&
        is_a($args[0], 'Ifsnop\MartinezRueda\Polygon') &&
        is_a($args[1], 'Ifsnop\MartinezRueda\Polygon')
    ) {
        return self::__operate($args[0], $args[1], 'selectUnion');
    } else {
        return Polygon::create()->fillFromArray([], true);
    }
}

public static function intersect($polygon1, $polygon2): Polygon
{
    // Asegúrate de que el selector existe: 'selectIntersect' o 'selectIntersection'
    return self::__operate($polygon1, $polygon2, 'selectIntersect');
}



/*
    public static function intersect($polygon1, $polygon2) {
	return self::__operate($polygon1, $polygon2, 'selectIntersect');
    }
*/
    public static function difference($polygon1, $polygon2) {
	return self::__operate($polygon1, $polygon2, 'selectDifference');
    }

    public static function differenceRev($polygon1, $polygon2) {
	return self::__operate($polygon1, $polygon2, 'selectDifferenceRev');
    }

    public static function xoring($polygon1, $polygon2) {
	return self::__operate($polygon1, $polygon2, 'selectXor');
    }









    /**
     * Helper genérico: selecciona segmentos por operación booleana a nivel de lado.
     * $combine($insideA, $insideB) => bool para cada lado (above/below)
     * - A (myFill)   = interior respecto al polígono 1 (tras el swap del Intersecter).
     * - B (otherFill)= interior respecto al polígono 2.
     */
    private static function __selectLogical(array $segments, callable $combine): array
    {
        $result = [];
        foreach ($segments as $seg) {
            $my = $seg->myFill ?? new Fill(null, null);
            $ot = $seg->otherFill ?? new Fill(null, null);


	    // Mapear A/B según el origen del segmento
	    $isA = ($seg->sourcePrimary === true);

	    // A_side = interior en A a ese lado, B_side = interior en B a ese lado
	    $A_above = (bool)($isA ? $my->above : $ot->above);
	    $A_below = (bool)($isA ? $my->below : $ot->below);
	    $B_above = (bool)($isA ? $ot->above : $my->above);
	    $B_below = (bool)($isA ? $ot->below : $my->below);

	    // Evaluar la operación por lado
	    $resAbove = (bool)$combine($A_above, $B_above); // interior a la IZQUIERDA del segmento
	    $resBelow = (bool)$combine($A_below, $B_below); // interior a la DERECHA del segmento


            // Trata null como false (fuera). Esto es robusto frente a divisiones/degenerados.
            //$A_above = (bool)($my->above);
            //$A_below = (bool)($my->below);
            //$B_above = (bool)($ot->above);
            //$B_below = (bool)($ot->below);

            // Evalúa la operación por lado
            //$resAbove = (bool)$combine($A_above, $B_above);
            //$resBelow = (bool)$combine($A_below, $B_below);

            // Una arista es frontera del resultado si separa interior/exterior
            if ($resAbove !== $resBelow) {


                // Propagamos un Fill “del resultado” (útil para fases posteriores)
                //$result[] = new Segment(
                //    start:  $seg->start,
                //    end:    $seg->end,
                //    myFill: new Fill($resBelow, $resAbove)
                //);

		$start = $seg->start;
		$end   = $seg->end;
		if ($resBelow && !$resAbove) { [$start, $end] = [$end, $start]; } // interior -> izquierda
		$result[] = new Segment(
		    start:         $start,
		    end:           $end,
		    myFill:        new Fill($resBelow, $resAbove),
		    otherFill:     null, // ya no se necesita en la frontera
		    sourcePrimary: $seg->sourcePrimary
		);

            }
        }

	print 'SEGMENTS after selectLogical: '.count($result) . PHP_EOL; // DEBUG
        return $result;
    }

    public static function selectUnion($combinedPolySegments):PolySegments
    {
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A || $B)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: ($combinedPolySegments->isInverted1 || $combinedPolySegments->isInverted2),
	    touchVertices:$combinedPolySegments->touchVertices ?? []   // ← ¡propagar!
        );
    }

    public static function selectIntersect($combinedPolySegments)
    {
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A && $B)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: ($combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2),
	    touchVertices:$combinedPolySegments->touchVertices ?? []   // ← ¡propagar!
        );
    }

    public static function selectDifference($combinedPolySegments)
    {
        // A \ B  ⇒  inside = A && !B
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A && !$B)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: ($combinedPolySegments->isInverted1 && !$combinedPolySegments->isInverted2),
	    touchVertices:$combinedPolySegments->touchVertices ?? []   // ← ¡propagar!
        );
    }

    public static function selectDifferenceRev($combinedPolySegments)
    {
        // B \ A  ⇒  inside = B && !A
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($B && !$A)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: (!$combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2),
	    touchVertices:$combinedPolySegments->touchVertices ?? []   // ← ¡propagar!
        );
    }

    public static function selectXor($combinedPolySegments)
    {
        // XOR  ⇒  inside = A xor B
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A xor $B)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: ($combinedPolySegments->isInverted1 != $combinedPolySegments->isInverted2),
	    touchVertices:$combinedPolySegments->touchVertices ?? []   // ← ¡propagar!
        );
    }




















    public static function arrays_are_equal(array $a1, array $a2): bool {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	if (count($a1) !== count($a2)) {
	    return false; // Si tienen diferentes tamaños, no son iguales
	}
	foreach ($a1 as $key => $value) {
	    if (!array_key_exists($key, $a2)) {
		return false; // Si una clave falta en $a2, no son iguales
	    }
	    if (is_array($value) && is_array($a2[$key])) {
		if (!self::arrays_are_equal($value, $a2[$key])) {
		    return false; // Llamada recursiva para comparar subarrays
		}
	    } elseif ($value != $a2[$key]) {
		return false; // Si los valores son distintos, no son iguales
	    }
	}
	return true; // Si todo coincide, los arrays son iguales
    }

}

