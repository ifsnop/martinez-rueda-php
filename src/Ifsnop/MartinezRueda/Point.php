<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Point
{
    /* Coordenadas privadas del punto */
    public float $x;
    public float $y;
    public ?string $_cachedKey = null;

    /* Constructor: inicializa el punto con coordenadas x e y (tipo float) */
    public function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /* Devuelve las coordenadas del punto como un array [x, y] */
    public function getArray(): array
    {
        return [$this->x, $this->y];
    }

    /*
     * Comprueba si tres puntos están alineados (colineales) usando determinante.
     * Se usa una tolerancia para evitar errores por precisión en números flotantes.
     */
    public static function collinear(Point $point1, Point $point2, Point $point3): bool
    {
        $dx1 = $point1->x - $point2->x;
        $dy1 = $point1->y - $point2->y;
        $dx2 = $point2->x - $point3->x;
        $dy2 = $point2->y - $point3->y;

        return abs($dx1 * $dy2 - $dx2 * $dy1) < Algorithm::TOLERANCE;
    }

    /*
     * Compara dos puntos teniendo en cuenta una tolerancia:
     * - Si son casi iguales: devuelve 0
     * - Si point1 está antes: -1
     * - Si point1 está después: 1
     * La comparación es primero por x, luego por y.
     */
    public static function compare(Point $point1, Point $point2): int
    {
        $eps = Algorithm::TOLERANCE;
        $dx = $point1->x - $point2->x;
        if (abs($dx) < $eps) {
            $dy = $point1->y - $point2->y;
            if (abs($dy) < $eps) {
                return 0;
            }
            return ($dy < 0.0) ? -1 : 1;
        }
        return ($dx < 0.0) ? -1 : 1;
    }

    /*
     * Determina si un punto está por encima o sobre la línea definida por "left" y "right".
     * Usa el producto vectorial para hacer la evaluación.
     * En el caso de encontrar problemas por la precisión de TOLERANCE, y en lugar
     * de hacer el valor más pequeño, comprobar con el siguiente PR
     * https://github.com/Henry00IS/ShapeEditor/commit/5584b25914ff53a773e4517482a028aab2cd8f1e
     */
    public static function pointAboveOrOnLine(Point $point, Point $left, Point $right): bool
    {

        $rx = $right->x;
        $ry = $right->y;
        $lx = $left->x;
        $ly = $left->y;
        $px = $point->x;
        $py = $point->y;

        // Orientación (signo del área): >= -eps ⇒ sobre o por encima
        $orient = (($rx - $lx) * ($py - $ly)) - (($ry - $ly) * ($px - $lx));
        return $orient >= -Algorithm::TOLERANCE;
    }

    /*
     * Verifica si un punto está entre dos puntos dados (en la línea definida por ellos).
     * Utiliza producto escalar para proyectar el punto y comprobar si está dentro del segmento.
     * Versión exclusiva (t € (0,1)) y colineal (recomendada para booleanas)
     */
    public static function between(Point $p, Point $a, Point $b): bool
    {

        $abx = $b->x - $a->x;
        $aby = $b->y - $a->y;
        $apx = $p->x - $a->x;
        $apy = $p->y - $a->y;
        $eps = Algorithm::TOLERANCE;


        $sqlen = $abx * $abx + $aby * $aby;
        if ($sqlen == 0.0) return false;
        $len = sqrt($sqlen);

        // Colinealidad robusta
        $cross = $abx * $apy - $aby * $apx;
        $epsGeom = max(1.0, $len) * (1.0 * $eps);
        if (abs($cross) > $epsGeom) return false;

        $t = ($apx * $abx + $apy * $aby) / $sqlen;

        // Exclusión paramétrica equivalente a distancia T
        $tTol = $eps / $len; // <-- clave
        return ($t > 0.0 + $tTol) && ($t < 1.0 - $tTol);
    }

    /*
     * Determina si dos segmentos de línea se cruzan y devuelve el punto de intersección.
     * Si son paralelos, devuelve null.
     */
    public static function linesIntersect(Point $a0, Point $a1, Point $b0, Point $b1): ?IntersectionPoint
    {
        $adx = $a1->x - $a0->x;
        $ady = $a1->y - $a0->y;
        $bdx = $b1->x - $b0->x;
        $bdy = $b1->y - $b0->y;

        $axb = $adx * $bdy - $ady * $bdx;

        if (abs($axb) < Algorithm::TOLERANCE) {
            return null;
        }

        $dx = $a0->x - $b0->x;
        $dy = $a0->y - $b0->y;

        $a = ($bdx * $dy - $bdy * $dx) / $axb;
        $b = ($adx * $dy - $ady * $dx) / $axb;

        return new IntersectionPoint(
            self::__calcAlongUsingValue($a),
            self::__calcAlongUsingValue($b),
            new Point($a0->x + $a * $adx, $a0->y + $a * $ady)
        );
    }

    /*
     * Método auxiliar que clasifica un valor dentro de un rango normalizado [0, 1]
     * Retorna:
     * -2 → fuera por la izquierda,
     * -1 → casi al inicio,
     *  0 → dentro,
     *  1 → casi al final,
     *  2 → fuera por la derecha.
     */
    private static function __calcAlongUsingValue(float $value)
    {
        $epsStrict = Algorithm::TOLERANCE;
        $epsSnap   = Algorithm::TOLERANCE_SQRT;
        if ($value < -$epsStrict) {
            return -2;
        }
        if ($value < $epsSnap) {
            return -1;
        }
        $d1 = $value - 1.0;
        if ($d1 <= -$epsSnap) {
            return 0;
        }
        if ($d1 < $epsStrict) {
            return 1;
        }
        return 2;
    }
    /*
     * Comprueba si otro objeto es un punto equivalente (coordenadas casi iguales).
     */
    public function __eq(Point $other): bool
    {
        $eps = Algorithm::TOLERANCE;
        return abs($this->x - $other->x) < $eps &&
            abs($this->y - $other->y) < $eps;
    }

    /*
     * Devuelve una representación en cadena del punto, con corchetes.
     * Ejemplo: [1.5, 3.2]
     */
    public function __toString(): string
    {
        return "[{$this->x},{$this->y}]";
    }

    /*
     * Devuelve una representación simple del punto como cadena (sin corchetes).
     * Ejemplo: 1.5,3.2
     */
    public function __repr(): string
    {
        return "{$this->x},{$this->y}";
    }
}
