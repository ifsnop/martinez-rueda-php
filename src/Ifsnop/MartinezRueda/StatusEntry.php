<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class StatusEntry
{
    public Node      $ev;       // el evento del sweep al que corresponde esta entrada
    public ?StatusEntry $previous = null; // enlace de la skip list (nivel 0)
    public ?StatusEntry $next     = null; // enlace de la skip list (nivel 0)
    public ?SkipNode    $snode    = null; // back-pointer al nodo de la skip list
    public function __construct(Node $ev)
    {
        $this->ev = $ev;
    }
}