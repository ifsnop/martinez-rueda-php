<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/* ---------------------------------------------------------------------------
 *  Nodo interno del skip list (torre de punteros).
 *  No se toca la clase Node del dominio: la torre vive aquí dentro.
 * ------------------------------------------------------------------------- */

final class SkipNode
{
    public mixed $value;
    public int   $height;
    public array $next;
    public array $prev;

    public function __construct(mixed $value, int $height)
    {
        $this->value  = $value;
        $this->height = $height;
        $this->next   = array_fill(0, $height, null);
        $this->prev   = array_fill(0, $height, null);
    }
}
