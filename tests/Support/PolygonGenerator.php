<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda\Tests\Support;

/**
 * Generadores de pares de polígonos aleatorios (usar mt_srand para que sean
 * deterministas). Devuelven anillos en el rango aproximado [-10, 10].
 */
final class PolygonGenerator
{
    /** Generadores => descripción */
    public const GENERATORS = [
        'star'    => 'polígonos estrellados simples aleatorios',
        'grid'    => 'rectángulos enteros: aristas compartidas, solapes colineales, vértices comunes',
        'rotgrid' => 'como grid pero girados un ángulo arbitrario (aristas compartidas inexactas)',
        'holes'   => 'polígono con agujero frente a polígono estrellado',
        'selfx'   => 'polígonos aleatorios auto-intersectantes (regla par-impar)',
        'shared'  => 'B reutiliza vértices exactos de A',
        'tiles'   => 'teselas adyacentes (triángulos con aristas comunes) repartidas entre A y B',
    ];

    public static function rnd(float $a, float $b): float
    {
        return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
    }

    public static function star(float $cx, float $cy, float $r, int $n): array
    {
        $angs = [];
        for ($i = 0; $i < $n; $i++) {
            $angs[] = self::rnd(0, 2 * M_PI);
        }
        sort($angs);
        $ring = [];
        foreach ($angs as $a) {
            $rr = self::rnd(0.3 * $r, $r);
            $ring[] = [$cx + $rr * cos($a), $cy + $rr * sin($a)];
        }
        return $ring;
    }

    public static function rect(float $x0, float $y0, float $x1, float $y1): array
    {
        return [[$x0, $y0], [$x1, $y0], [$x1, $y1], [$x0, $y1]];
    }

    public static function rotate(array $ring, float $ang): array
    {
        $c = cos($ang);
        $s = sin($ang);
        return array_map(fn($p) => [$p[0] * $c - $p[1] * $s, $p[0] * $s + $p[1] * $c], $ring);
    }

    /** @return array{0: array, 1: array} anillos de A y de B */
    public static function generate(string $g): array
    {
        switch ($g) {
            case 'star':
                return [
                    [self::star(self::rnd(-2, 2), self::rnd(-2, 2), self::rnd(1, 5), mt_rand(3, 25))],
                    [self::star(self::rnd(-2, 2), self::rnd(-2, 2), self::rnd(1, 5), mt_rand(3, 25))],
                ];
            case 'grid':
                $mk = function () {
                    $rings = [];
                    $k = mt_rand(1, 3);
                    for ($i = 0; $i < $k; $i++) {
                        $x = mt_rand(-4, 3);
                        $y = mt_rand(-4, 3);
                        $rings[] = self::rect($x, $y, $x + mt_rand(1, 4), $y + mt_rand(1, 4));
                    }
                    return $rings;
                };
                return [$mk(), $mk()];
            case 'rotgrid':
                $a = self::rnd(0, M_PI);
                [$A, $B] = self::generate('grid');
                return [
                    array_map(fn($r) => self::rotate($r, $a), $A),
                    array_map(fn($r) => self::rotate($r, $a), $B),
                ];
            case 'holes':
                $s = self::rnd(3, 6);
                return [
                    [self::rect(-$s, -$s, $s, $s), self::rect(-$s / 3, -$s / 3, $s / 3, $s / 3)],
                    [self::star(self::rnd(-3, 3), self::rnd(-3, 3), self::rnd(1, 4), mt_rand(3, 12))],
                ];
            case 'selfx':
                $mk = function () {
                    $r = [];
                    $n = mt_rand(4, 10);
                    for ($i = 0; $i < $n; $i++) {
                        $r[] = [self::rnd(-5, 5), self::rnd(-5, 5)];
                    }
                    return [$r];
                };
                return [$mk(), $mk()];
            case 'shared':
                $A = self::star(0, 0, 5, mt_rand(5, 15));
                $B = [];
                foreach ($A as $p) {
                    if (mt_rand(0, 2) === 0) {
                        $B[] = $p;
                    }
                }
                $B[] = [self::rnd(-6, 6), self::rnd(-6, 6)];
                if (count($B) < 3) {
                    $B = array_merge($B, self::star(1, 1, 3, 3));
                }
                $cx = array_sum(array_column($B, 0)) / count($B);
                $cy = array_sum(array_column($B, 1)) / count($B);
                usort($B, fn($p, $q) => atan2($p[1] - $cy, $p[0] - $cx) <=> atan2($q[1] - $cy, $q[0] - $cx));
                return [[$A], [$B]];
            case 'tiles':
                $n = mt_rand(5, 20);
                $pts = self::star(0, 0, 5, $n);
                $c = [self::rnd(-0.5, 0.5), self::rnd(-0.5, 0.5)];
                $A = [];
                $B = [];
                for ($i = 0; $i < $n; $i++) {
                    $tri = [$c, $pts[$i], $pts[($i + 1) % $n]];
                    if ($i % 2) {
                        $A[] = $tri;
                    } else {
                        $B[] = $tri;
                    }
                }
                return [$A, $B];
        }
        throw new \InvalidArgumentException("generador desconocido: $g");
    }

    /**
     * Escala, desplaza y opcionalmente redondea a $round decimales (como un GeoJSON típico).
     * $offset puede ser un número (mismo desplazamiento en x e y) o [lon, lat].
     */
    public static function transform(array $rings, float|array $offset, float $scale, ?int $round = null): array
    {
        [$ox, $oy] = is_array($offset) ? $offset : [$offset, $offset];
        return array_map(
            fn($r) => array_map(function ($p) use ($ox, $oy, $scale, $round) {
                $x = $p[0] * $scale + $ox;
                $y = $p[1] * $scale + $oy;
                return $round === null ? [$x, $y] : [round($x, $round), round($y, $round)];
            }, $r),
            $rings
        );
    }
}
