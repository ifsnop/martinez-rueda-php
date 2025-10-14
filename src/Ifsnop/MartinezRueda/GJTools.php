<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class GJTools
{
    /** Offset para el punto de prueba interior (evitar caer justo en borde) */
    private const TEST_OFFSET = 1e-9;

    /**
     * Transforma un array de anillos [[ [x,y], ... ], ... ] en una geometría GeoJSON.
     * - Si hay un único polígono => { type: 'Polygon', coordinates: [...] }
     * - Si hay varios polígonos   => { type: 'MultiPolygon', coordinates: [...] }
     * Asume profundidad 1: exteriores con huecos (no huecos dentro de huecos).
     *
     * @param array $rings Array de anillos; cada anillo es array de puntos [x, y].
     * @param bool  $preferPolygon Si true y hay un solo polígono, devuelve 'Polygon'; si false, siempre 'MultiPolygon'.
     * @param bool  $sortForCompare   Si true, canoniza/ordena polígonos y anillos para comparación determinista.
     * @return array GeoJSON geometry (array asociativo)
     * @throws InvalidArgumentException Si la entrada es inválida o hay profundidad > 1.
     */
    public static function ringsToGeoJSON(array $rings, bool $preferPolygon = true, $sortForCompare = true): array
    {
        if (!is_array($rings)) {
            throw new \InvalidArgumentException("Entrada no válida: se esperaba un array de anillos.");
        }

        // 1) Normalizar: cerrar anillos y preparar utilidades
        $R = [];
        $areas = [];
        $bboxes = [];
        foreach ($rings as $idx => $ring) {
	    $ring = self::dedupConsecutive($ring);
	    // $ring = self::removeCollinear($ring);
	    $ring = self::removeColinearPointsFromPolygon($ring);
            $ring = self::closeRing($ring);
            if (count($ring) < 4) { // 3 vértices + cierre
                throw new \InvalidArgumentException("Anillo $idx inválido: menos de 3 vértices.");
            }
            $R[$idx] = $ring;
            $areas[$idx] = self::signedArea($ring);
            $bboxes[$idx] = self::bbox($ring);
        }

        $n = count($R);
        if ($n === 0) {
            return ['type' => 'MultiPolygon', 'coordinates' => []];
        }

        // 2) Encontrar contenedor directo (padre) para cada anillo
        $parent = array_fill(0, $n, -1);

        for ($i = 0; $i < $n; $i++) {
            $bestParent = -1;
            $bestParentAbsArea = INF;
            $pTest = self::interiorTestPoint($R[$i]);

            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;

                if (!self::bboxContains($bboxes[$j], $bboxes[$i])) continue;

                if (self::pointInRing($pTest, $R[$j])) {
                    $absAj = abs($areas[$j]);
                    if ($absAj > abs($areas[$i]) && $absAj < $bestParentAbsArea) {
                        $bestParent = $j;
                        $bestParentAbsArea = $absAj;
                    }
                }
            }

            $parent[$i] = $bestParent;
        }

        // 3) Validar profundidad (no permitir huecos dentro de huecos)
        for ($i = 0; $i < $n; $i++) {
            if ($parent[$i] !== -1 && $parent[$parent[$i]] !== -1) {
                throw new \InvalidArgumentException(
                    "Se detectó profundidad > 1 (hueco dentro de hueco). Esta implementación asume un solo nivel de huecos."
                );
            }
        }

        // 4) Agrupar exteriores y sus huecos; normalizar orientación
        $children = array_fill(0, $n, []);
        for ($i = 0; $i < $n; $i++) {
            $p = $parent[$i];
            if ($p !== -1) $children[$p][] = $i;
        }

        $polygons = []; // cada elemento: [ exterior, hole1, hole2, ... ]
        for ($i = 0; $i < $n; $i++) {
            if ($parent[$i] !== -1) continue; // no es exterior

            $exterior = self::orientAsExterior($R[$i], $areas[$i]);
            $holes = [];
            foreach ($children[$i] as $c) {
                $holes[] = self::orientAsHole($R[$c], $areas[$c]);
            }

            // Opcional: ordenar huecos por área ascendente (más pequeños primero)
            usort($holes, function (array $a, array $b) {
                return abs(self::signedArea($a)) <=> abs(self::signedArea($b));
            });

            $polygons[] = array_merge([$exterior], $holes);
        }

        if (count($polygons) === 0) {
            throw new \InvalidArgumentException("No se detectó ningún anillo exterior.");
        }


	 // 5) Canonizar/ordenar para comparación determinista si procede
        if ($sortForCompare) {
            $polygons = self::canonicalizePolygons($polygons, (int) abs(floor(log10(abs(Algorithm::TOLERANCE)))));
        }

        // 6) Resultado GeoJSON
        if ($preferPolygon && count($polygons) === 1) {
            return ['type' => 'Polygon', 'coordinates' => $polygons[0]];
        }
        return ['type' => 'MultiPolygon', 'coordinates' => $polygons];
    }

    private static function almostEq(float $a, float $b): bool {
	return abs($a-$b) <= Algorithm::TOLERANCE;
    }

    private static function dedupConsecutive(array $ring): array {
        $out = [];
        foreach ($ring as $p) {
            if (empty($out)) { $out[] = $p; continue; }
            $q = $out[count($out)-1];
            if (!(self::almostEq($p[0],$q[0]) && self::almostEq($p[1],$q[1]))) $out[] = $p;
        }
        return $out;
    }
/*
    private static function isCollinear($a,$b,$c): bool {
        return abs(($b[0]-$a[0])*($c[1]-$a[1]) - ($b[1]-$a[1])*($c[0]-$a[0])) <= Algorithm::TOLERANCE;
    }

    private static function removeCollinear(array $ring): array {
        $n = count($ring);
        if ($n <= 3) return $ring;
        $out = [];
        for ($i=0; $i<$n; $i++) {
            $prev = $ring[($i-1+$n)%$n];
            $cur  = $ring[$i];
            $next = $ring[($i+1)%$n];
            if (!self::isCollinear($prev,$cur,$next)) $out[] = $cur;
        }
        if ($out && ($out[0] !== end($out))) $out[] = $out[0];
        return $out;
    }
*/
 /* ===================== Utilidades de canonización/ordenación ===================== */

    /**
     * Canoniza polígonos y anillos:
     *  - Rota cada anillo para que empiece en el vértice mínimo lexicográfico
     *  - Ordena huecos de cada polígono por clave lexicográfica
     *  - Ordena polígonos por clave (exterior, luego huecos)
     *
     * @param array    $polygons  [ [ext, hole1, ...], ... ]
     * @param int|null $precision Decimales para redondeo (null = sin redondeo)
     * @return array
     */


    // helper para limpiar geometrías
    public static function fixGeometries(array $geom): array
    {
	// la geometría es un multipolígono
	$ret = [];
	// print "GEOM: " . json_encode($geom) . PHP_EOL . PHP_EOL;
	$polygons = [];
	foreach($geom as $polygon) {
	    //print "POLY: " . json_encode($polygon) . PHP_EOL;
	    $rings = [];
	    foreach($polygon as $ring) {
		//print "RING&HOLES $idx]" . json_encode($ring) . PHP_EOL;
		$ring = self::dedupConsecutive($ring);
		$ring = self::removeColinearPointsFromPolygon($ring);
		$ring = self::closeRing($ring);
		//print "RING&HOLES $idx]" . json_encode($ring) . PHP_EOL;
		$rings[] = $ring;
	    }
	    $polygons[] = $rings;
	}
	//print "FINA: " . json_encode($polygons) . PHP_EOL . PHP_EOL;
	return $polygons;
	
/*
	    foreach($polygons as $rings) {
		print "RINGS: " . json_encode($rings) . PHP_EOL;
		$ring = self::dedupConsecutive($ring);
		$ring = self::removeCollinear($ring);
		$ring = self::closeRing($ring);
		$g[] = $ring;
	    }
		foreach ($rings as $idx => $ring) {
		    print "INSIDE: " . json_encode($ring) . PHP_EOL;
		    $ring = self::dedupConsecutive($ring);
		    $ring = self::removeCollinear($ring);
        	    $ring = self::closeRing($ring);
		    $p[] = $ring;
		}

	    $ret[] = $g;
	}

	return $ret;
*/
    }


public static function removeColinearPointsFromPolygon($polygonCoords):array {
    // Asegura que el anillo esté cerrado
    if ($polygonCoords[0] !== end($polygonCoords)) {
        $polygonCoords[] = $polygonCoords[0];
    }

    $cleaned = [];
    $n = count($polygonCoords);

    // Recorre todos los puntos, excepto el último (que es igual al primero)
    for ($i = 0; $i < $n - 2; $i++) {
        $a = $polygonCoords[$i];
        $b = $polygonCoords[$i + 1];
        $c = $polygonCoords[$i + 2];

        $x1 = $a[0]; $y1 = $a[1];
        $x2 = $b[0]; $y2 = $b[1];
        $x3 = $c[0]; $y3 = $c[1];

        // Calcula el área del triángulo (doble)
        $area = abs(($x2 - $x1) * ($y3 - $y1) - ($y2 - $y1) * ($x3 - $x1));

        // Si el área es mayor que epsilon, B no es colineal
        if ($area > Algorithm::TOLERANCE) {
            $cleaned[] = $a;
        }
    }

    // Añade el penúltimo punto y el cierre
    $cleaned[] = $polygonCoords[$n - 2];
    $cleaned[] = $cleaned[0];

    return $cleaned;
}






    public static function canonicalizePolygons(array $polygons, ?int $precision): array
    {
        $canon = [];

        foreach ($polygons as $poly) {
            if (!is_array($poly) || count($poly) === 0) continue;

            // Canonizar exterior y huecos
            $ext = self::ringCanonical($poly[0], $precision);
            $holes = [];
            for ($i = 1; $i < count($poly); $i++) {
                $holes[] = self::ringCanonical($poly[$i], $precision);
            }

            // Ordenar huecos por clave
            usort($holes, function (array $a, array $b) use ($precision) {
                $ka = self::ringKey($a, $precision);
                $kb = self::ringKey($b, $precision);
                return $ka <=> $kb;
            });

            $canon[] = array_merge([$ext], $holes);
        }

        // Ordenar polígonos por clave (exterior y luego huecos)
        usort($canon, function (array $A, array $B) use ($precision) {
            $kA = self::polygonKey($A, $precision);
            $kB = self::polygonKey($B, $precision);
            return $kA <=> $kB;
        });

        return $canon;
    }

    /**
     * Crea una versión canónica del anillo:
     *  - Redondeo de coordenadas (si $precision != null)
     *  - Rotación para que empiece por el punto mínimo lexicográfico
     *  - Mantiene cierre del anillo
     */
    private static function ringCanonical(array $ring, ?int $precision): array
    {
        // Asegurar cierre por si acaso
        // $ring = self::closeRing($ring);

        // Redondeo (opcional)
        if ($precision !== null) {
            $ring = self::roundRing($ring, $precision);
        }

        // Rotar al mínimo lexicográfico
        $n = count($ring) - 1; // último es igual al primero
        $minIdx = 0;
        for ($i = 1; $i < $n; $i++) {
            if (self::comparePoint($ring[$i], $ring[$minIdx]) < 0) {
                $minIdx = $i;
            }
        }

        // Construir el anillo rotado
        $rot = array_merge(
            array_slice($ring, $minIdx, $n - $minIdx),
            array_slice($ring, 0, $minIdx)
        );
        // Cerrar otra vez
        $rot[] = [$rot[0][0], $rot[0][1]];

        return $rot;
    }

    /** Clave lexicográfica de anillo (JSON) */
    private static function ringKey(array $ring, ?int $precision): string
    {
        // ringCanonical ya asegura redondeo y rotación si se llamó antes;
        // aquí asumimos que $ring ya está canónico.
        return json_encode($ring, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Clave de polígono: exterior + '|' + claves de huecos */
    private static function polygonKey(array $polygon, ?int $precision): string
    {
        $parts = [ self::ringKey($polygon[0], $precision) ];
        for ($i = 1; $i < count($polygon); $i++) {
            $parts[] = self::ringKey($polygon[$i], $precision);
        }
        return implode('|', $parts);
    }

    /** Comparación lexicográfica de puntos [x,y] */
    private static function comparePoint(array $a, array $b): int
    {
        if ($a[0] < $b[0]) return -1;
        if ($a[0] > $b[0]) return 1;
        if ($a[1] < $b[1]) return -1;
        if ($a[1] > $b[1]) return 1;
        return 0;
    }

    /** Redondeo de todos los puntos del anillo */
    private static function roundRing(array $ring, int $precision): array
    {
        $out = [];
        foreach ($ring as $pt) {
            $out[] = [ round($pt[0], $precision), round($pt[1], $precision) ];
        }
        return $out;
    }

    /**
     * Devuelve solo las coordenadas estilo MultiPolygon:
     * [ polygon1, polygon2, ... ] donde cada polygon = [ exterior, hole1, hole2, ... ].
     *
     * @param array $rings
     * @return array
     */
    public static function ringsToCoordinates(array $rings): array
    {
        $geom = self::ringsToGeoJSON($rings, false); // forzar MultiPolygon
        return $geom['coordinates'];
    }

    /* ===================== Utilidades geométricas (privadas) ===================== */

    /**
     * Asegura que el anillo esté cerrado (primer punto == último).
     * @param array $ring
     * @return array
     */
    private static function closeRing(array $ring): array
    {
        if (!is_array($ring) || count($ring) < 3) {
            throw new \InvalidArgumentException("Anillo inválido.");
        }
        $first = $ring[0];
        $last  = $ring[count($ring) - 1];
        if ($first[0] === $last[0] && $first[1] === $last[1]) {
            return $ring;
        }
        $ring[] = [$first[0], $first[1]];
        return $ring;
    }

    /**
     * Área con signo (shoelace). CCW => área > 0, CW => área < 0.
     * @param array $ring
     * @return float
     */
    private static function signedArea(array $ring): float
    {
        $a = 0.0;
        $n = count($ring);
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $ring[$i][0]; $y1 = $ring[$i][1];
            $x2 = $ring[$i + 1][0]; $y2 = $ring[$i + 1][1];
            $a += ($x1 * $y2 - $x2 * $y1);
        }
        return $a / 2.0;
    }

    /**
     * Bounding box [minX, minY, maxX, maxY].
     * @param array $ring
     * @return array<string,float>
     */
    private static function bbox(array $ring): array
    {
        $minX = INF; $minY = INF; $maxX = -INF; $maxY = -INF;
        foreach ($ring as $pt) {
            $x = $pt[0]; $y = $pt[1];
            if ($x < $minX) $minX = $x;
            if ($y < $minY) $minY = $y;
            if ($x > $maxX) $maxX = $x;
            if ($y > $maxY) $maxY = $y;
        }
        return ['minX'=>$minX, 'minY'=>$minY, 'maxX'=>$maxX, 'maxY'=>$maxY];
    }

    /**
     * ¿B contiene a b (por bounding box)?
     * @param array $B
     * @param array $b
     * @return bool
     */
    private static function bboxContains(array $B, array $b): bool
    {
        return ($B['minX'] <= $b['minX'] && $B['minY'] <= $b['minY']
             && $B['maxX'] >= $b['maxX'] && $B['maxY'] >= $b['maxY']);
    }

    /**
     * Punto de prueba interior del anillo (primer vértice con pequeño offset).
     * @param array $ring
     * @return array
     */
    private static function interiorTestPoint(array $ring): array
    {
        $x = $ring[0][0];
        $y = $ring[0][1] + self::TEST_OFFSET;
        return [$x, $y];
    }

    /**
     * Ray casting (incluye borde como "dentro").
     * @param array{0:float,1:float} $point
     * @param array $ring
     * @return bool
     */
    private static function pointInRing(array $point, array $ring): bool
    {
        [$px, $py] = $point;
        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0]; $yi = $ring[$i][1];
            $xj = $ring[$j][0]; $yj = $ring[$j][1];

            if (self::onSegment([$xj, $yj], [$xi, $yi], [$px, $py])) return true;

            $den = ($yj - $yi);
            if (abs($den) < Algorithm::TOLERANCE) $den = ($den >= 0 ? Algorithm::TOLERANCE : -Algorithm::TOLERANCE);

            $intersect = (($yi > $py) !== ($yj > $py)) &&
                         ($px < ($xj - $xi) * ($py - $yi) / $den + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    /**
     * Comprueba si P está sobre el segmento AB.
     * @param array $a
     * @param array $b
     * @param array $p
     * @return bool
     */
    private static function onSegment(array $a, array $b, array $p): bool
    {
        $ax = $a[0]; $ay = $a[1];
        $bx = $b[0]; $by = $b[1];
        $px = $p[0]; $py = $p[1];

        // Colinealidad (área del paralelogramo ~ 0)
        $cross = ($bx - $ax) * ($py - $ay) - ($by - $ay) * ($px - $ax);
        if (abs($cross) > Algorithm::TOLERANCE) return false;

        // P dentro del rango de A-B
        $dot = ($px - $ax) * ($px - $bx) + ($py - $ay) * ($py - $by);
        return ($dot <= Algorithm::TOLERANCE);
    }

    /**
     * Orienta anillo como exterior (recomendado CCW).
     * @param array $ring
     * @param float $area
     * @return array
     */
    private static function orientAsExterior(array $ring, float $area): array
    {
        return ($area > 0.0) ? $ring : array_reverse($ring);
    }

    /**
     * Orienta anillo como hueco (recomendado CW).
     * @param array $ring
     * @param float $area
     * @return array
     */
    private static function orientAsHole(array $ring, float $area): array
    {
        return ($area < 0.0) ? $ring : array_reverse($ring);
    }
}

