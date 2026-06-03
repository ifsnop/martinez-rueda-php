<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/* ---------------------------------------------------------------------------
 *  Mecánica común del skip list doblemente enlazado.
 *  Las subclases sólo aportan su comparador de orden.
 * ------------------------------------------------------------------------- */
trait SkipListCore
{
    private SkipNode $header;
    private int      $level    = 1;
    private int      $maxLevel = 32;

    private function initSkip(): void
    {
        // Centinela con la altura máxima; sus next/prev sobrantes quedan null.
        $this->header = new SkipNode(null, $this->maxLevel);
        $this->level  = 1;
    }

    /** Altura aleatoria con p = 1/2 (skip list clásico). */
    private function randomLevel(): int
    {
        $lvl = 1;
        while ($lvl < $this->maxLevel && (\mt_rand() & 1)) {
            $lvl++;
        }
        return $lvl;
    }

    /**
     * Inserta $value justo después de los predecesores $update[i] de cada
     * nivel y devuelve su SkipNode. Mantiene además los enlaces de NIVEL 0
     * sobre el propio Node ($value->previous / $value->next) para que la
     * navegación del algoritmo de barrido funcione sin cambios.
     *
     * @param array<int,SkipNode> $update
     */
    private function linkAt(array $update, Node $value): SkipNode
    {
        $h = $this->randomLevel();
        if ($h > $this->level) {
            for ($i = $this->level; $i < $h; $i++) {
                $update[$i] = $this->header;
            }
            $this->level = $h;
        }

        $w = new SkipNode($value, $h);
        for ($i = 0; $i < $h; $i++) {
            $w->next[$i]        = $update[$i]->next[$i];
            $w->prev[$i]        = $update[$i];
            $update[$i]->next[$i] = $w;
            if ($w->next[$i] !== null) {
                $w->next[$i]->prev[$i] = $w;
            }
        }

        // --- Enlaces de la lista doblemente enlazada del nivel 0 (sobre Node) ---
        $pv   = $w->prev[0];
        $nx   = $w->next[0];
        $pVal = ($pv === $this->header) ? null : $pv->value;
        $nVal = ($nx === null)          ? null : $nx->value;

        $value->previous = $pVal;
        $value->next     = $nVal;
        if ($pVal !== null) $pVal->next     = $value;
        if ($nVal !== null) $nVal->previous = $value;

        return $w;
    }

    /** Desenlaza un nodo de todos los niveles en O(altura). Sin búsqueda. */
    private function unlink(SkipNode $w): void
    {
        for ($i = 0; $i < $w->height; $i++) {
            $p = $w->prev[$i];
            $n = $w->next[$i];
            if ($p !== null) $p->next[$i] = $n;
            if ($n !== null) $n->prev[$i] = $p;
        }

        // --- Puentea los vecinos en la lista doblemente enlazada del nivel 0 ---
        $pv   = $w->prev[0];
        $nx   = $w->next[0];
        $pVal = ($pv === null || $pv === $this->header) ? null : $pv->value;
        $nVal = ($nx === null)                          ? null : $nx->value;

        if ($pVal !== null) $pVal->next     = $nVal;
        if ($nVal !== null) $nVal->previous = $pVal;

        $w->value->previous = null;
        $w->value->next     = null;
    }
}
