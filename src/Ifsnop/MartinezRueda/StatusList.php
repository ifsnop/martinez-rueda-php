<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/* ===========================================================================
 *  StatusList  ->  estado del barrido (skip list ordenado con vecinos)
 * ========================================================================= */

final class StatusList
{
    use SkipListCore;

    public function __construct()
    {
        $this->initSkip();
    }

    public function exists(?Node $node): bool
    {
        return $node !== null && $node->inStatus; // isset($this->exists[\spl_object_id($node)]);
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
        return $data;
    }

    /**
     * Localiza la posición de $ev y devuelve sus vecinos (before/after).
     * La inserción real se hace luego con insert(), que re-busca la posición
     * porque el segmento de $ev puede haber sido dividido entre medias.
     * O(log n).
     */
    public function findTransition(Node $ev): Transition
    {
        $update  = $this->searchStatus($ev);
        $beforeW = $update[0];
        $afterW  = $beforeW->next[0];

        $before = ($beforeW === $this->header) ? null : $beforeW->value;
        $after  = ($afterW  === null)          ? null : $afterW->value;

        return new Transition(after: $after, before: $before);
    }

    /**
     * Inserta $node (que envuelve al evento $ev) en el estado, re-buscando la
     * posición. Sustituye al antiguo closure `insert` de la Transition: ya no
     * se asigna un Closure por evento START. Guarda el back-pointer $snode
     * para que remove() pueda desenlazar en O(altura) sin closure.
     */
    public function insert(Node $ev, Node $node): Node
    {
        $node->inStatus = true;
        $update = $this->searchStatus($ev);
        $node->snode = $this->linkAt($update, $node);
        return $node;
    }

    /**
     * Desenlaza $node del estado. Sustituye al closure `$node->remove`.
     * Idempotente: tras el borrado anula el back-pointer (segunda llamada = no-op).
     */
    public function remove(Node $node): void
    {
        if ($node->snode !== null) {
            $this->unlink($node->snode);
            $node->snode    = null;
            $node->inStatus = false;
        }
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
