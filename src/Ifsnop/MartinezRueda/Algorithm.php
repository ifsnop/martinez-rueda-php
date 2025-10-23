<?php

namespace Ifsnop\MartinezRueda;

class Algorithm {
    public const TOLERANCE = 1e-12; //9;
    public const DEBUG = false;

    public static function reverseChain(&$chains, int $index) {
	$chains[$index] = array_reverse($chains[$index]);
    }

    public static function appendChain(&$chains, int $index1, int $index2) {
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

    // core API
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

    /**
     * Selección específica para UNIÓN (A ∪ B) sin tabla.
     * Mantiene el segmento si (resAbove XOR resBelow), donde
     * resSide = (myFill.side || otherFill.side).
     * Además, fija myFill del segmento resultante como (below=resBelow, above=resAbove)
     * para que el chainer pueda entender la orientación del interior del resultado.
     */
    private static function __selectUnionLogical(array $segments): array
    {
        $result = [];

        foreach ($segments as $segment) {
            // Asegurar objetos Fill, tratar null como false
            $my = $segment->myFill ?? new Fill(null, null);
            $ot = $segment->otherFill;

            $myA = (bool)($my->above);
            $myB = (bool)($my->below);
            $otA = (bool)($ot?->above);
            $otB = (bool)($ot?->below);

            // Interior del resultado a cada lado
            $resA = ($myA || $otA);
            $resB = ($myB || $otB);

            if ($resA !== $resB) {
                // Conservar: es frontera de la unión.
                // Fijamos Fill para el resultado (útil para posteriores fases)
                $result[] = new Segment(
                    start:  $segment->start,
                    end:    $segment->end,
                    myFill: new Fill($resB, $resA) // below=resB, above=resA
                );
            }
        }

        return $result;
    }

    public static function polygon($segments) {
	$s = self::segmentChainer($segments->segments);
	$p = Polygon::create()->fillFromArray($s , $segments->isInverted);
	return $p;
    }

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

    public static function intersect($polygon1, $polygon2) {
	return self::__operate($polygon1, $polygon2, 'selectIntersect');
    }

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

            // Trata null como false (fuera). Esto es robusto frente a divisiones/degenerados.
            $A_above = (bool)($my->above);
            $A_below = (bool)($my->below);
            $B_above = (bool)($ot->above);
            $B_below = (bool)($ot->below);

            // Evalúa la operación por lado
            $resAbove = (bool)$combine($A_above, $B_above);
            $resBelow = (bool)$combine($A_below, $B_below);

            // Una arista es frontera del resultado si separa interior/exterior
            if ($resAbove !== $resBelow) {
                // Propagamos un Fill “del resultado” (útil para fases posteriores)
                $result[] = new Segment(
                    start:  $seg->start,
                    end:    $seg->end,
                    myFill: new Fill($resBelow, $resAbove)
                );
            }
        }
        return $result;
    }

    public static function selectUnion($combinedPolySegments)
    {
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A || $B)
        );
        return new PolySegments(
            segments:   $segments,
            isInverted: ($combinedPolySegments->isInverted1 || $combinedPolySegments->isInverted2)
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
            isInverted: ($combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
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
            isInverted: ($combinedPolySegments->isInverted1 && !$combinedPolySegments->isInverted2)
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
            isInverted: (!$combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
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
            isInverted: ($combinedPolySegments->isInverted1 != $combinedPolySegments->isInverted2)
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

