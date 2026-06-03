<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/* ===========================================================================
 *  StatusList  ->  estado del barrido (skip list ordenado con vecinos)
 * ========================================================================= */
final class StatusList
{
    use SkipListCore;

    /** @var array<int,true> set de membresía para exists() */
    // private array $exists = [];

    public function __construct() { $this->initSkip(); }

    public function exists(?Node $node): bool
    {
	return $node !== null && $node->inStatus; // isset($this->exists[\spl_object_id($node)]);
/*
        if ($node === null) {
            return false;
        }
        return isset($this->exists[\spl_object_id($node)]);
*/
    }

    /**
     * Inicializa un Node como elemento de lista. El `remove` que se fija aquí
     * es un marcador de posición: lo sustituye la lista concreta (EventList o
     * StatusList) en el momento de insertarlo de verdad.
     */
    public static function node(Node $data): Node
    {
        $data->previous = null;
        $data->next     = null;
/*        $data->remove   = static function () use ($data) {
            if ($data->previous === null && $data->next === null) {
                return;
            }
            $prev = $data->previous;
            $next = $data->next;
            if ($prev !== null) $prev->next     = $next;
            if ($next !== null) $next->previous = $prev;
            $data->previous = $data->next = null;
        };
*/
        return $data;
    }

    /**
     * Localiza la posición de $ev y devuelve sus vecinos (before/after) más
     * un closure `insert` que realiza la inserción real (re-buscando, porque
     * el segmento de $ev puede haber sido dividido entre medias). Equivalente
     * al comportamiento original, ahora en O(log n).
     */
    public function findTransition(Node $ev): Transition
    {
        $update  = $this->searchStatus($ev);
        $beforeW = $update[0];
        $afterW  = $beforeW->next[0];

        $before = ($beforeW === $this->header) ? null : $beforeW->value;
        $after  = ($afterW  === null)          ? null : $afterW->value;

        $insert = function (Node $node) use ($ev): Node {
	    $node->inStatus = true;
            $update = $this->searchStatus($ev);
            $w      = $this->linkAt($update, $node);
            // $this->exists[\spl_object_id($node)] = true;
            $node->remove = function () use ($node, $w) {
                $this->unlink($w);
		$node->inStatus = false;
                // unset($this->exists[\spl_object_id($node)]);
            };
            return $node;
        };

        return new Transition(after: $after, before: $before, insert: $insert);
    }

    /**
     * Camino de predecesores para la posición vertical de $ev en el estado.
     *
     * @return array<int,SkipNode>
     */
    private function searchStatus(Node $ev): array
    {
        $update = [];
        $a1 = $ev->seg->start;
        $a2 = $ev->seg->end;

        $x = $this->header;
        for ($i = $this->level - 1; $i >= 0; $i--) {
            $n = $x->next[$i];
            while ($n !== null && !$this->statusCheckBefore($a1, $a2, $n->value->ev)) {
                $x = $n;
                $n = $x->next[$i];
            }
            $update[$i] = $x;
        }
        return $update;
    }

    /**
     * Comparador idéntico al binSearchPos original:
     * true si $ev (a1,a2) debe situarse ANTES del evento existente $e2.
     */
    private function statusCheckBefore(Point $a1, Point $a2, Node $e2): bool
    {
        $b1 = $e2->seg->start;
        $b2 = $e2->seg->end;

        if (Point::collinear($a1, $b1, $b2)) {
            return !Point::collinear($a2, $b1, $b2) && Point::pointAboveOrOnLine($a2, $b1, $b2);
        }
        return Point::pointAboveOrOnLine($a1, $b1, $b2);
    }
}
