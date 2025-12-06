<?php
declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class GJTools
{
    /** Offset para el punto de prueba interior (evitar caer justo en borde) */
    private const TEST_OFFSET = 1e-12;

    /**
     * Convierte un GeoJSON (archivo, string JSON o estructura ya decodificada) en un array normalizado de polígonos.
     *
     * Resultado: array de polígonos. Cada polígono = array de anillos. Cada anillo = array de [x, y].
     * - Para Polygon: un polígono con N anillos (1 exterior + K huecos).
     * - Para MultiPolygon: N polígonos, cada uno con sus anillos.
     * - Para Feature/FeatureCollection/GeometryCollection: se extraen todos los polígonos contenidos.
     * - Geometrías no poligonales (Point/LineString/etc.) se ignoran.
     *
     * @param string|array|object $geojsonSource Ruta a archivo .geojson, JSON en string o estructura ya decodificada.
     * @param bool $enforceOrientation Si true, fuerza anillos exteriores CCW y huecos CW (recomendación RFC 7946).
     * @return array Array de polígonos: [ [ [ [x,y], ... ], [holeRing], ... ], ... ]
     * @throws InvalidArgumentException Si el JSON no es válido o el objeto no es GeoJSON válido.
    */
    public static function geojsonToPolygons($geojsonSource, bool $enforceOrientation = false): array
    {

	// print "ORIGINAL> " . json_encode($geojsonSource) . PHP_EOL;
	// 1) Cargar/decodificar
	if (is_string($geojsonSource)) {
	    if (is_file($geojsonSource)) {
		$json = @file_get_contents($geojsonSource);
		if ($json === false) {
		    throw new \InvalidArgumentException("No se pudo leer el archivo: $geojsonSource");
		}
	    } else {
		// asumimos que es un string JSON
		$json = $geojsonSource;
	    }
	    $root = json_decode($json, true);
	    if ($root === null && json_last_error() !== JSON_ERROR_NONE) {
		throw new \InvalidArgumentException("JSON inválido: " . json_last_error_msg());
	    }
	} elseif (is_array($geojsonSource) || is_object($geojsonSource)) {
	    // convertir objeto stdClass a array asociativo recursivamente
	    $root = json_decode(json_encode($geojsonSource), true);
	} else {
	    throw new \InvalidArgumentException("Tipo de entrada no soportado.");
	}

	if (!is_array($root)) {
	    throw new \InvalidArgumentException("GeoJSON decodificado no es un objeto/array válido.");
	}

	// 2) Recorrer recursivamente y extraer polígonos
	$polygons = self::extractPolygonsRec($root, $enforceOrientation);
	// print "POLYGONS> " . json_encode($polygons) . PHP_EOL;

	// 3) Flatten
	// necesitamos aplanar los rings para tenerlos todos seguidos
	$polygons = self::flattenMultipolygon($polygons);

	// La salida de extract es siempre multipolygon!!
	// Comprobar si el resultado es Multipolygon o Polygon
/*	$type = self::detectGeometryTypeFromCoordinatesArray($polygons);
	print "TYPE> " . $type . PHP_EOL;

	$rings = [];
        if ($type === 'Polygon') {
            // $coordinates = [ [ring0], [ring1], ... ]
            foreach ($polygons as $ring) {
                $rings[] = $ring;
            }
        } elseif ($type === 'MultiPolygon') {
            // $coordinates = [ [ [ring0], [ring1], ... ], [ [ring0], ... ], ... ]
            foreach ($polygons as $polygon) {
                foreach ($polygon as $ring) {
                    $rings[] = $ring;
                }
            }
        } else {
            throw new InvalidArgumentException("Tipo no soportado: $type. Usa 'Polygon' o 'MultiPolygon'.");
        }
*/
	// print "flatted>  " . json_encode($polygons) . PHP_EOL; ;

	// averiguar cuales son interior y cuales son exterior, teniendo en cuenta que
	// los pares son exterior y los impares interior

	$nodes = self::classifyRings($polygons);

	// print "CLASSIF>  " . json_encode($nodes) . PHP_EOL;


	$polygons = self::buildPolygons($nodes, $enforceOrientation=true);

	// print "LAST>     " . json_encode($polygons) . PHP_EOL; ;

	// canonicalizePolygons 
        $tol = max(Algorithm::TOLERANCE, PHP_FLOAT_EPSILON);
        $digits = (int) max(0, round(-log10($tol)));
        $polygons = self::canonicalizePolygons($polygons, $digits);

	// print "CANONICAL>     " . json_encode($polygons) . PHP_EOL; ;

	// Asegurar que siempre devolvemos un array (posiblemente vacío)
	return array_values($polygons);
    }

    /**
     * Función recursiva que extrae polígonos desde cualquier nodo GeoJSON.
     */
    private static function extractPolygonsRec(array $node, bool $enforceOrientation): array
    {
	$out = [];

	if ( 0 == count($node) ) {
	    return $out;
	}

	// Caso raíz sin 'type' pero con 'features' (algunos exportadores)
	if (!isset($node['type']) && isset($node['features']) && is_array($node['features'])) {
	    foreach ($node['features'] as $feat) {
		$out = array_merge($out, self::extractPolygonsRec($feat, $enforceOrientation));
	    }
	    return $out;
	}

	// nos ha llegado un array de coordenadas, calcularemos el tipo pero siempre será o
	// Polygon o MultiPolygon
	if ( !isset($node['type']) && !isset($node['coordinates']) ) {
	    $new_node = [];
	    $new_node['type'] = self::detectGeometryTypeFromCoordinatesArray($node);
	    $new_node['coordinates'] = $node;
	    $node = $new_node;
	}

	$type = $node['type'] ?? null;

	switch ($type) {
	    case 'FeatureCollection':
		if (isset($node['features']) && is_array($node['features'])) {
		    foreach ($node['features'] as $feature) {
			$out = array_merge($out, self::extractPolygonsRec($feature, $enforceOrientation));
		    }
		}
		break;
	    case 'Feature':
		// La geometría puede ser null
		if (isset($node['geometry']) && is_array($node['geometry'])) {
		    $out = array_merge($out, self::extractPolygonsRec($node['geometry'], $enforceOrientation));
		}
		break;

	    case 'GeometryCollection':
		if (isset($node['geometries']) && is_array($node['geometries'])) {
		    foreach ($node['geometries'] as $geom) {
			if (is_array($geom)) {
			    $out = array_merge($out, self::extractPolygonsRec($geom, $enforceOrientation));
			}
		    }
		}
		break;

	    case 'Polygon':
		if (isset($node['coordinates']) && is_array($node['coordinates'])) {
		    $poly = self::normalizePolygon($node['coordinates'], $enforceOrientation);
		    if (!empty($poly)) {
			$out[] = $poly;
		    }
		}
		break;

	    case 'MultiPolygon':
		if (isset($node['coordinates']) && is_array($node['coordinates'])) {
		    foreach ($node['coordinates'] as $polyCoords) {
			if (is_array($polyCoords)) {
			    $poly = self::normalizePolygon($polyCoords, $enforceOrientation);
			    if (!empty($poly)) {
			        $out[] = $poly;
			    }
			}
		    }
		}
		break;

	    // Otros tipos se ignoran: Point, MultiPoint, LineString, MultiLineString
	    default:
		// Ignorar silenciosamente
		break;
	}

	return $out;
    }

    /**
     * Normaliza un polígono GeoJSON (array de LinearRings) a:
     * [ [ [x,y], ... ], [holeRing], ... ]
     * - El primer anillo es exterior, el resto son huecos.
     * - Elimina el punto de cierre duplicado si existe (GeoJSON usa anillos cerrados).
     * - Descarta dimensiones extra (z/m) conservando [x,y].
     * - Filtra anillos degenerados (< 4 puntos) y coordenadas no numéricas.
     * - Opcionalmente forza orientación: exterior CCW, huecos CW (RFC 7946).
     *
     * @param array $coords Estructura GeoJSON: [ ring0, ring1, ... ], ring = [ [x,y(,z...)], ... ]
     * @param bool $enforceOrientation
     * @return array
     */
    private static function normalizePolygon(array $coords/*, bool $enforceOrientation*/): array
    {
	$rings = [];

	foreach ($coords as $ringIdx => $ringCoords) {
	    if (!is_array($ringCoords)) {
		continue;
	    }

	    $ring = [];

	    foreach ($ringCoords as $pt) {
		if (!is_array($pt) || count($pt) < 2) {
		    continue;
		}
		$x = $pt[0];
		$y = $pt[1];

		// Validar numéricos
		if (!is_numeric($x) || !is_numeric($y)) {
		    continue;
		}

		$ring[] = [floatval($x), floatval($y)];
	    }

	    /*
	    // Eliminar punto de cierre duplicado si primero == último
	    if (count($ring) >= 2) {
		$first = $ring[0];
		$last  = $ring[count($ring) - 1];
		if ($first[0] === $last[0] && $first[1] === $last[1]) {
		    array_pop($ring);
		}
	    }
	    */

	    // Un anillo válido necesita al menos 4 puntos en GeoJSON (incl. cierre).
	    // Como removimos el cierre duplicado, exigimos >= 3 puntos distintos (triángulo).
	    if (count($ring) < 3) {
		// anillo degenerado → ignorar
		throw new \InvalidArgumentException("Anillo degenerado count(ring)<3.");
		continue;
	    }


	    $ring = self::dedupConsecutive($ring);
	    $ring = self::removeColinearPointsFromPolygon($ring);
	    $ring = self::closeRing($ring);

	    $rings[] = $ring;
	}

	if (empty($rings)) {
	    return [];
	}
/*
	if ($enforceOrientation) {
	    // Exterior: CCW; Huecos: CW
	    foreach ($rings as $i => $r) {
		$area = self::ringSignedArea($r);
		$isCCW = $area > 0; // Shoelace: >0 => CCW

		if ($i === 0) {
		    // Exterior debe ser CCW
		    if (!$isCCW) {
			$rings[$i] = array_reverse($r);
		    }
		} else {
		    // Hueco debe ser CW
		    if ($isCCW) {
			$rings[$i] = array_reverse($r);
		    }
		}
	    }
	}
*/
	return $rings;
    }

    /**
     * Área orientada mediante fórmula del zapatero (shoelace).
     * > 0 => CCW, < 0 => CW
     *
     * @param array $ring Array de [x,y]
     * @return float
     */
/*
    private static function ringSignedArea(array $ring): float
    {
	$n = count($ring);
	if ($n < 3) return 0.0;

	$sum = 0.0;
	for ($i = 0; $i < $n; $i++) {
	    $j = ($i + 1) % $n;
	    $sum += ($ring[$i][0] * $ring[$j][1]) - ($ring[$j][0] * $ring[$i][1]);
	}
	return $sum / 2.0;
    }
*/

    /**
     * Flattens a Multipolygon array so we can classify rings (exterior, interior, parent, child)
     * @param array polygons
     * @return array
     */
     private static function flattenMultipolygon(array $polygons): array
     {
	$rings = [];

	foreach ($polygons as $polygon) {
	    foreach ($polygon as $ring) {
		$rings[] = $ring;
            }
	}
	return $rings;
     }

    /**
     * Asegura que el anillo esté cerrado (primer punto == último).
     * @param array $ring
     * @return array
     */
    private static function closeRing(array $ring): array
    {
        if (!is_array($ring) || count($ring) < 3) {
            throw new \InvalidArgumentException("Anillo inválido: " . json_encode($ring), 101);
        }
        $first = $ring[0];
        $last  = $ring[count($ring) - 1];
        if ($first[0] === $last[0] && $first[1] === $last[1]) {
            return $ring;
        }
        $ring[] = [$first[0], $first[1]];
        return $ring;
    }

    public static function almostEq(float $a, float $b): bool {
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

    public static function removeColinearPointsFromPolygon($polygonCoords):array
    {
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

    /*
     * converts a polygon to an optimized standard form Polygon[{p1,p2,…},{outer1,outer2,inner2,…}].
     * The points pi are the endpoints of nonintersecting line segments and sorted into Sort order.
     */
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
     * Dada una cadena JSON que representa SOLO 'coordinates',
     * detecta si corresponde a un Polygon o a un MultiPolygon.
     *
     * @param string $coordinatesJson Cadena JSON de coordinates (p.ej. '[[[0,0],[1,0],[1,1],[0,1],[0,0]]]')
     * @return 'Polygon'|'MultiPolygon'
     * @throws InvalidArgumentException Si la cadena no es JSON válido o no tiene la estructura mínima.
     */
    public static function detectGeometryTypeFromCoordinatesString(string $coordinatesJson): string
    {
	$coords = json_decode(trim($coordinatesJson), true);
	if ($coords === null && json_last_error() !== JSON_ERROR_NONE) {
	    throw new InvalidArgumentException("JSON inválido en coordinates: " . json_last_error_msg());
	}
	if (!is_array($coords)) {
	    throw new InvalidArgumentException("coordinates debe decodificar a un array.");
	}
	if (empty($coords)) {
	    throw new InvalidArgumentException("coordinates no puede estar vacío.");
	}

	// 1) Validación estructural primero (más fiable)
	if (self::isPolygonCoordinates($coords)) {
	    return 'Polygon';
	}
	if (self::isMultiPolygonCoordinates($coords)) {
	    return 'MultiPolygon';
	}

	throw new InvalidArgumentException(
	    "La estructura de 'coordinates' no corresponde a Polygon ni MultiPolygon."
	);
    }

    /**
     * Dada una cadena JSON que representa SOLO 'coordinates',
     * detecta si corresponde a un Polygon o a un MultiPolygon.
     *
     * @param array $coordinatesJson Array JSON de coordinates (p.ej. [[[0,0],[1,0],[1,1],[0,1],[0,0]]] )
     * @return 'Polygon'|'MultiPolygon'
     * @throws InvalidArgumentException Si la cadena no es JSON válido o no tiene la estructura mínima.
     */
    public static function detectGeometryTypeFromCoordinatesArray(array $coords): string
    {
	//$coords = json_decode(trim($coordinatesJson), true);
	//if ($coords === null && json_last_error() !== JSON_ERROR_NONE) {
	//    throw new InvalidArgumentException("JSON inválido en coordinates: " . json_last_error_msg());
	//}
	if (!is_array($coords)) {
	    throw new InvalidArgumentException("coordinates debe decodificar a un array.");
	}
	if (empty($coords)) {
	    throw new InvalidArgumentException("coordinates no puede estar vacío.");
	}

	// 1) Validación estructural primero (más fiable)
	if (self::isPolygonCoordinates($coords)) {
	    return 'Polygon';
	}
	if (self::isMultiPolygonCoordinates($coords)) {
	    return 'MultiPolygon';
	}

	throw new InvalidArgumentException(
	    "La estructura de 'coordinates' no corresponde a Polygon ni MultiPolygon."
	);
    }

    /** Heurística: ¿parece array de rings (cada uno array de positions)? */
    private static function isPolygonCoordinates($coords): bool
    {
	if (!is_array($coords) || empty($coords)) return false;
	// Debe haber al menos un ring que contenga alguna posición [x,y]
	foreach ($coords as $ring) {
	    if (is_array($ring) && !empty($ring)) {
		// ¿contiene alguna position?
		foreach ($ring as $pos) {
		    if (self::looksLikePosition($pos)) {
			return true;
		    }
		}
	    }
	}
	return false;
    }

    /** Heurística: ¿parece array de polígonos, cada uno con rings que contienen positions? */
    private static function isMultiPolygonCoordinates($coords): bool
    {
	if (!is_array($coords) || empty($coords)) return false;
	foreach ($coords as $poly) {
	    if (!is_array($poly) || empty($poly)) continue;
	    foreach ($poly as $ring) {
	        if (is_array($ring)) {
		    foreach ($ring as $pos) {
			if (self::looksLikePosition($pos)) {
			    return true;
			}
		    }
		}
	    }
	}
	return false;
    }

    /** Devuelve true si $arr tiene pinta de posición [x,y(,z...)] */
    private static function looksLikePosition($arr): bool
    {
	if (!is_array($arr) || count($arr) < 2) return false;
	// Los dos primeros deben ser numéricos
	return is_numeric($arr[0]) && is_numeric($arr[1]);
    }

    /**
     * Construye un objeto GeoJSON de geometría a partir de la cadena de coordinates,
     * infiriendo automáticamente si es Polygon o MultiPolygon.
     *
     * @param string $coordinatesJson
     * @return array GeoJSON geometry: ['type' => 'Polygon'|'MultiPolygon', 'coordinates' => [...]]
     */
    public static function buildGeometryFromCoordinates($coordinatesJson): array
    {
	if ( is_array($coordinatesJson) ) {
	    $type = self::detectGeometryTypeFromCoordinatesArray($coordinatesJson);
	} else {
	    $type = self::detectGeometryTypeFromCoordinatesString($coordinatesJson);
	}
	$coords = json_decode(trim($coordinatesJson), true);
	return [
	    'type' => $type,
	    'coordinates' => $coords,
	];
    }


    /**
     * Compara dos arrays de coordenadas estilo MultiPolygon (p.ej. salida de ringsToCoordinates):
     * [ polygon1, polygon2, ... ], cada polygon = [ exterior, hole1, ... ], cada ring = [ [x,y], ... ]
     *
     * @param array      $coordsA
     * @param array      $coordsB
     * @param array|null $diff            (salida) detalles del primer desacuerdo encontrado.
     * @return bool
     */
    public static function compareCoordinates(
        array $coordsA,
        array $coordsB,
        ?array &$diff = null
    ): bool {
        $diff = null;

        // 1) Nº de polígonos
        $na = count($coordsA);
        $nb = count($coordsB);
        if ($na !== $nb) {
            $diff = ['where' => 'polygon_count', 'a' => $na, 'b' => $nb];
            return false;
        }

        // 2) Iterar polígonos, anillos y puntos
        for ($p = 0; $p < $na; $p++) {
            $polyA = $coordsA[$p];
            $polyB = $coordsB[$p];

            $ra = count($polyA);
            $rb = count($polyB);
            if ($ra !== $rb) {
                $diff = ['where' => 'ring_count', 'polygon' => $p, 'a' => $ra, 'b' => $rb];
                return false;
            }

            for ($r = 0; $r < $ra; $r++) {
                $ringA = $polyA[$r];
                $ringB = $polyB[$r];

                $pa = count($ringA);
                $pb = count($ringB);
                if ($pa !== $pb) {
                    $diff = ['where' => 'point_count', 'polygon' => $p, 'ring' => $r, 'a' => $pa, 'b' => $pb];
                    return false;
                }

                // Punto a punto
                for ($k = 0; $k < $pa; $k++) {
                    $ax = (float)$ringA[$k][0]; $ay = (float)$ringA[$k][1];
                    $bx = (float)$ringB[$k][0]; $by = (float)$ringB[$k][1];

                    if (!self::almostEq($ax, $bx)) {
                        $diff = [
                            'where'   => 'point_x',
                            'polygon' => $p, 'ring' => $r, 'point' => $k,
                            'a' => $ax, 'b' => $bx
                        ];
                        return false;
                    }
                    if (!self::almostEq($ay, $by)) {
                        $diff = [
                            'where'   => 'point_y',
                            'polygon' => $p, 'ring' => $r, 'point' => $k,
                            'a' => $ay, 'b' => $by
                        ];
                        return false;
                    }
                }
            }
        }

        return true;
    }




    /**
     * A partir de una lista de rings, calcula:
     * - parent: índice del ring que lo contiene más cercano (o null)
     * - depth: nivel de anidamiento (0,1,2,...)
     * - parity: 'exterior' (niveles pares) | 'interior' (niveles impares)
     * Devuelve además bbox y área para diagnóstico.
     *
     * @param array $rings Lista de rings (cada ring = [[x,y], ...] cerrado)
     * @return array Lista con metadatos: [
     *   [
     *     'ring' => [[x,y]...],
     *     'bbox' => [minx, miny, maxx, maxy],
     *     'area' => float (signed),
     *     'parent' => int|null,
     *     'depth' => int,
     *     'parity' => 'exterior'|'interior',
     *     'children' => [indices...]
     *   ],
     *   ...
     * ]
     */
    public static function classifyRings(array $rings): array
    {
        $n = count($rings);
        $nodes = [];

        // Precalcular bbox, área y punto de prueba (centroide)
        for ($i = 0; $i < $n; $i++) {
            $ring = $rings[$i];
            $bbox = self::bbox($ring);
            $area = self::signedArea($ring);
            $pt   = self::interiorPoint($ring); // punto para pruebas pinp

            $nodes[] = [
                'ring' => $ring,
                'bbox' => $bbox,
                'area' => $area,
                'testPoint' => $pt,
                'parent' => null,
                'depth' => 0,
                'parity' => 'exterior', // provisional
                'children' => []
            ];
        }

        // Buscar contenedor más pequeño para cada ring (O(n^2))
        for ($i = 0; $i < $n; $i++) {
            $bestParent = null;
            $bestAbsArea = INF;

            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;

                if (!self::bboxContains($nodes[$j]['bbox'], $nodes[$i]['bbox'])) {
                    continue;
                }

                // Un test point del hijo dentro del anillo j
                if (self::pointInRing($nodes[$i]['testPoint'], $nodes[$j]['ring'])) {
                    $absA = abs($nodes[$j]['area']);
                    if ($absA < $bestAbsArea) {
                        $bestAbsArea = $absA;
                        $bestParent = $j;
                    }
                }
            }

            $nodes[$i]['parent'] = $bestParent;
        }

        // Construir hijos y calcular depth
        for ($i = 0; $i < $n; $i++) {
            $p = $nodes[$i]['parent'];
            if ($p !== null) {
                $nodes[$p]['children'][] = $i;
            }
        }

        // Profundidad por BFS/DFS desde raíces (parent === null)
        $queue = [];
        for ($i = 0; $i < $n; $i++) {
            if ($nodes[$i]['parent'] === null) {
                $nodes[$i]['depth'] = 0;
                $queue[] = $i;
            }
        }
        while (!empty($queue)) {
            $cur = array_shift($queue);
            foreach ($nodes[$cur]['children'] as $ch) {
                $nodes[$ch]['depth'] = $nodes[$cur]['depth'] + 1;
                $queue[] = $ch;
            }
        }

        // Paridad
        for ($i = 0; $i < $n; $i++) {
            $nodes[$i]['parity'] = ($nodes[$i]['depth'] % 2 === 0) ? 'exterior' : 'interior';
        }

        // Limpieza: no devolvemos testPoint
        for ($i = 0; $i < $n; $i++) {
            unset($nodes[$i]['testPoint']);
        }

        return $nodes;
    }

    /** ==========================================
     *  3) CONSTRUCCIÓN DE MULTIPOLYGON(S) GeoJSON
     *  ==========================================
     */


    /**
     * Devuelve una LISTA DE POLÍGONOS.
     * Cada polígono: [ ringExterior, hole1, hole2, ... ].
     * Útil si luego quieres:
     *   - Polygon: elegir uno
     *   - MultiPolygon (único): envolver esta lista una vez
     */
    public static function buildPolygons(array $nodes, bool $enforceOrientation = false): array
    {
        // familias (raíces)
        $roots = [];
        foreach ($nodes as $i => $node) {
            if ($node['parent'] === null) $roots[] = $i;
        }

        $polygons = [];

        foreach ($roots as $root) {
            $family = self::collectSubtree($nodes, $root);
            foreach ($family as $idx) {
                if ($nodes[$idx]['parity'] === 'exterior') {
                    $exterior = $nodes[$idx]['ring'];
                    $holes = [];
                    foreach ($nodes[$idx]['children'] as $ch) {
                        if ($nodes[$ch]['parity'] === 'interior') {
                            $holes[] = $nodes[$ch]['ring'];
                        }
                    }

                    if ($enforceOrientation) {
                        $exterior = self::orientExteriorCCW($exterior);
                        $holes    = array_map([self::class, 'orientHoleCW'], $holes);
                    }

                    $polygons[] = array_merge([$exterior], $holes);
                }
            }
        }

        return $polygons; // <- SIN nivel extra de MultiPolygon[]
    }

    /**
     * Recolecta el subárbol (índices) desde un nodo raíz.
     */
    private static function collectSubtree(array $nodes, int $root): array
    {
        $out = [];
        $stack = [$root];
        while (!empty($stack)) {
            $cur = array_pop($stack);
            $out[] = $cur;
            foreach ($nodes[$cur]['children'] as $ch) {
                $stack[] = $ch;
            }
        }
        return $out;
    }

    /** ======================
     *  Geometría: utilidades
     *  ======================
     */

    /**
     * BBox [minx, miny, maxx, maxy]
     */
    private static function bbox(array $ring): array
    {
        $minx = $miny = INF;
        $maxx = $maxy = -INF;
        foreach ($ring as $pt) {
            $x = $pt[0]; $y = $pt[1];
            if ($x < $minx) $minx = $x;
            if ($y < $miny) $miny = $y;
            if ($x > $maxx) $maxx = $x;
            if ($y > $maxy) $maxy = $y;
        }
        return [$minx, $miny, $maxx, $maxy];
    }

    private static function bboxContains(array $A, array $B): bool
    {
        return $A[0] <= $B[0] && $A[1] <= $B[1] && $A[2] >= $B[2] && $A[3] >= $B[3];
    }

    /**
     * Área con signo por fórmula del polígono (shoelace). Cero si ring degenerado.
     */
    private static function signedArea(array $ring): float
    {
        $n = count($ring);
        if ($n < 4) return 0.0; // mínimo 3 puntos + cierre
        $sum = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $ring[$i][0];   $y1 = $ring[$i][1];
            $x2 = $ring[$i+1][0]; $y2 = $ring[$i+1][1];
            $sum += ($x1 * $y2 - $x2 * $y1);
        }
        return 0.5 * $sum; // CCW positivo
    }

    /**
     * Un punto “dentro” del ring: centroide si área!=0; si no, el primer vértice.
     */
    private static function interiorPoint(array $ring): array
    {
        $A = self::signedArea($ring);
        if ($A == 0.0) {
            return $ring[0];
        }
        $cx = 0.0; $cy = 0.0;
        $n = count($ring);
        $factor = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $ring[$i][0];   $y1 = $ring[$i][1];
            $x2 = $ring[$i+1][0]; $y2 = $ring[$i+1][1];
            $cross = ($x1 * $y2 - $x2 * $y1);
            $cx += ($x1 + $x2) * $cross;
            $cy += ($y1 + $y2) * $cross;
            $factor += $cross;
        }
        if ($factor == 0.0) return $ring[0];
        $cx = $cx / (3.0 * $factor);
        $cy = $cy / (3.0 * $factor);
        return [$cx, $cy];
    }

    /**
     * Test punto-en-ring (ray casting). Considera puntos en borde como DENTRO.
     */
    private static function pointInRing(array $pt, array $ring): bool
    {
        $x = $pt[0]; $y = $pt[1];
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0]; $yi = $ring[$i][1];
            $xj = $ring[$j][0]; $yj = $ring[$j][1];

            // Chequeo rápido de punto sobre segmento (opcional)
            if (self::pointOnSegment($pt, [$xj, $yj], [$xi, $yi])) {
                return true;
            }

            $intersect = (($yi > $y) != ($yj > $y)) &&
                         ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-30) + $xi);
            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    private static function pointOnSegment(array $p, array $a, array $b, float $eps = 1e-12): bool
    {
        $cross = ($b[0]-$a[0])*($p[1]-$a[1]) - ($b[1]-$a[1])*($p[0]-$a[0]);
        if (abs($cross) > $eps) return false;
        $dot = ($p[0]-$a[0])*($b[0]-$a[0]) + ($p[1]-$a[1])*($b[1]-$a[1]);
        if ($dot < -$eps) return false;
        $sqLen = ($b[0]-$a[0])**2 + ($b[1]-$a[1])**2;
        if ($dot - $sqLen > $eps) return false;
        return true;
    }

    /** ===========================
     *  Orientación opcional (RFC)
     *  ===========================
     *  GeoJSON recomienda (no exige) exteriores CCW y huecos CW.
     */
    private static function orientExteriorCCW(array $ring): array
    {
        return (self::signedArea($ring) >= 0) ? $ring : array_reverse($ring);
    }

    private static function orientHoleCW(array $ring): array
    {
        return (self::signedArea($ring) <= 0) ? $ring : array_reverse($ring);
    }












}
