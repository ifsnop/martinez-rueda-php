<?php

namespace Ifsnop\MartinezRueda;

class Point {
    /* Coordenadas privadas del punto */
    private $x;
    private $y;

    /* Constructor: inicializa el punto con coordenadas x e y (tipo float) */
    public function __construct(float $x, float $y) {
        $this->x = $x;
        $this->y = $y;
    }

    /* Devuelve las coordenadas del punto como un array [x, y] */
    public function getArray(): array {
        return [$this->x, $this->y];
    }

    /*
     * Comprueba si tres puntos están alineados (colineales) usando determinante.
     * Se usa una tolerancia para evitar errores por precisión en números flotantes.
     */
    public static function collinear(Point $point1, Point $point2, Point $point3): bool {
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
    public static function compare(Point $point1, Point $point2) {
        if (abs($point1->x - $point2->x) < Algorithm::TOLERANCE) {
            return abs($point1->y - $point2->y) < Algorithm::TOLERANCE
                ? 0
                : ($point1->y < $point2->y ? -1 : 1);
        }
        return $point1->x < $point2->x ? -1 : 1;
    }

    /*
     * Determina si un punto está por encima o sobre la línea definida por "left" y "right".
     * Usa el producto vectorial para hacer la evaluación.
     */
    public static function pointAboveOrOnLine(Point $point, Point $left, Point $right) {
        return (
            (($right->x - $left->x) * ($point->y - $left->y)) -
            (($right->y - $left->y) * ($point->x - $left->x))
        ) >= -Algorithm::TOLERANCE;
    }

    /*
     * Verifica si un punto está entre dos puntos dados (en la línea definida por ellos).
     * Utiliza producto escalar para proyectar el punto y comprobar si está dentro del segmento.
     */
    public static function between(Point $point, Point $left, Point $right) {
        $dPyLy = $point->y - $left->y;
        $dRxLx = $right->x - $left->x;
        $dPxLx = $point->x - $left->x;
        $dRyLy = $right->y - $left->y;

        $dot = $dPxLx * $dRxLx + $dPyLy * $dRyLy;
        if ($dot < Algorithm::TOLERANCE) {
            return false;
        }

        $sqlen = $dRxLx * $dRxLx + $dRyLy * $dRyLy;
        if ($dot - $sqlen > -Algorithm::TOLERANCE) {
            return false;
        }

        return true;
    }

    /*
     * Determina si dos segmentos de línea se cruzan y devuelve el punto de intersección.
     * Si son paralelos, devuelve null.
     */
    public static function linesIntersect(Point $a0, Point $a1, Point $b0, Point $b1) {
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
    private static function __calcAlongUsingValue(float $value) {
        if ($value <= -Algorithm::TOLERANCE) {
            return -2;
        } elseif ($value < Algorithm::TOLERANCE) {
            return -1;
        } elseif ($value - 1 <= -Algorithm::TOLERANCE) {
            return 0;
        } elseif ($value - 1 < Algorithm::TOLERANCE) {
            return 1;
        } else {
            return 2;
        }
    }

    /*
     * Comprueba si otro objeto es un punto equivalente (coordenadas casi iguales).
     */
    public function __eq($other): bool {
        if (!($other instanceof Point)) {
            return false;
        }
        return abs($this->x - $other->x) < Algorithm::TOLERANCE &&
               abs($this->y - $other->y) < Algorithm::TOLERANCE;
    }

    /*
     * Devuelve una representación en cadena del punto, con corchetes.
     * Ejemplo: [1.5, 3.2]
     */
    public function __toString(): string {
        return "[{$this->x},{$this->y}]";
    }

    /*
     * Devuelve una representación simple del punto como cadena (sin corchetes).
     * Ejemplo: 1.5,3.2
     */
    public function __repr(): string {
        return "{$this->x},{$this->y}";
    }
}
