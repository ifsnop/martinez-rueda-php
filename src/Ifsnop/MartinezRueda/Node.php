<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Node
{
    public bool $isStart;     // true si es evento START, false si es END

    public ?StatusEntry $status;   // entrada en StatusList para este evento
    public ?SkipNode    $evSnode = null; // back-pointer en EventList
    public ?Node $other;      // el evento END paired con este START (o viceversa)
    public ?Node $previous;   // nodo anterior en la lista enlazada
    public ?Node $next;       // nodo siguiente en la lista enlazada

    public ?Point   $pt;      // coordenadas del evento
    public ?Segment $seg;     // segmento al que pertenece este evento
    public bool     $primary; // true si es el evento primario (no el espejo)

    public function __construct(
        bool $isStart = false,
        ?Point $pt = null,
        ?Segment $seg = null,
        bool $primary = false,
        ?Node $next = null,
        ?Node $previous = null,
        ?Node $other = null,
    ) {
        $this->status = null;
        $this->other = $other;
        $this->previous = $previous;
        $this->next = $next;
        $this->isStart = $isStart;
        $this->pt = $pt;
        $this->seg = $seg;
        $this->primary = $primary;
    }
}
