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

    public function exists(?StatusEntry $entry): bool
    {
        return $entry !== null && $entry->snode !== null;
    }

    /**
     * Localiza la posición de $ev y devuelve sus vecinos (before/after).
     * La inserción real se hace luego con insert(), que re-busca la posición
     * porque el segmento de $ev puede haber sido dividido entre medias.
     * O(log n).
     * 
     * @return array{0:?StatusEntry,1:?StatusEntry} [before (above), after (below)]
     */
    public function findTransition(Node $ev): array
    {
        $update  = $this->searchStatus($ev);
        $beforeW = $update[0];
        $afterW  = $beforeW->next[0];

        return [
            ($beforeW === $this->header) ? null : $beforeW->value,
            ($afterW  === null)          ? null : $afterW->value,
        ];
    }

    /**
     * Inserta $node (que envuelve al evento $ev) en el estado, re-buscando la
     * posición. Sustituye al antiguo closure `insert` de la Transition: ya no
     * se asigna un Closure por evento START. Guarda el back-pointer $snode
     * para que remove() pueda desenlazar en O(altura) sin closure.
     */
    public function insert(Node $ev): StatusEntry
    {
        $entry = new StatusEntry($ev);
        $update = $this->searchStatus($ev);
        $entry->snode = $this->linkAt($update, $entry);
        return $entry;
    }

    /**
     * Desenlaza $node del estado. Sustituye al closure `$node->remove`.
     * Idempotente: tras el borrado anula el back-pointer (segunda llamada = no-op).
     */
    public function remove(StatusEntry $entry): void
    {
        if ($entry->snode !== null) {
            $this->unlink($entry->snode);
            $entry->snode = null;
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
            while ($n !== null && !$this->statusCheckBefore($a1, $a2, $n->value)) {
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
    private function statusCheckBefore(Point $a1, Point $a2, StatusEntry $e2): bool
    {
        $b1 = $e2->ev->seg->start;
        $b2 = $e2->ev->seg->end;

        if (Point::collinear($a1, $b1, $b2)) {
            return !Point::collinear($a2, $b1, $b2) && Point::pointAboveOrOnLine($a2, $b1, $b2);
        }
        return Point::pointAboveOrOnLine($a1, $b1, $b2);
    }
}
