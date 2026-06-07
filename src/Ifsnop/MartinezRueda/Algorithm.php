<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

enum Selector: string
{
    case Union          = 'selectUnion';
    case Intersect      = 'selectIntersect';
    case Difference     = 'selectDifference';
    case DifferenceRev  = 'selectDifferenceRev';
    case Xor            = 'selectXor';
}

final class Algorithm
{
    public const TOLERANCE = 1e-12; //12; //9;
    public const DEBUG = false;

    private static function pointKey(Point $p): string
    {
        $inv = 1.0 / Algorithm::TOLERANCE;
        return (int)round($p->x * $inv) . ',' . (int)round($p->y * $inv);
    }

    /**
     * Invierte el orden de los nodos en una cadena y actualiza los índices correspondientes.
     */
    private static function reverseChainIdx(
        array &$chains,
        array &$headIndex,
        array &$tailIndex,
        int $id
    ): void {
        $oldHeadKey = self::pointKey($chains[$id][0]);
        $oldTailKey = self::pointKey(end($chains[$id]));
        $chains[$id] = array_reverse($chains[$id]);
        unset($headIndex[$oldHeadKey], $tailIndex[$oldTailKey]);
        $headIndex[$oldTailKey] = $id;
        $tailIndex[$oldHeadKey] = $id;
    }
    /**
     * Añade una cadena a otra y actualiza los índices correspondientes.
     */
    private static function appendChainIdx(
        array &$chains,
        array &$headIndex,
        array &$tailIndex,
        int $id1,
        int $id2
    ): void {
        if (!isset($chains[$id1], $chains[$id2])) {
            return;
        }
        $chain1 = &$chains[$id1];
        $chain2 = &$chains[$id2];
        $lenC1  = count($chain1);
        $lenC2  = count($chain2);
        // Capturar claves externas ANTES de cualquier modificación
        $headKey2Ext = self::pointKey($chain2[0]);
        $tailKey2Ext = self::pointKey($chain2[$lenC2 - 1]);
        $tailKey1Ext = self::pointKey($chain1[$lenC1 - 1]);
        // Simplificación colineal en el punto de empalme
        $tail = $chain1[$lenC1 - 1];
        $head = $chain2[0];
        if ($lenC1 >= 2) {
            $tail2 = $chain1[$lenC1 - 2];
            if (Point::collinear($tail2, $tail, $head)) {
                array_pop($chain1);
                $tail = $tail2;
            }
        }
        if ($lenC2 >= 2) {
            $head2 = $chain2[1];
            if (Point::collinear($tail, $head, $head2)) {
                array_shift($chain2);
            }
        }
        // Actualizar índices
        unset($tailIndex[$tailKey1Ext], $headIndex[$headKey2Ext]);
        unset($tailIndex[$tailKey2Ext]);
        // Fusionar chain1 ← chain2
        $chains[$id1] = array_merge($chain1, $chain2);
        $tailIndex[$tailKey2Ext] = $id1;
        // Eliminar chain2 en O(1)
        unset($chains[$id2]);
    }
    /**
     * Crea un objeto PolySegments a partir de un array de segmentos.
     */
    private static function makePolySegments(array $segments, bool $isInverted): PolySegments
    {
        return new PolySegments(
            segments: $segments,
            isInverted: $isInverted,
            bounds: self::segmentsBounds($segments)
        );
    }
    /**
     * Crea un objeto PolySegments vacío.
     */
    private static function emptyPolySegments(bool $isInverted = false): PolySegments
    {
        return new PolySegments([], $isInverted, null);
    }
    /**
     * Calcula los límites de un array de segmentos.
     */
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

    /**
     * Verifica si dos bounding boxes se superponen.
     */
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

    /**
     * Combina los límites de dos bounding boxes.
     */
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

    /**
     * Crea una cadena de segmentos a partir de un array de segmentos.
     */
    public static function segmentChainer(array $segments): array
    {
        $regions   = [];
        $chains    = [];   // id => Point[]
        $headIndex = [];   // pointKey => chain id
        $tailIndex = [];   // pointKey => chain id
        $nextId    = 0;

        foreach ($segments as $segment) {
            $point1 = $segment->start;
            $point2 = $segment->end;
            if ($point1->__eq($point2)) {
                continue;
            }
            $key1 = self::pointKey($point1);
            $key2 = self::pointKey($point2);

            // Lookup O(1)
            $matchId1Head = $headIndex[$key1] ?? null;  // head == point1
            $matchId1Tail = $tailIndex[$key1] ?? null;  // tail == point1
            $matchId2Head = $headIndex[$key2] ?? null;  // head == point2
            $matchId2Tail = $tailIndex[$key2] ?? null;  // tail == point2

            // Construir candidatos en el mismo orden de prioridad que el original:
            // head==pt1, head==pt2, tail==pt1, tail==pt2
            $candidates = [];
            if ($matchId1Head !== null) $candidates[] = [$matchId1Head, true,  true];
            if ($matchId2Head !== null) $candidates[] = [$matchId2Head, true,  false];
            if ($matchId1Tail !== null) $candidates[] = [$matchId1Tail, false, true];
            if ($matchId2Tail !== null) $candidates[] = [$matchId2Tail, false, false];

            // Deduplicar por id y tomar los dos primeros
            $firstMatch  = null;
            $secondMatch = null;
            $seen = [];
            foreach ($candidates as [$cid, $cHead, $cPt1]) {
                if (isset($seen[$cid])) continue;
                $seen[$cid] = true;
                if ($firstMatch === null) {
                    $firstMatch = [$cid, $cHead, $cPt1];
                } else {
                    $secondMatch = [$cid, $cHead, $cPt1];
                    break;
                }
            }

            // El original itera cadenas por ID creciente → menor ID = firstMatch.
            // Normalizar para garantizar topología idéntica al original.
            if (
                $firstMatch !== null && $secondMatch !== null
                && $firstMatch[0] > $secondMatch[0]
            ) {
                [$firstMatch, $secondMatch] = [$secondMatch, $firstMatch];
            }

            // ── Caso 0: sin match → nueva cadena ─────────────────────────────
            if ($firstMatch === null) {
                $id = $nextId++;
                $chains[$id]      = [$point1, $point2];
                $headIndex[$key1] = $id;
                $tailIndex[$key2] = $id;
                continue;
            }

            // ── Caso 1: un solo match → extender ─────────────────────────────
            if ($secondMatch === null) {
                [$index, $matchesHead, $matchesPt1] = $firstMatch;
                $point     = $matchesPt1 ? $point2 : $point1;
                $pointKey  = $matchesPt1 ? $key2   : $key1;
                $addToHead = $matchesHead;
                $chain     = &$chains[$index];
                $lenC      = count($chain);
                $grow      = $addToHead ? $chain[0]        : $chain[$lenC - 1];
                $grow2     = $addToHead
                    ? ($chain[1]        ?? $chain[0])
                    : ($chain[$lenC - 2] ?? $chain[$lenC - 1]);
                $opposite  = $addToHead ? $chain[$lenC - 1] : $chain[0];

                // Simplificación colineal en el extremo que crece
                if ($lenC >= 2 && Point::collinear($grow2, $grow, $point)) {
                    if ($addToHead) {
                        $oldHeadKey = self::pointKey($chain[0]);
                        unset($headIndex[$oldHeadKey]);
                        array_shift($chain);
                        $grow = $grow2;
                        $headIndex[self::pointKey($chain[0])] = $index;
                    } else {
                        $oldTailKey = self::pointKey($chain[$lenC - 1]);
                        unset($tailIndex[$oldTailKey]);
                        array_pop($chain);
                        $grow = $grow2;
                        $tailIndex[self::pointKey(end($chain))] = $index;
                    }
                    $lenC--;
                }

                // ¿El segmento cierra la cadena?
                if ($opposite->__eq($point)) {
                    // Capturar claves ANTES de la simplificación de cierre
                    $hkClose = self::pointKey($chain[0]);
                    $tkClose = self::pointKey(end($chain));
                    if ($lenC >= 2) {
                        $opposite2 = $addToHead ? $chain[$lenC - 2] : $chain[1];
                        if (Point::collinear($opposite2, $opposite, $grow)) {
                            if ($addToHead) {
                                array_pop($chain);
                            } else {
                                array_shift($chain);
                            }
                        }
                    }
                    unset($headIndex[$hkClose], $tailIndex[$tkClose], $chains[$index]);
                    if (count($chain) >= 3) {
                        $regions[] = $chain;
                    }
                    continue;
                }

                // Extender
                if ($addToHead) {
                    $oldHKey = self::pointKey($chain[0]);
                    unset($headIndex[$oldHKey]);
                    array_unshift($chain, $point);
                    $headIndex[$pointKey] = $index;
                } else {
                    $oldTKey = self::pointKey($chain[$lenC - 1]);
                    unset($tailIndex[$oldTKey]);
                    $chain[] = $point;
                    $tailIndex[$pointKey] = $index;
                }
                continue;
            }

            // ── Caso 2: dos matches → fusionar dos cadenas ───────────────────
            [$firstIndex,  $firstHead] = $firstMatch;
            [$secondIndex, $secondHead] = $secondMatch;
            $reverseFirst = count($chains[$firstIndex]) < count($chains[$secondIndex]);

            if ($firstHead) {
                if ($secondHead) {
                    if ($reverseFirst) {
                        self::reverseChainIdx($chains, $headIndex, $tailIndex, $firstIndex);
                        self::appendChainIdx($chains, $headIndex, $tailIndex, $firstIndex, $secondIndex);
                    } else {
                        self::reverseChainIdx($chains, $headIndex, $tailIndex, $secondIndex);
                        self::appendChainIdx($chains, $headIndex, $tailIndex, $secondIndex, $firstIndex);
                    }
                } else {
                    self::appendChainIdx($chains, $headIndex, $tailIndex, $secondIndex, $firstIndex);
                }
            } else {
                if ($secondHead) {
                    self::appendChainIdx($chains, $headIndex, $tailIndex, $firstIndex, $secondIndex);
                } else {
                    if ($reverseFirst) {
                        self::reverseChainIdx($chains, $headIndex, $tailIndex, $firstIndex);
                        self::appendChainIdx($chains, $headIndex, $tailIndex, $secondIndex, $firstIndex);
                    } else {
                        self::reverseChainIdx($chains, $headIndex, $tailIndex, $secondIndex);
                        self::appendChainIdx($chains, $headIndex, $tailIndex, $firstIndex, $secondIndex);
                    }
                }
            }
        }

        return $regions;
    }

    // core API
    public static function segments(Polygon$polygon): PolySegments
    {
        $regionIntersecter = new RegionIntersecter();

        foreach ($polygon->regions as $region) {
            $regionIntersecter->addRegion($region);
        }

        $segments = $regionIntersecter->calculate2($polygon->isInverted);

        return self::makePolySegments($segments, $polygon->isInverted);
    }

    public static function combine(PolySegments $segments1, Polysegments $segments2)
    {
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
                    start: $segment->start,
                    end: $segment->end,
                    myFill: new Fill($resB, $resA) // below=resB, above=resA
                );
            }
        }

        return $result;
    }

    public static function polygon(PolySegments $segments)
    {
        // 1) Construye cadenas (como ya haces)
        $s = self::segmentChainer($segments->segments);
        // 2) POST-PROCESO: parte anillos auto-tocados en ciclos simples
        $s = self::splitSelfTouchingRegions($s);
        // 3) Construye el polígono final
        $p = Polygon::create()->fillFromArray($s, $segments->isInverted);
        return $p;
    }

    public static function __operate(
        Polygon $polygon1,
        Polygon $polygon2,
        Selector $selector
    ): Polygon {
        $method           = $selector->value;
        $combinedSegments = self::combine(self::segments($polygon1), self::segments($polygon2));
        $selectedSegments = self::$method($combinedSegments);
        return self::polygon($selectedSegments);
    }

    public static function splitSelfTouchingRegions(array $regions): array
    {
        // Recorre cada región (anillo) y parte si hay puntos repetidos
        $out = [];
        foreach ($regions as $ring) {
            foreach (self::__splitRegionAtDuplicates($ring) as $r) {
                // guarda solo ciclos con al menos 3 puntos
                if (count($r) >= 3) {
                    $out[] = $r;
                }
            }
        }
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
    private static function __splitRegionAtDuplicates(array $ring): array
    {
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

    // ── Operaciones binarias (dos polígonos) ──────────────────────────────────

    public static function union(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::__operate($polygon1, $polygon2, Selector::Union);
    }

    public static function intersect(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::__operate($polygon1, $polygon2, Selector::Intersect);
    }

    public static function intersection(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::intersect($polygon1, $polygon2);
    }

    public static function difference(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::__operate($polygon1, $polygon2, Selector::Difference);
    }

    public static function differenceRev(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::__operate($polygon1, $polygon2, Selector::DifferenceRev);
    }

    public static function xoring(Polygon $polygon1, Polygon $polygon2): Polygon
    {
        return self::__operate($polygon1, $polygon2, Selector::Xor);
    }

    // ── Operaciones n-arias (array de polígonos) ──────────────────────────────

    public static function unionMany(array $polygons): Polygon
    {
        if (count($polygons) === 0) {
            throw new \InvalidArgumentException('unionMany requires at least one polygon.');
        }
        /*
        $firstSegments = self::segments($polygons[0]);
        for ($i = 1; $i < count($polygons); $i++) {
            $combined      = self::combine($firstSegments, self::segments($polygons[$i]));
            $firstSegments = self::selectUnion($combined);
        }
        return self::polygon($firstSegments);
        */
        $items = self::polygonsToSegments($polygons);

        $result = self::reduceBalanced(
            $items,
            fn(PolySegments $a, PolySegments $b) => self::unionSegments($a, $b)
        );

        return self::polygon($result);
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
                    start: $seg->start,
                    end: $seg->end,
                    myFill: new Fill($resBelow, $resAbove)
                );
            }
        }
        return $result;
    }

    public static function selectUnion(CombinedPolySegments $combinedPolySegments): PolySegments
    {
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A || $B)
        );
        return new PolySegments(
            segments: $segments,
            isInverted: ($combinedPolySegments->isInverted1 || $combinedPolySegments->isInverted2)
        );
    }

    public static function selectIntersect(CombinedPolySegments $combinedPolySegments): PolySegments
    {
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A && $B)
        );
        return new PolySegments(
            segments: $segments,
            isInverted: ($combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
        );
    }

    public static function selectDifference(CombinedPolySegments $combinedPolySegments): PolySegments
    {
        // A \ B  ⇒  inside = A && !B
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A && !$B)
        );
        return new PolySegments(
            segments: $segments,
            isInverted: ($combinedPolySegments->isInverted1 && !$combinedPolySegments->isInverted2)
        );
    }

    public static function selectDifferenceRev(CombinedPolySegments $combinedPolySegments): PolySegments
    {
        // B \ A  ⇒  inside = B && !A
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($B && !$A)
        );
        return new PolySegments(
            segments: $segments,
            isInverted: (!$combinedPolySegments->isInverted1 && $combinedPolySegments->isInverted2)
        );
    }

    public static function selectXor(CombinedPolySegments $combinedPolySegments): PolySegments
    {
        // XOR  ⇒  inside = A xor B
        $segments = self::__selectLogical(
            $combinedPolySegments->combined,
            fn(bool $A, bool $B) => ($A xor $B)
        );
        return new PolySegments(
            segments: $segments,
            isInverted: ($combinedPolySegments->isInverted1 != $combinedPolySegments->isInverted2)
        );
    }
}
