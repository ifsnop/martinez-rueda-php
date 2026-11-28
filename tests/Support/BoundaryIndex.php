<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests\Support;

/**
 * Índice espacial (rejilla) de las aristas de un conjunto de anillos, para saber
 * rápido si un punto está a menos de $delta de alguna arista. Celda = longitud media
 * de arista; cada arista se registra en las celdas que toca su bounding box.
 */
final class BoundaryIndex
{
    private array $grid = [];
    private float $cell;
    /** @var array<int, array{float,float,float,float}> */
    private array $edges = [];

    public function __construct(array $rings)
    {
        $sum = 0.0;
        foreach ($rings as $r) {
            $n = count($r);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $e = [$r[$j][0], $r[$j][1], $r[$i][0], $r[$i][1]];
                $this->edges[] = $e;
                $sum += abs($e[2] - $e[0]) + abs($e[3] - $e[1]);
            }
        }
        $this->cell = $this->edges ? max($sum / count($this->edges), 1e-12) : 1.0;
        foreach ($this->edges as $id => [$ax, $ay, $bx, $by]) {
            for ($gx = $this->c(min($ax, $bx)); $gx <= $this->c(max($ax, $bx)); $gx++) {
                for ($gy = $this->c(min($ay, $by)); $gy <= $this->c(max($ay, $by)); $gy++) {
                    $this->grid["$gx,$gy"][] = $id;
                }
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->edges === [];
    }

    /** ¿Está (x, y) a menos de (o a) $delta de alguna arista? */
    public function near(float $x, float $y, float $delta): bool
    {
        for ($gx = $this->c($x - $delta); $gx <= $this->c($x + $delta); $gx++) {
            for ($gy = $this->c($y - $delta); $gy <= $this->c($y + $delta); $gy++) {
                foreach ($this->grid["$gx,$gy"] ?? [] as $id) {
                    if (self::distanceToEdge($this->edges[$id], $x, $y) <= $delta) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /** Distancia exacta a la arista más cercana (recorre todas; solo para mensajes). */
    public function distance(float $x, float $y): float
    {
        $best = INF;
        foreach ($this->edges as $e) {
            $best = min($best, self::distanceToEdge($e, $x, $y));
        }
        return $best;
    }

    private function c(float $v): int
    {
        return (int)floor($v / $this->cell);
    }

    private static function distanceToEdge(array $e, float $x, float $y): float
    {
        [$ax, $ay, $bx, $by] = $e;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $l = $dx * $dx + $dy * $dy;
        $t = $l > 0 ? max(0.0, min(1.0, (($x - $ax) * $dx + ($y - $ay) * $dy) / $l)) : 0.0;
        return hypot($x - ($ax + $t * $dx), $y - ($ay + $t * $dy));
    }
}
