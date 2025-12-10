<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Segment {
    public Point $start;
    public Point $end;
    public ?Fill $myFill;
    public ?Fill $otherFill;

    // Cache de bounding box y longitud
    public float $minX;
    public float $maxX;
    public float $minY;
    public float $maxY;
    public float $len2; // longitud al cuadrado


    public function __construct(Point $start, Point $end, Fill $myFill = null, Fill $otherFill = null) {
	// if ( Algorithm::DEBUG ) print __METHOD__ . PHP_EOL;
	$this->start = $start;
	$this->end = $end;
	$this->myFill = $myFill;
	$this->otherFill = $otherFill;
	$this->recalcBounds();
    }

    /** Recalcula min/max y longitud cuadrada */
    public function recalcBounds(): void
    {
        $sx = $this->start->getX();
        $sy = $this->start->getY();
        $ex = $this->end->getX();
        $ey = $this->end->getY();

        // AABB
        $this->minX = ($sx < $ex) ? $sx : $ex;
        $this->maxX = ($sx > $ex) ? $sx : $ex;
        $this->minY = ($sy < $ey) ? $sy : $ey;
        $this->maxY = ($sy > $ey) ? $sy : $ey;

        // Longitud^2 (para diagnósticos rápidos si se necesitara)
        $dx = $ex - $sx;
        $dy = $ey - $sy;
        $this->len2 = $dx * $dx + $dy * $dy;
    }


    public function __toString() {
	return "S: {$this->start}, E: {$this->end}";
    }

    public function __debugInfo() {
	return ["start" => $this->start, "end" => $this->end, 'myFill' => $this->myFill, 'otherFill' => $this->otherFill];
    }
}

