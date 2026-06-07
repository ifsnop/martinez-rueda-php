<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Node
{
    public bool $isRoot;      // true si es el nodo centinela raíz de la lista
    public bool $isStart;     // true si es evento START, false si es END

    public ?Node $status;     // nodo en StatusList que corresponde a este evento
    public ?Node $other;      // el evento END paired con este START (o viceversa)
    public ?Node $ev;         // back-pointer al evento desde un nodo de StatusList
    public ?Node $previous;   // nodo anterior en la lista enlazada
    public ?Node $next;       // nodo siguiente en la lista enlazada

    public ?Point   $pt;      // coordenadas del evento
    public ?Segment $seg;     // segmento al que pertenece este evento
    public bool     $primary; // true si es el evento primario (no el espejo)
    public bool     $inStatus = false;
    public ?SkipNode $snode    = null;

    public function __construct(
        bool $isRoot = false,
        bool $isStart = false,
        ?Point $pt = null,
        ?Segment $seg = null,
        bool $primary = false,
        ?Node $next = null,
        ?Node $previous = null,
        ?Node $other = null,
        ?Node $ev = null,
        ?Node $status = null
    ) {
        $this->status = $status;
        $this->other = $other;
        $this->ev = $ev;
        $this->previous = $previous;
        $this->next = $next;
        $this->isRoot = $isRoot;
        $this->isStart = $isStart;
        $this->pt = $pt;
        $this->seg = $seg;
        $this->primary = $primary;
    }
}
