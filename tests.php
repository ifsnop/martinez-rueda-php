<?php
declare(strict_types=1);

/*
 * Run some simple tests, just to verify nothing basic has broken.
 * Use phpunit to get full testing coverage.
 *
 */

error_reporting(E_ALL);
ini_set("display_errors", '1');

use Ifsnop\MartinezRueda as MR;

include_once('autoloader.php');

$test = [
    0 => [
	'region_a' => [[[0,0], [0,1], [1,1], [1,0]]],
	'region_b' => [[[1,0], [2,0], [2,1], [1,1]]],
	'res' => [
	    'union' => [[[2,1], [2,0], [0,0], [0,1]]],
	],
    ],
    1 => [
	'region_a' => [[[0,0],[-1,0],[-1,1],[0,1],[-0.5,0.5]]],
	'region_b' => [[[0,0],[-2,2],[0,2]]],
	'res' => [
	    'union' => [[[0,2],[0,0],[-1,0],[-1,1],[-2,2]]],
	    'intersect' => [[[-1,1],[-0.5,0.5],[0,1]]],
	],
    ],
    2 => [
	'region_a' => [[ [3.1827730120236866, -14.647299060696893],
	    [3.292779172743269, -14.709442780204576],
	    [3.2790977409099513, -15.096879410975502] ]],
	'region_b' => [[  [3.3984917402267456, -14.277922392125454],
	    [3.3984917402267456, -14.919993494289331],
	    [3.2411990917407074, -14.919993494289326] ]],
	'res' => [
	    'difference' => [[[3.1827730120236866, -14.647299060696893],
		[3.2790977409099513, -15.096879410975502],
		[3.2853440594539682, -14.919993494289328],
		[3.2411990917407074, -14.919993494289326],
		[3.292779172743269, -14.709442780204576]]],
	],
    ],
    3 => [ // three triangles
	'region_a' => [[ [-1,1], [1,-1], [1,1] ]],
	'region_b' => [[ [0,0], [0,1], [1,0] ]],
	'res' => [
	    'union' => [[[-1,1],[1,-1],[1,1]]],
	    'difference' => [[[-1,1],[0,0],[0,1]],[[0,0],[1,-1],[1,0]],[[0,1],[1,0],[1,1]]],

	],
    ],
    4 => [ // end-to-end/vertical-intersection-rounding-error
	'region_a' => [[ [-0.1,49], [0.1,49], [-0.1,50], [-0.1,49] ]],
	'region_b' => [[  [-1.1741342,50.6250111], [0.0001,49.32584697546245], [0.0001,50.6251], [-1.1741342,50.6250111] ]],
	'res' => [
	    'union' => [[[0.1,49],[-0.1,49],[-0.1,49.43659688282012],[-1.1741342,50.6250111],[0.0001,50.6251],[0.0001,49.4995]]],
	],
    ],
    5 => [ // https://github.com/mfogel/polygon-clipping/blob/main/test/end-to-end/almost-colinear-segments-but-not-2/union.geojson
	'region_a' =>  [[ [-75.727,45.361],[-75.723,45.354],[-75.723,45.36],[-75.727,45.361] ]],
	'region_b' => [[ [-75.73,45.365],[-75.723,45.36],[-75.727,45.354],[-75.73,45.365] ]],
	'res' => [
	    'union' => [[[-75.73,45.365],[-75.727,45.354],[-75.72484615384616,45.35723076923077],[-75.723,45.354],[-75.723,45.36]]],
	],
    ],
    6 => [
	'region_a' => [[[-89.1214798,30.2253957],[-89.12099375,30.225243895877554],[-89.1205,30.224],[-89.1205,30.226],[-89.1214798,30.2253957]]],
	'region_b' => [[[-89.12119375,30.223],[-89.12,30.223],[-89.12,30.2251544],[-89.1207072,30.2251544],[-89.12119375,30.225306360283458],[-89.12119375,30.223]]],
	'res' => [
	    'difference' => [[[-89.1214798,30.2253957],[-89.1207072,30.2251544],[-89.1205,30.2251544],[-89.1205,30.226]]],
	],
    ],
    7 => [ // UNION DE DOS POLIGONOS UNIDOS POR UN VERTICE, DEBE GENERAR DOS POLIGONOS
	'region_a' => [[[0,0], [1,0], [0,1],[0,0]]],
	'region_b' => [[[0,0],[-1,0],[0,-1],[0,0]]],
	'res' => [
	    'union' => [[[-1,0],[0,-1],[0,0],[-1,0]],[[0,0],[0,1],[1,0],[0,0]]],
	],
    ],
    8 => [
	'region_a' => [[[150.873, -10.017],[150.867925, -10.013013],[150.8708803653717, -10.01678734229192]]],
	'region_b' => [[[150.873, -10.017],[150.871475815773, -10.0166398791],[150.87071943283, -10.01682719716]]],
	'res' => [
	    'union' => [[[150.873,-10.017],[150.87071943283,-10.01682719716],[150.87088036534647,-10.016787342259704],[150.867925,-10.013013]]],
	],
    ],
    9 => [
	'region_a' =>  [[[18.1054513,60.4585421],[18.10556875,60.45856990788591],[18.1055,60.4587],[18.1054513,60.4585421]]],
        'region_b' =>  [[[18.1054513,60.4585421],[18.1057195,60.4586056],[18.10563,60.4584],[18.1059,60.4584],[18.1058,60.4585],[18.1054513,60.4585421]]],
	'res' => [
	    'union' => [
		[[18.1054513,60.4585421],[18.105679846052,60.458514506685],[18.1057195,60.4586056],[18.10556875,60.458569907886],[18.1055,60.4587],[18.1054513,60.4585421]]
		,
		[[18.10563,60.4584],[18.1059,60.4584],[18.1058,60.4585],[18.105679846052,60.458514506685],[18.10563,60.4584]]

	    ],
	],
    ],
    10 => [ // UNION DE UN MULTIPOLYGON Y UN POLYGON DA COMO RESULTADO UN POLYGON
	'region_a' => [ [[ [0,0], [0,1], [1,1], [0,0] ]], [[ [2,2], [2,3], [3,3], [2,2] ]] ],
	'region_b' => [[ [0,0], [3,0], [3,3], [0,3], [0,0] ]],
	'res' => [
	    'union' => [[ [0,0], [3,0], [3,3], [0,3], [0,0] ]],
	],
    ],
    11 => [ // XOR de dos cuadrados que se dan un mordisco
	'region_a' => [ [[0,0],[2,0],[2,2],[0,2],[0,0]] ],
	'region_b' => [ [[1,1],[3,1],[3,3],[1,3],[1,1]] ],
	'res' => [
	    'union' => [[[[0,0],[2,0],[2,1],[3,1],[3,3],[1,3],[1,2],[0,2],[0,0]]]],
	    'xoring' => [[[[0,0],[2,0],[2,1],[1,1],[1,2],[0,2],[0,0]]],[[[1,2],[2,2],[2,1],[3,1],[3,3],[1,3],[1,2]]]],
	],
    ],
];

/*
$diff = [];

$op1_1 = [[ [0,0], [0,1], [1,1], [1,0], [0,0] ]];
$op1_2 = [[ [2,2], [2,3], [3,3], [3,2], [2,2] ]];
$op2 = [[[0.25,0.25],[2.75,0.25],[2.75,2.75],[0.25,2.75],[0.25,0.25]]];

$op1_mr = MR\Polygon::create()->fillFromArray($op1_1)->fillFromArray($op1_2);
$op2_mr = MR\Polygon::create()->fillFromArray($op2);
$result_polygon = MR\Algorithm::union($op1_mr, $op2_mr);
$result_op = $result_polygon->getArray();
$result_n = MR\GJTools::geojsonToPolygons($result_op);

print "op1_1: " . json_encode($op1_1) . PHP_EOL;
print "op1_1n " . json_encode(MR\GJTools::geojsonToPolygons($op1_1)) . PHP_EOL;
print "op1_2: " . json_encode($op1_2) . PHP_EOL;
print "op1_2n " . json_encode(MR\GJTools::geojsonToPolygons($op1_2)) . PHP_EOL;
print "op2  : " . json_encode($op2) . PHP_EOL;
print "op2n : " . json_encode(MR\GJTools::geojsonToPolygons($op2)) . PHP_EOL;
print "rop  : " . json_encode($result_op) . PHP_EOL;
print "rop_n: " . json_encode($result_n) . PHP_EOL;

//var_dump($result_polygon);
exit(1);
*/

/*
$argsPath = "tests/end-to-end/issue-38/args.geojson";
$expectedPath = "tests/end-to-end/issue-38/union.geojson";

$argsPolygon = MR\GJTools::geojsonToPolygons($argsPath);
$expectedPolygon = MR\GJTools::geojsonToPolygons($expectedPath);

print json_encode($argsPolygon) . PHP_EOL;
print json_encode($expectedPolygon) . PHP_EOL;

exit(0);
*/

// MR
// el algoritmo espera poligonos como entrada (que están formados por un anillo de
// contorno exterior y n anillos representando agujeros.

// el algoritmo devuelve o bien polígonos o bien listas de polígonos, dependiendo
// del resultado de la operación.


$mp = MR\GJTools::geojsonToArray("tests/continents_australia.json");
$pa = MR\Polygon::create()->fillFromArray($mp);
$mp_normalized = MR\GJTools::geojsonToArray($mp);

$shifted_mp = displaceMultiPolygon($mp, -0.5);
// print json_encode($shifted_mp) . PHP_EOL; exit(1);


$shifted_pa = MR\Polygon::create()->fillFromArray($shifted_mp);

#print "original #" . $pa->numPoints . PHP_EOL;
#print "displaced #" . $shifted_pa->numPoints . PHP_EOL;

$result = MR\Algorithm::xoring($pa, $shifted_pa); // devuelve un class Polygon
$result_normalized = MR\GJTools::geojsonToArray($result->getArray()); // devuelve un array
print json_encode($result_normalized) . PHP_EOL;
exit(1);
print "result #" . $result->numPoints . PHP_EOL;
/*
$result_normalized = MR\GJTools::geojsonToArray($result->getArray()); // devuelve un array

$result_normalized_pa = MR\Polygon::create()->fillFromArray($result_normalized);
//print json_encode($result_normalized) . PHP_EOL;

$shifted_re = displaceMultiPolygon($result_normalized, -0.5);
$shifted_pa2 = MR\Polygon::create()->fillFromArray($shifted_re);

$result = MR\Algorithm::xoring($result_normalized_pa, $shifted_pa2);
print "result 2#" . $result->numPoints . PHP_EOL;
*/
//$result_normalized = MR\GJTools::geojsonToPolygons($result->getArray());
///print json_encode($mp_normalized) . PHP_EOL;
//exit(0);
//print json_encode($result_normalized) . PHP_EOL;

//exit(0);


$fail = false;
$debug = false;
foreach( $test as $test_number => $test_predicates ) {
    $pa = MR\Polygon::create()->fillFromArray($test_predicates['region_a']);
    $pb = MR\Polygon::create()->fillFromArray($test_predicates['region_b']);

    foreach ( $test_predicates['res'] as $op => $expected ) {
	$diff = array();
	$result = MR\Algorithm::$op($pa, $pb)->getArray();

	if ( $debug ) print "PA" . PHP_EOL . json_encode($test_predicates['region_a']) . PHP_EOL;
	if ( $debug ) print "PB" . PHP_EOL . json_encode($test_predicates['region_b']) . PHP_EOL;
	if ( $debug ) print "RE" . PHP_EOL . json_encode($expected) . PHP_EOL;
	if ( $debug ) print "RS" . PHP_EOL . json_encode($result) . PHP_EOL . PHP_EOL;

	// soporta como entrada Polygon o MultiPolygon
	$result_normalized = MR\GJTools::geojsonToArray($result);
	// print "RESULT NORMALIZED" . PHP_EOL . json_encode($result_normalized) . PHP_EOL;
	$expected_normalized = MR\GJTools::geojsonToArray($expected);
	// print "EXPECTED NORMALIZED" . PHP_EOL . json_encode($expected_normalized) . PHP_EOL;
	//exit(1);
	$ret = MR\GJTools::compareCoordinates($result_normalized, $expected_normalized, $diff);

	if ( $ret ) {
	    print "Result PASS {$test_number} {$op}" . PHP_EOL;
	} else {
	    print "Result FAIL {$test_number} {$op}" . PHP_EOL;
	    print "OPA" . PHP_EOL . json_encode(MR\GJTools::geojsonToArray($test_predicates['region_a'])) . PHP_EOL; // MR\GJTools::ringsToCoordinates($test_predicates['region_a'])[0]) . PHP_EOL;
	    print "OPB" . PHP_EOL . json_encode(MR\GJTools::geojsonToArray($test_predicates['region_b'])) . PHP_EOL; // MR\GJTools::ringsToCoordinates($test_predicates['region_a'])[0]) . PHP_EOL;
	    print "EXPECTED" . PHP_EOL . json_encode($expected) . PHP_EOL;
	    print "GOT" . PHP_EOL . json_encode($result) . PHP_EOL;
	    print "EXPECTED NORMALIZED" . PHP_EOL . json_encode($expected_normalized) . PHP_EOL;
	    print "GOT NORMALIZED" . PHP_EOL . json_encode($result_normalized) . PHP_EOL;
	    $fail = true;
	}
    }
}

if ( $fail )
    exit(1);

exit(0);



/**
 * Displace all points in a GeoJSON MultiPolygon by a given amount.
 *
 * @param array $multiPolygon A MultiPolygon as nested arrays of coordinates:
 *                            [[[ [x, y], [x, y], ... ] /x ring x/, ...] /x polygon x/, ...]
 *                            This matches GeoJSONs "coordinates" for MultiPolygon.
 * @param float $dx Displacement along X (longitude). If $dy is null, this value is used for both x and y.
 * @param float|null $dy Displacement along Y (latitude). If null, $dy = $dx.
 * @param bool $closeRings Ensure rings are closed after displacement (last point equals first).
 * @return array Displaced MultiPolygon (same structure).
 * @throws InvalidArgumentException If structure is invalid.
 */
function displaceMultiPolygon(array $multiPolygon, float $dx, ?float $dy = null, bool $closeRings = true): array
{
    if ($dy === null) {
        $dy = $dx;
    }

    // Validate minimal structure: must be array of polygons
    if (!is_array($multiPolygon)) {
        throw new InvalidArgumentException('MultiPolygon must be an array.');
    }

    $out = [];

    foreach ($multiPolygon as $polyIndex => $polygon) {
        if (!is_array($polygon)) {
            throw new InvalidArgumentException("Polygon #{$polyIndex} must be an array of rings.");
        }

        $outPolygon = [];

        foreach ($polygon as $ringIndex => $ring) {
            if (!is_array($ring) || count($ring) < 4) {
                throw new InvalidArgumentException("Ring #{$ringIndex} in polygon #{$polyIndex} must have at least 4 coordinates (closed linear ring).");
            }

            $ringOut = [];
            foreach ($ring as $coordIndex => $coord) {
                if (!is_array($coord) || count($coord) < 2) {
                    throw new InvalidArgumentException("Coordinate #{$coordIndex} in ring #{$ringIndex}, polygon #{$polyIndex} must be [x, y].");
                }
                $x = $coord[0];
                $y = $coord[1];

                // Optional: keep extra dimensions (e.g., altitude, measure)
                $rest = array_slice($coord, 2);

                $ringOut[] = array_values(array_merge([$x + $dx, $y + $dy], $rest));
            }

            // Ensure ring closure if requested
            if ($closeRings) {
                $first = $ringOut[0];
                $last  = end($ringOut);
                // Compare x,y only; keep any extra dims from first vertex
                if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
                    $ringOut[] = $first;
                }
            }

            $outPolygon[] = $ringOut;
        }

        $out[] = $outPolygon;
    }

    return $out;
}
