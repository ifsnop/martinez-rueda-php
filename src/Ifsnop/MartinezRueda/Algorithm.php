<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Algorithm {
    public const TOLERANCE = 1e-12; //12; //9;
    public const DEBUG = false;

    public static function reverseChain(&$chains, int $index) {
	$chains[$index] = array_reverse($chains[$index]);
    }

    public static function appendChain(&$chains, int $index1, int $index2) {

	// Snapshot rápido para logging sin tocar referencias
        $exists1 = array_key_exists($index1, $chains);
        $exists2 = array_key_exists($index2, $chains);

	// Si alguno no existe, no sigas: el problema está en el caller
        if (!$exists1 || !$exists2) {
            return; // Solo para depurar: evita notice y nos deja pista
        }

	$chain1 = &$chains[$index1];
        $chain2 = &$chains[$index2];

        $tail  = end($chain1);
	$tail2 = $chain1[count($chain1)-2];
        $head  = $chain2[0];
        $head2 = $chain2[1];

	if (Point::collinear($tail2, $tail, $head)) {
	    array_pop($chain1);
    	    $tail = $tail2;
        }

	if (Point::collinear($tail, $head, $head2)) {
    	    // if (Algorithm::DEBUG) echo "  collinear(tail,head,head2): shift head of chain2\n";
	    array_shift($chain2);
        }

	$chains[$index1] = array_merge($chain1, $chain2);
        array_splice($chains, $index2, 1);
    }

private static function makePolySegments(array $segments, bool $isInverted): PolySegments
{
    return new PolySegments(
        segments: $segments,
        isInverted: $isInverted,
        bounds: self::segmentsBounds($segments)
    );
}

private static function emptyPolySegments(bool $isInverted = false): PolySegments
{
    return new PolySegments([], $isInverted, null);
}

private static function segmentsBounds(array $segments): ?array
{
    if (empty($segments)) {
        return null;
    }

    $minX = INF;
    $minY = INF;
    $maxX = -INF;
    $maxY = -INF;

    foreach ($segments as $s) {
        if ($s->minX < $minX) $minX = $s->minX;
        if ($s->minY < $minY) $minY = $s->minY;
        if ($s->maxX > $maxX) $maxX = $s->maxX;
        if ($s->maxY > $maxY) $maxY = $s->maxY;
    }

    return [$minX, $minY, $maxX, $maxY];
}

private static function boundsOverlap(?array $a, ?array $b): bool
{
    if ($a === null || $b === null) {
        return false;
    }

    return !(
        $a[2] < $b[0] ||
        $b[2] < $a[0] ||
        $a[3] < $b[1] ||
        $b[3] < $a[1]
    );
}

private static function mergeBounds(?array $a, ?array $b): ?array
{
    if ($a === null) return $b;
    if ($b === null) return $a;

    return [
        min($a[0], $b[0]),
        min($a[1], $b[1]),
        max($a[2], $b[2]),
        max($a[3], $b[3]),
    ];
}



public static function segmentChainer(array $segments): array {
    $regions = [];
    $chains = [];

    foreach ($segments as $k => $segment) {
        $point1 = $segment->start;
        $point2 = $segment->end;
        if ($point1->__eq($point2)) {
            //if (Algorithm::DEBUG) echo "Skip zero-length segment at k=$k\n";
            continue;
        }

        $segmentChainerMatcher = new SegmentChainerMatcher();

	$nchains = count($chains);
        for ($i = 0; $i < $nchains; $i++) {
            $chain = &$chains[$i];
            $head = $chain[0];
            $tail = end($chain);

            if ($head->__eq($point1)) {
                //if (Algorithm::DEBUG) echo "  MATCH head==P1 with chain[$i]\n";
                if ($segmentChainerMatcher->setMatch($i, true, true)) { break; }
            } elseif ($head->__eq($point2)) {
                //if (Algorithm::DEBUG) echo "  MATCH head==P2 with chain[$i]\n";
                if ($segmentChainerMatcher->setMatch($i, true, false)) { break; }
            } elseif ($tail->__eq($point1)) {
                //if (Algorithm::DEBUG) echo "  MATCH tail==P1 with chain[$i]\n";
                if ($segmentChainerMatcher->setMatch($i, false, true)) { break; }
            } elseif ($tail->__eq($point2)) {
                //if (Algorithm::DEBUG) echo "  MATCH tail==P2 with chain[$i]\n";
                if ($segmentChainerMatcher->setMatch($i, false, false)) { break; }
            }
        }

        // 0 matches: crea nueva cadena
        if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->firstMatch) {
            $chains[] = [$point1, $point2];
            continue;
        }

        // 1 match: extiende cadena
        if ($segmentChainerMatcher->nextMatch === $segmentChainerMatcher->secondMatch) {
            $index = $segmentChainerMatcher->firstMatch->index;
            $point = $segmentChainerMatcher->firstMatch->matchesPt1 ? $point2 : $point1;
            $addToHead = $segmentChainerMatcher->firstMatch->matchesHead;

            $chain = &$chains[$index];

            $grow  = $addToHead ? $chain[0]               : end($chain);
            $grow2 = $addToHead ? $chain[1]               : $chain[count($chain)-2];
            $opposite  = $addToHead ? end($chain)         : $chain[0];
            $opposite2 = $addToHead ? $chain[count($chain)-2] : $chain[1];

            if (Point::collinear($grow2, $grow, $point)) {
                //if (Algorithm::DEBUG) echo "     collinear(grow2,grow,point): trimming " . ($addToHead?'HEAD':'TAIL') . "\n";
                if ($addToHead) { array_shift($chain); } else { array_pop($chain); }
                $grow = $grow2;
            }

            if ($opposite == $point) {
                //if (Algorithm::DEBUG) echo "     OPPOSITE==POINT: CLOSE REGION (before trim)\n";
                array_splice($chains, $index, 1);

                if (Point::collinear($opposite2, $opposite, $grow)) {
                    //if (Algorithm::DEBUG) echo "     collinear(opposite2,opposite,grow): trimming " . ($addToHead?'TAIL':'HEAD') . "\n";
                    if ($addToHead) { array_pop($chain); } else { array_shift($chain); }
                }
                //if (Algorithm::DEBUG) echo "     CLOSED REGION: " . regionStr($chain) . "\n";
                $regions[] = $chain;
                continue;
            }

            if ($addToHead) {
                $ret = array_unshift($chain, $point);
            } else {
                $chain[] = $point;
            }
            //if (Algorithm::DEBUG) echo "     chain[$index] now " . chainEndsStr($chain) . "\n";
            continue;
        }

        // 2 matches: unir cadenas
        $firstIndex  = $segmentChainerMatcher->firstMatch->index;
        $secondIndex = $segmentChainerMatcher->secondMatch->index;

        $reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);

        if ($segmentChainerMatcher->firstMatch->matchesHead) {
            if ($segmentChainerMatcher->secondMatch->matchesHead) {
                if ($reverseFirst) {
                    //if (Algorithm::DEBUG) echo "     CASE: head + head (reverse FIRST) -> appendChain(first, second)\n";
                    self::reverseChain($chains, $firstIndex);
                    self::appendChain($chains, $firstIndex, $secondIndex);
                } else {
                    //if (Algorithm::DEBUG) echo "     CASE: head + head (reverse SECOND) -> appendChain(second, first)\n";
                    self::reverseChain($chains, $secondIndex);
                    self::appendChain($chains, $secondIndex, $firstIndex);
                }
            } else {
                //if (Algorithm::DEBUG) echo "     CASE: head + tail -> appendChain(second, first)\n";
                self::appendChain($chains, $secondIndex, $firstIndex);
            }
        } else {
            if ($segmentChainerMatcher->secondMatch->matchesHead) {
                //if (Algorithm::DEBUG) echo "     CASE: tail + head -> appendChain(first, second)\n";
                self::appendChain($chains, $firstIndex, $secondIndex);
            } else {
                if ($reverseFirst) {
                    //if (Algorithm::DEBUG) echo "     CASE: tail + tail (reverse FIRST) -> appendChain(second, first)\n";
                    self::reverseChain($chains, $firstIndex);
                    self::appendChain($chains, $secondIndex, $firstIndex);
                } else {
                    //if (Algorithm::DEBUG) echo "     CASE: tail + tail (reverse SECOND) -> appendChain(first, second)\n";
                    self::reverseChain($chains, $secondIndex);
                    self::appendChain($chains, $firstIndex, $secondIndex);
                }
            }
        }
    }
    return $regions;
}

    // core API
    public static function segments($polygon): PolySegments
    {
    $regionIntersecter = new RegionIntersecter();

    foreach ($polygon->regions as $region) {
        $regionIntersecter->addRegion($region);
    }

    $segments = $regionIntersecter->calculate2($polygon->isInverted);

    return self::makePolySegments($segments, $polygon->isInverted);
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

private static function combineSelect(
    PolySegments $a,
    PolySegments $b,
    string $operation
): PolySegments {
    $segmentIntersecter = new SegmentIntersecter();

    $combined = $segmentIntersecter->calculate2(
        $a->segments,
        $a->isInverted,
        $b->segments,
        $b->isInverted
    );

    return match ($operation) {
        'union' => self::makePolySegments(
            self::__selectUnionLogical($combined),
            $a->isInverted || $b->isInverted
        ),

        'intersect' => self::makePolySegments(
            self::__selectLogical($combined, fn(bool $A, bool $B) => $A && $B),
            $a->isInverted && $b->isInverted
        ),

        'difference' => self::makePolySegments(
            self::__selectLogical($combined, fn(bool $A, bool $B) => $A && !$B),
            $a->isInverted && !$b->isInverted
        ),

        'differenceRev' => self::makePolySegments(
            self::__selectLogical($combined, fn(bool $A, bool $B) => $B && !$A),
            !$a->isInverted && $b->isInverted
        ),

        'xor' => self::makePolySegments(
            self::__selectLogical($combined, fn(bool $A, bool $B) => $A xor $B),
            $a->isInverted != $b->isInverted
        ),

        default => throw new \InvalidArgumentException("Operación no soportada: $operation"),
    };
}

private static function unionSegments(PolySegments $a, PolySegments $b): PolySegments
{
    if (
        !$a->isInverted &&
        !$b->isInverted &&
        !self::boundsOverlap($a->bounds, $b->bounds)
    ) {
        return new PolySegments(
            segments: array_merge($a->segments, $b->segments),
            isInverted: false,
            bounds: self::mergeBounds($a->bounds, $b->bounds)
        );
    }

    return self::combineSelect($a, $b, 'union');
}

private static function intersectSegments(PolySegments $a, PolySegments $b): PolySegments
{
    if (
        !$a->isInverted &&
        !$b->isInverted &&
        !self::boundsOverlap($a->bounds, $b->bounds)
    ) {
        return self::emptyPolySegments(false);
    }

    return self::combineSelect($a, $b, 'intersect');
}

private static function xorSegments(PolySegments $a, PolySegments $b): PolySegments
{
    if (
        !$a->isInverted &&
        !$b->isInverted &&
        !self::boundsOverlap($a->bounds, $b->bounds)
    ) {
        return new PolySegments(
            segments: array_merge($a->segments, $b->segments),
            isInverted: false,
            bounds: self::mergeBounds($a->bounds, $b->bounds)
        );
    }

    return self::combineSelect($a, $b, 'xor');
}

private static function differenceSegments(PolySegments $a, PolySegments $b): PolySegments
{
    if (
        !$a->isInverted &&
        !$b->isInverted &&
        !self::boundsOverlap($a->bounds, $b->bounds)
    ) {
        return $a;
    }

    return self::combineSelect($a, $b, 'difference');
}

private static function differenceRevSegments(PolySegments $a, PolySegments $b): PolySegments
{
    if (
        !$a->isInverted &&
        !$b->isInverted &&
        !self::boundsOverlap($a->bounds, $b->bounds)
    ) {
        return $b;
    }

    return self::combineSelect($a, $b, 'differenceRev');
}

private static function reduceBalanced(array $items, callable $op): PolySegments
{
    if (empty($items)) {
        return self::emptyPolySegments(false);
    }

    while (count($items) > 1) {
        $next = [];
        $n = count($items);

        for ($i = 0; $i < $n; $i += 2) {
            if ($i + 1 >= $n) {
                $next[] = $items[$i];
                continue;
            }

            $next[] = $op($items[$i], $items[$i + 1]);
        }

        $items = $next;
    }

    return $items[0];
}
private static function polygonsToSegments(array $polygons): array
{
    $items = [];

    foreach ($polygons as $polygon) {
        $items[] = self::segments($polygon);
    }

    usort($items, function (PolySegments $a, PolySegments $b): int {
        $ba = $a->bounds;
        $bb = $b->bounds;

        if ($ba === null && $bb === null) return 0;
        if ($ba === null) return 1;
        if ($bb === null) return -1;

        return ($ba[0] <=> $bb[0]) ?: ($ba[1] <=> $bb[1]);
    });

    return $items;
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
	// 1) Construye cadenas (como ya haces)
	$s = self::segmentChainer($segments->segments);
	// 2) POST-PROCESO: parte anillos auto-tocados en ciclos simples
	$s = self::splitSelfTouchingRegions($s);
	// 3) Construye el polígono final
	$p = Polygon::create()->fillFromArray($s, $segments->isInverted);
	return $p;
    }

public static function __operate($polygon1, $polygon2, $selector): Polygon
{
    $first = self::segments($polygon1);
    $second = self::segments($polygon2);

    $operation = match ($selector) {
        'selectUnion' => 'union',
        'selectIntersect' => 'intersect',
        'selectDifference' => 'difference',
        'selectDifferenceRev' => 'differenceRev',
        'selectXor' => 'xor',
        default => throw new \InvalidArgumentException("Selector no soportado: $selector"),
    };

    $result = match ($operation) {
        'union' => self::unionSegments($first, $second),
        'intersect' => self::intersectSegments($first, $second),
        'difference' => self::differenceSegments($first, $second),
        'differenceRev' => self::differenceRevSegments($first, $second),
        'xor' => self::xorSegments($first, $second),
    };

    return self::polygon($result);
}

    public static function splitSelfTouchingRegions(array $regions): array {
	// Recorre cada región (anillo) y parte si hay puntos repetidos
	$out = [];
	foreach ($regions as $ring) {
    	    foreach (self::__splitRegionAtDuplicates($ring) as $r) {
        	// guarda solo ciclos con al menos 3 puntos
        	if (count($r) >= 3) { $out[] = $r; }
    	    }
	}
	//if (Algorithm::DEBUG) {
    	//    echo "splitSelfTouchingRegions: in=" . count($regions) . " out=" . count($out) . PHP_EOL;
	//}
	return $out;
    }



    /**
     * Recibe un anillo (array de Point, SIN punto final repetido) y:
     *  - si no tiene puntos repetidos internos → lo devuelve tal cual [ [$ring] ]
     *  - si encuentra un punto repetido en posiciones i<j → lo parte en dos ciclos:
     *        ring1 = ring[i..j]   (sin repetir el punto final)
     *        ring2 = ring[j..end] + ring[0..i]   (sin repetir el punto final)
     *    y aplica el mismo proceso recursivamente a cada subciclo.
     *
     * @param Point[] $ring
     * @return array<int, array<int, Point>>  lista de subciclos
     */
    private static function __splitRegionAtDuplicates(array $ring): array {
	$n = count($ring);
	if ($n < 3) {
    	    // Degenerado: no es un ciclo válido, devolver tal cual
    	    return [$ring];
	}

	// Busca el PRIMER par (i,j) con ring[i] == ring[j], i<j
	for ($i = 0; $i < $n - 2; $i++) {
    	    for ($j = $i + 1; $j < $n; $j++) {
        	if ($ring[$i]->__eq($ring[$j])) {
            	    // División en dos ciclos

            	    // Ciclo 1: [i .. j] (quitar el último si repite el primero)
            	    $ring1 = array_slice($ring, $i, $j - $i + 1);
            	    if (count($ring1) >= 2 && end($ring1)->__eq($ring1[0])) {
                	array_pop($ring1);
            	    }

            	    // Ciclo 2: [j .. end] + [0 .. i] (quitar el último si repite el primero)
            	    $part1 = array_slice($ring, $j, $n - $j);
        	    $part2 = array_slice($ring, 0, $i + 1); // incluye el punto i
        	    $ring2 = array_merge($part1, $part2);
        	    if (count($ring2) >= 2 && end($ring2)->__eq($ring2[0])) {
        	        array_pop($ring2);
        	    }
            	    // Recursivo por si aún quedan más puntos repetidos en alguno
    	            $out = [];
        	    foreach (self::__splitRegionAtDuplicates($ring1) as $r1) {
            	        if (count($r1) >= 3) $out[] = $r1;
            	    }
    	            foreach (self::__splitRegionAtDuplicates($ring2) as $r2) {
            	        if (count($r2) >= 3) $out[] = $r2;
            	    }
            	    return $out;
        	}
    	    }
	}

	// Si no hay puntos repetidos internos, devolver el anillo tal cual
	return [$ring];
    }


    // helper functions for common operations
public static function union(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons = $args[0];

        if (empty($polygons)) {
            return Polygon::create()->fillFromArray([], true);
        }

        $items = self::polygonsToSegments($polygons);

        $result = self::reduceBalanced(
            $items,
            fn(PolySegments $a, PolySegments $b) => self::unionSegments($a, $b)
        );

        return self::polygon($result);
    }

    if (
        count($args) === 2 &&
        is_a($args[0], Polygon::class) &&
        is_a($args[1], Polygon::class)
    ) {
        return self::__operate($args[0], $args[1], 'selectUnion');
    }

    return Polygon::create()->fillFromArray([], true);
}

public static function intersection(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons = $args[0];

        if (empty($polygons)) {
            return Polygon::create()->fillFromArray([], false);
        }

        $items = self::polygonsToSegments($polygons);

        $result = self::reduceBalanced(
            $items,
            fn(PolySegments $a, PolySegments $b) => self::intersectSegments($a, $b)
        );

        return self::polygon($result);
    }

    if (count($args) === 2) {
        return self::__operate($args[0], $args[1], 'selectIntersect');
    }

    return Polygon::create()->fillFromArray([], false);
}

public static function intersect(...$args): Polygon
{
    return self::intersection(...$args);
}

public static function xoring(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons = $args[0];

        if (empty($polygons)) {
            return Polygon::create()->fillFromArray([], false);
        }

        $items = self::polygonsToSegments($polygons);

        $result = self::reduceBalanced(
            $items,
            fn(PolySegments $a, PolySegments $b) => self::xorSegments($a, $b)
        );

        return self::polygon($result);
    }

    if (count($args) === 2) {
        return self::__operate($args[0], $args[1], 'selectXor');
    }

    return Polygon::create()->fillFromArray([], false);
}

public static function difference(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons = $args[0];

        if (empty($polygons)) {
            return Polygon::create()->fillFromArray([], false);
        }

        if (count($polygons) === 1) {
            return $polygons[0];
        }

        $base = self::segments($polygons[0]);

        $subtractors = array_slice($polygons, 1);
        $subtractorsSegments = self::polygonsToSegments($subtractors);

        $subtractorUnion = self::reduceBalanced(
            $subtractorsSegments,
            fn(PolySegments $a, PolySegments $b) => self::unionSegments($a, $b)
        );

        $result = self::differenceSegments($base, $subtractorUnion);

        return self::polygon($result);
    }

    if (count($args) === 2) {
        return self::__operate($args[0], $args[1], 'selectDifference');
    }

    return Polygon::create()->fillFromArray([], false);
}

// el último menos todos los anteriores
public static function differenceRev(...$args): Polygon
{
    if (count($args) === 1 && is_array($args[0])) {
        $polygons = $args[0];

        if (empty($polygons)) {
            return Polygon::create()->fillFromArray([], false);
        }

        if (count($polygons) === 1) {
            return $polygons[0];
        }

        $lastIndex = count($polygons) - 1;

        $base = self::segments($polygons[$lastIndex]);

        $subtractors = array_slice($polygons, 0, $lastIndex);
        $subtractorsSegments = self::polygonsToSegments($subtractors);

        $subtractorUnion = self::reduceBalanced(
            $subtractorsSegments,
            fn(PolySegments $a, PolySegments $b) => self::unionSegments($a, $b)
        );

        $result = self::differenceSegments($base, $subtractorUnion);

        return self::polygon($result);
    }

    if (count($args) === 2) {
        return self::__operate($args[0], $args[1], 'selectDifferenceRev');
    }

    return Polygon::create()->fillFromArray([], false);
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

}

