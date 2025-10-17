<?php

namespace Ifsnop\MartinezRueda;

class Algorithm {
    public const TOLERANCE = 1e-9;
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

	if (false) print("NEWTEST") . PHP_EOL;
	if (false) print_r($segments);

	foreach ($segments as $k => $segment) {
	    if (false) print __METHOD__ . "::segment=BEGIN k($k) count(segments)=" . count($segments) . PHP_EOL;
	    if (false) print_r($segment);
	    $point1 = $segment->start;
	    $point2 = $segment->end;
	    if ($point1 == $point2) {
		if (false) print "point1 == point2" . PHP_EOL;
		continue;
	    }

	    $segmentChainerMatcher = new SegmentChainerMatcher();

	    for ($i = 0; $i < count($chains); $i++) {
		if (false) print "for $i count(chains)=" . count($chains) . PHP_EOL;
		if (false) print __METHOD__ . "::chains=BEGIN ($i)" . PHP_EOL;
		if (false) var_dump($chains);
		$chain = &$chains[$i];

		$head = $chain[0];
		$tail = end($chain);
		if (false) print __METHOD__ . "::HEAD=BEGIN ($i)";
		if (false) print_r($head);
		if (false) print __METHOD__ . "::POINT1=BEGIN ($i)";
		if (false) print_r($point1);
		if ($head == $point1) {
		    if ($segmentChainerMatcher->setMatch($i, true, true)) {
			if (false) print __METHOD__ . "::A1 i($i)" . PHP_EOL;
			break;
		    }
		    if (false) print __METHOD__ . "::A2 i($i)" . PHP_EOL;
		} elseif ($head == $point2) {
		    if ($segmentChainerMatcher->setMatch($i, true, false)) {
			if (false) print __METHOD__ . "::A3 i($i)" . PHP_EOL;
			break;
		    }
		    if (false) print __METHOD__ . "::A4 i($i)" . PHP_EOL;
		} elseif ($tail == $point1) {
		    if ($segmentChainerMatcher->setMatch($i, false, true)) {
			if (false) print __METHOD__ . "::A5 i($i)" . PHP_EOL;
			break;
		    }
		    if (false) print __METHOD__ . "::A6 i($i)" . PHP_EOL;
		} elseif ($tail == $point2) {
		    if ($segmentChainerMatcher->setMatch($i, false, false)) {
			if (false) print __METHOD__ . "::A7 i($i)" . PHP_EOL;
			break;
		    }
		    if (false) print __METHOD__ . "::A8 i($i)" . PHP_EOL;
		}
		if (false) print __METHOD__ . "::A9 i($i)" . PHP_EOL;
		if (false) print __METHOD__ . "::segmentChainerMatcher=BEGIN ($i)" . PHP_EOL;
		if (false) var_dump($segmentChainerMatcher);
		if (false) print __METHOD__ . "::segmentChainerMatcher=END ($i)" . PHP_EOL;
	    }

	    if (false) print __METHOD__ . "::A10 k($k)" . PHP_EOL;
	    if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->firstMatch) {
		if (false) print __METHOD__ . "::A11 k($k)" . PHP_EOL;
		if (false) print __METHOD__ . "::chains=APPEND k($k)" . PHP_EOL;
		$chains[] = [$point1, $point2];
		if (false) print __METHOD__ . "::chains=BEGIN k($k)" . PHP_EOL;
		if (false) print_r($chains);
		if (false) print __METHOD__ . "::chains=END k($k)" . PHP_EOL;
		continue;
	    }

	    if (false) print __METHOD__ . "::A12 k($k)" . PHP_EOL;
	    if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->secondMatch) {
		if (false) print __METHOD__ . "::A13 k($k)" . PHP_EOL;
		$index = $segmentChainerMatcher->firstMatch->index;
		$point = $segmentChainerMatcher->firstMatch->matchesPt1 ? $point2 : $point1;
		$addToHead = $segmentChainerMatcher->firstMatch->matchesHead;

		if (false) print __METHOD__ . "::addToHead=" . $addToHead . "  k($k)" . PHP_EOL;

		$chain = &$chains[$index];
		$grow = $addToHead ? $chain[0] : end($chain);
		$grow2 = $addToHead ? $chain[1] : $chain[count($chain) - 2];
		$opposite = $addToHead ? end($chain) : $chain[0];
		$opposite2 = $addToHead ? $chain[count($chain) - 2] : $chain[1];
		if (false) print __METHOD__ . "::A14 k($k)" . PHP_EOL;
		if (Point::collinear($grow2, $grow, $point)) {
		    if (false) print __METHOD__ . "::A15 k($k)" . PHP_EOL;
		    if ($addToHead) {
			if (false) print __METHOD__ . "::A16 k($k)" . PHP_EOL;
			array_shift($chain);
		    } else {
			if (false) print __METHOD__ . "::A17 k($k)" . PHP_EOL;
			array_pop($chain);
		    }
		    $grow = $grow2;
		    if (false) print __METHOD__ . "::A18 k($k)" . PHP_EOL;
		}
		if (false) print __METHOD__ . "::A19 k($k)" . PHP_EOL;
		if ($opposite == $point) {
		    if (false) print __METHOD__ . "::A20 k($k)" . PHP_EOL;
		    array_splice($chains, $index, 1);
		    if (Point::collinear($opposite2, $opposite, $grow)) {
			if (false) print __METHOD__ . "::A21 k($k)" . PHP_EOL;
			if ($addToHead) {
			    if (false) print __METHOD__ . "::A22 k($k)" . PHP_EOL;
			    array_pop($chain);
			} else {
			    if (false) print __METHOD__ . "::A23 k($k)" . PHP_EOL;
			    array_shift($chain);
			}
		    }
		    if (false) print __METHOD__ . "::A24 k($k)" . PHP_EOL;
		    $regions[] = $chain;
		    continue;
		}
		if (false) print __METHOD__ . "::A25 k($k)" . PHP_EOL;
		if (false) print __METHOD__ . "::chains BEGIN scm.next is scrm.secondmatch3 k($k)" . PHP_EOL;
		if (false) var_dump($chains);

		if ($addToHead) {
		    if (false) print __METHOD__ . "::A26 k($k)" . PHP_EOL;
		    if (false) print_r($point);
		    if (false) var_dump($chain);
		    $ret = array_unshift($chain, $point);
		    if (false) var_dump($chain);
		    if (false) print "RET=$ret" . PHP_EOL;
		    if (false) print __METHOD__ . "::chains BEGIN scm.next is scrm.secondmatch4 k($k)" . PHP_EOL;
		    if (false) var_dump($chains);
		} else {
		    if (false) print __METHOD__ . "::A27 k($k)" . PHP_EOL;
		    $chain[] = $point;
		}
		if (false) print __METHOD__ . "::A28 k($k) count(chains)=" . count($chains) . PHP_EOL;
		continue; // esto cambia de segmento
	    }
	    if (false) print __METHOD__ . "::A29 k($k)" . PHP_EOL;
	    if (false) print __METHOD__ . "::A30 k($k)" . PHP_EOL;
	    $firstIndex = $segmentChainerMatcher->firstMatch->index;
	    $secondIndex = $segmentChainerMatcher->secondMatch->index;

	    $reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);
	    if ($segmentChainerMatcher->firstMatch->matchesHead) {
		if (false) print __METHOD__ . "::A31 k($k)" . PHP_EOL;
		if ($segmentChainerMatcher->secondMatch->matchesHead) {
		    if (false) print __METHOD__ . "::A32 k($k)" . PHP_EOL;
		    if ($reverseFirst) {
			if (false) print __METHOD__ . "::A33 k($k)" . PHP_EOL;
			if (false) print_r($chains[$firstIndex]);
			self::reverseChain($chains, $firstIndex);
			if (false) print_r($chains[$firstIndex]);
			self::appendChain($chains, $firstIndex, $secondIndex);
		    } else {
			if (false) print __METHOD__ . "::A34 k($k)" . PHP_EOL;
			self::reverseChain($chains, $secondIndex);
			self::appendChain($chains, $secondIndex, $firstIndex);
		    }
		    if (false) print __METHOD__ . "::A35 k($k)" . PHP_EOL;
		} else {
		    if (false) print __METHOD__ . "::A36 k($k)" . PHP_EOL;
		    self::appendChain($chains, $secondIndex, $firstIndex);
		}
	    } else {
		if (false) print __METHOD__ . "::A37 k($k)" . PHP_EOL;
		if ($segmentChainerMatcher->secondMatch->matchesHead) {
		    if (false) print __METHOD__ . "::A38 k($k)" . PHP_EOL;
		    self::appendChain($chains, $firstIndex, $secondIndex);
		} else {
		    if (false) print __METHOD__ . "::A39 k($k)" . PHP_EOL;
		    if ($reverseFirst) {
			if (false) print __METHOD__ . "::A40 k($k)" . PHP_EOL;
			self::reverseChain($chains, $firstIndex);
			self::appendChain($chains, $secondIndex, $firstIndex);
		    } else {
			if (false) print __METHOD__ . "::A41 k($k)" . PHP_EOL;
			if (false) print_r($chains[$secondIndex]);
			self::reverseChain($chains, $secondIndex);
			if (false) print_r($chains[$secondIndex]);
			self::appendChain($chains, $firstIndex, $secondIndex);
		    }
		    if (false) print __METHOD__ . "::A42 k($k)" . PHP_EOL;
		}
		if (false) print __METHOD__ . "::A43 k($k)" . PHP_EOL;
	    }
	    if (false) print __METHOD__ . "::A44 k($k)" . PHP_EOL;
	}
	if (false) print __METHOD__ . "::A45 k($k)" . PHP_EOL;
	if (false) print __METHOD__ . "::regions=BEGIN" . PHP_EOL;
	if (false) print_r($regions);
	if (false) print __METHOD__ . "::regions=END" . PHP_EOL;
	return $regions;
    }

    public static function __select(array $segments, array $selection): array {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	//print_r($segments); // IFSNOP
	//print_r($selection);
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

    public static function selectUnion($combinedPolySegments) {
	return new PolySegments(
	    segments: self::__select(
		$combinedPolySegments->combined, [
		    0, 2, 1, 0,
		    2, 2, 0, 0,
		    1, 0, 1, 0,
		    0, 0, 0, 0,
		]
	    ),
	    isInverted: ($combinedPolySegments->isInverted1 || $combinedPolySegments->isInverted2)
	);
    }

    public static function selectIntersect($combinedPolySegments) {
	return new PolySegments(
	    segments: self::__select(
		$combinedPolySegments->combined, [
		    0, 0, 0, 0,
		    0, 2, 0, 2,
		    0, 0, 1, 1,
		    0, 2, 1, 0
		]
	    ),
	    isInverted: ($combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
	);
    }

    public static function selectDifference($combinedPolySegments) {
	return new PolySegments(
	    segments: self::__select(
		$combinedPolySegments->combined, [
		    0, 0, 0, 0,
		    2, 0, 2, 0,
		    1, 1, 0, 0,
		    0, 1, 2, 0
		]
	    ),
	    isInverted: ($combinedPolySegments->isInverted1 && !$combinedPolySegments->isInverted2)
	);
    }

    public static function selectDifferenceRev($combinedPolySegments) {
	return new PolySegments(
	    segments: self::__select(
		$combinedPolySegments->combined, [
		    0, 2, 1, 0,
		    0, 0, 1, 1,
		    0, 2, 0, 2,
		    0, 0, 0, 0
		]
	    ),
	    isInverted: (!$combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
	);
    }

    public static function selectXor($combinedPolySegments) {
	return new PolySegments(
	    segments: self::__select(
		$combinedPolySegments->combined, [
		    0, 2, 1, 0,
		    2, 0, 0, 1,
		    1, 0, 0, 2,
		    0, 1, 2, 0
		]
	    ),
	    isInverted: ($combinedPolySegments->isInverted1 != $combinedPolySegments->isInverted2)
	);
    }

    public static function polygon($segments) {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	$s = self::segmentChainer($segments->segments);
	$p = Polygon::create()->fillFromArray($s , $segments->isInverted);
	return $p;
    }

    public static function __operate($polygon1, $polygon2, $selector) {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	$firstPolygonRegions = self::segments($polygon1);
	$secondPolygonRegions = self::segments($polygon2);
	$combinedSegments = self::combine($firstPolygonRegions, $secondPolygonRegions);
	$selectedSegments = self::$selector($combinedSegments);
	$p = self::polygon($selectedSegments);
	return $p;
    }

    // helper functions for common operations
    public static function union(...$args):Polygon {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
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

    public static function arrays_are_similar(array $a1, array $a2): bool {
	if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	if (count($a1) !== count($a2)) {
	    // print "distinto tamaño " . count($a1) .  " " . count($a2) . PHP_EOL;
	    return false; // Si tienen diferentes tamaños, no son iguales
	}

	foreach ($a1 as $key => $value) {
	    if (!array_key_exists($key, $a2)) {
		// print "falta clave" . PHP_EOL;
		return false; // Si una clave falta en $a2, no son iguales
	    }

	    if (is_array($value) && is_array($a2[$key])) {
		if (!arrays_are_similar($value, $a2[$key])) {
		    return false; // Llamada recursiva para comparar subarrays
		}
	    } else {
		$diff = abs($value - $a2[$key]);
		if ( $diff > self::$tolerance ) {
		    // print $diff . PHP_EOL;
		    return false; // Si los valores son distintos, no son iguales
		}
	    }
	}
	return true; // Si todo coincide, los arrays son iguales
    }
}

/*

$region_a = [[[0,0], [0,1], [1,1], [1,0]]];
$region_b = [[[1,0], [2,0], [2,1], [1,1]]];
$result_union = [[[2,1], [2,0], [0,0], [0,1]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa, $pb)->getArray();
if ( arrays_are_equal($result, $result_union) ) {
    print "OK 1" . PHP_EOL;
}

$region_a = [[[0,0],[-1,0],[-1,1],[0,1],[-0.5,0.5]]];
$region_b = [[[0,0],[-2,2],[0,2]]];
$result_union = [[[0,2],[0,0],[-1,0],[-1,1],[-2,2]]];
$result_intersect = [[[-1,1],[-0.5,0.5],[0,1]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa, $pb)->getArray();
if ( arrays_are_equal($result, $result_union) ) {
    print "OK 2" . PHP_EOL;
}
$result = intersect($pa, $pb)->getArray();
if ( arrays_are_equal($result, $result_intersect) ) {
    print "OK 3" . PHP_EOL;
}

$region_a = [[[-5.69091796875,75.50265886674975],[-6.218261718749999,75.29215785826014],[-6.87744140625,74.8219342035653],[-5.38330078125,74.61344527005673],[-3.27392578125,74.78737860165963],[-2.83447265625,75.26423875224219],[-3.251953125,75.59040636514479],[-5.69091796875,75.50265886674975]]];
$region_b = [[[-1.4501953125,75.1125778338579],[-1.9116210937499998,75.40331785380344],[-3.2958984375,75.49165372814439],[-3.80126953125,75.33672086232664],[-5.5810546875,74.95939165894974],[-7.31689453125,74.62510096387147],[-5.515136718749999,74.15208909789665],[-4.19677734375,74.86215220305225],[-2.373046875,74.55503734449476],[-1.4501953125,75.1125778338579]]];
$result_union = [[[-1.4501953125,75.1125778338579],[-2.373046875,74.55503734449476],[-3.5953601631730767,74.7608739958216],[-4.527530738644315,74.68400974275426],[-5.515136718749999,74.15208909789665],[-7.31689453125,74.62510096387147],[-6.539602834083374,74.77479298912367],[-6.87744140625,74.8219342035653],[-6.218261718749999,75.29215785826014],[-5.69091796875,75.50265886674975],[-3.251953125,75.59040636514479],[-3.110402964367213,75.47981657363235],[-1.9116210937499998,75.40331785380344]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa,$pb)->getArray();
if ( arrays_are_similar($result,$result_union) ) {
    print "OK 4" . PHP_EOL;
}



$region_a = [[[-4.1748046875, 75.52464464250062], [-6.701660156249999, 75.52464464250062], [-6.74560546875, 74.44346576284508], [-3.75732421875, 74.44935750063425], [-3.7353515625, 74.76429887097666], [-4.8779296875, 74.76718570583334], [-4.866943359375, 75.30331101068566], [-3.8452148437499996, 75.30331101068566], [-3.8452148437499996, 75.52464464250062], [-4.1748046875, 75.52464464250062]]];
$region_b = [[[-4.383544921875, 75.59587329063447], [-4.427490234375, 74.36371391783985], [-2.6806640625, 74.36667478672423], [-2.65869140625, 75.59860599198842], [-4.383544921875, 75.59587329063447]]];
$result_union = [[[-4.393979238329456,75.30331101068566],[-4.413142181792476,74.7660113749556],[-4.8779296875,74.76718570583334],[-4.866943359375,75.30331101068566]],
    [[-2.65869140625,75.59860599198842],[-2.6806640625,74.36667478672423],[-4.427490234375,74.36371391783985],[-4.4244826451389665,74.44804212159751],[-6.74560546875,74.44346576284508],[-6.701660156249999,75.52464464250062],[-4.386085311755,75.52464464250062],[-4.383544921875,75.59587329063447]]];
$result_intersect = [[[-3.8452148437499996,75.52464464250062], [-3.8452148437499996,75.30331101068566], [-4.393979238329456,75.30331101068566], [-4.386085311755,75.52464464250062]],
    [[-3.7353515625,74.76429887097666], [-3.75732421875,74.44935750063425], [-4.4244826451389665,74.44804212159751], [-4.413142181792476,74.7660113749556]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa,$pb)->getArray();
print json_encode($result) . PHP_EOL;
if ( arrays_are_equal($result,$result_union) ) {
    print "OK 5" . PHP_EOL;
}
$result = intersect($pa,$pb)->getArray();
print json_encode($result) . PHP_EOL;
if ( arrays_are_equal($result,$result_intersect) ) {
    print "OK 6" . PHP_EOL;
}
*/
