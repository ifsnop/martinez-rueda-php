<?php

declare(strict_types=1);
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
    10 => [ // UNION DE UN MULTIPOLIGON Y UN POLIGON DA COMO RESULTADO UN POLIGON
	'region_a' => [ [[ [0,0], [0,1], [1,1], [0,0] ]], [[ [2,2], [2,3], [3,3], [2,2] ]] ],
	'region_b' => [[ [0,0], [3,0], [3,3], [0,3], [0,0] ]],
	'res' => [
	    'union' => [[ [0,0], [3,0], [3,3], [0,3], [0,0] ]],
	],
    ],
];

/*

$p =
[
    [
        [[-76.509, 38.751], [-76.343, 38.714], [-76.37267128027678, 38.74011072664357], [-76.339, 38.721], [-76.377, 38.782], [-76.509, 38.751] ],
        [[-76.377, 38.782], [-76.37602658486708, 38.743063394683034], [-76.393, 38.758], [-76.385, 38.765], [-76.377, 38.782]]
    ],

    [
        [[-76.388, 38.755], [-76.377, 38.749], [-76.383, 38.756], [-76.388, 38.755]]
    ]
];

print json_encode($p) . PHP_EOL;
$p = MR\GJTools::toGeoJSONFromRingsOrGeometry($p, preferPolygon: false, allowNestedHoles: true);
print json_encode($p) . PHP_EOL . PHP_EOL;


exit(0);

*/
/*
$t = [ [[18.1054513,60.4585421],[18.105679846052,60.458514506685],[18.1057195,60.4586056],[18.10556875,60.458569907886],[18.1055,60.4587],[18.1054513,60.4585421]]
    ,
    [[18.10563,60.4584],[18.1059,60.4584],[18.1058,60.4585],[18.105679846052,60.458514506685],[18.10563,60.4584]] ];
$t = [[[-1,0],[0,-1],[0,0]],[[1,0],[0,0],[0,1]]];

$t_normalized = MR\GJTools::geojsonToPolygons($t);
print json_encode($t) . PHP_EOL;
print json_encode($t_normalized) . PHP_EOL; 
exit(1);
*/

$diff = [];

$op1 =  [[[[5,5], [5,6], [6,6] ]]];
$op2 =  [[[[0,0], [1,0], [1,1] ]]]; //[[ [0,0], [0,1], [1,1], [0,0] ]]; //, [[ [2,2], [2,3], [3,3], [2,2] ]] ];
$op1_mr = MR\Polygon::create()->fillFromArray($op1);
$op2_mr = MR\Polygon::create()->fillFromArray($op2);
$result_op = MR\Algorithm::union($op1_mr, $op2_mr)->getArray();

print "op1: " . json_encode($op1) . PHP_EOL;
print "op2: " . json_encode($op2) . PHP_EOL;
print "rop : " . json_encode($result_op) . PHP_EOL;

exit(1);



$t1 = MR\GJTools::geojsonToPolygons($op1);
$t2 = MR\GJTools::geojsonToPolygons($op2);

print "t1 : " . json_encode($t1) . PHP_EOL;
print "t2 : " . json_encode($t2) . PHP_EOL;

$result_t = MR\Algorithm::union($t1, $t2)->getArray();

print "rres: " . json_encode($result_t) . PHP_EOL;

exit(1);


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

$fail = false;
foreach( $test as $test_number => $test_predicates ) {
    $pa = MR\Polygon::create()->fillFromArray($test_predicates['region_a']);
    $pb = MR\Polygon::create()->fillFromArray($test_predicates['region_b']);

    foreach ( $test_predicates['res'] as $op => $expected ) {
	$diff = array();
	$result = MR\Algorithm::$op($pa, $pb)->getArray();

	print "PA" . PHP_EOL . json_encode($test_predicates['region_a']) . PHP_EOL;
	print "PB" . PHP_EOL . json_encode($test_predicates['region_b']) . PHP_EOL;
	print "RE" . PHP_EOL . json_encode($expected) . PHP_EOL;
	print "RS" . PHP_EOL . json_encode($result) . PHP_EOL . PHP_EOL;
	// continue;

	// soporta como entrada Polygon o MultiPolygon
	$result_normalized = MR\GJTools::geojsonToPolygons($result);
	//print "RESULT NORMALIZED" . PHP_EOL . json_encode($result_normalized) . PHP_EOL;
	$expected_normalized = MR\GJTools::geojsonToPolygons($expected);
	// print "EXPECTED NORMALIZED" . PHP_EOL . json_encode($expected_normalized) . PHP_EOL;
	//exit(1);
	$ret = MR\GJTools::compareCoordinates($result_normalized, $expected_normalized, $diff);

	if ( $ret ) {
	    print "Result PASS {$test_number} {$op}" . PHP_EOL;
	} else {
	    print "Result FAIL {$test_number} {$op}" . PHP_EOL;
	    print "OPA" . PHP_EOL . json_encode(MR\GJTools::geojsonToPolygons($test_predicates['region_a'])) . PHP_EOL; // MR\GJTools::ringsToCoordinates($test_predicates['region_a'])[0]) . PHP_EOL;
	    print "OPB" . PHP_EOL . json_encode(MR\GJTools::geojsonToPolygons($test_predicates['region_b'])) . PHP_EOL; // MR\GJTools::ringsToCoordinates($test_predicates['region_a'])[0]) . PHP_EOL;
	    // print "OPB" . PHP_EOL . json_encode(MR\GJTools::ringsToCoordinates($test_predicates['region_b'])[0]) . PHP_EOL;
	    // print json_encode($expected[0]) . PHP_EOL;
	    print "EXPECTED" . PHP_EOL . json_encode($expected) . PHP_EOL;
	    print "GOT" . PHP_EOL . json_encode($result) . PHP_EOL;
	    print "EXPECTED NORMALIZED" . PHP_EOL . json_encode($expected_normalized) . PHP_EOL;
	    print "GOT NORMALIZED" . PHP_EOL . json_encode($result_normalized) . PHP_EOL;
	    // print "GoN: " . json_encode($result) . PHP_EOL;
	    $fail = true;
	    // exit(1);
	}
    }
}

if ( $fail )
    exit(1);

exit(0);


// UNION DE DOS POLIGONOS INDEPENDIENTES
$region_a = MR\GJTools::ringsToCoordinates([[[0,0], [2,0], [1,1]]]);
$region_b = MR\GJTools::ringsToCoordinates([[[0,0], [3,0], [3,-1]]]);
$pa = MR\Polygon::create()->fillFromArray($region_a[0]);
$pb = MR\Polygon::create()->fillFromArray($region_b[0]);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print "INDEPENDIENTES" . PHP_EOL; // . json_encode($result) . PHP_EOL;
print json_encode($region_a[0]) . PHP_EOL;
print json_encode($region_b[0]) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;;

exit(0);




// UNION DE DOS POLIGONOS UNIDOS POR UN VERTICE, DEBE GENERAR DOS POLIGONOS
$region_a = MR\GJTools::ringsToCoordinates( [[[0,0], [1,0], [0,1],[0,0]]] );
$region_b = MR\GJTools::ringsToCoordinates( [[[0,0],[-1,0],[0,-1],[0,0]]] );
$pa = MR\Polygon::create()->fillFromArray($region_a[0]);
$pb = MR\Polygon::create()->fillFromArray($region_b[0]);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print "INDEPENDIENTES" . PHP_EOL; // . json_encode($result) . PHP_EOL;
print json_encode($region_a[0]) . PHP_EOL;
print json_encode($region_b[0]) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;;
//RESULTADO [[[[-1,0],[0,-1],[0,0],[-1,0]]],[[[0,0],[1,0],[0,1],[0,0]]]]


// UNION DE DOS POLIGONOS INDEPENDIENTES
$region_a = MR\GJTools::ringsToCoordinates([[[0,0], [2,0], [1,1]]]);
$region_b = MR\GJTools::ringsToCoordinates([[[0,0], [3,0], [3,-1]]]);
$pa = MR\Polygon::create()->fillFromArray($region_a[0]);
$pb = MR\Polygon::create()->fillFromArray($region_b[0]);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print "INDEPENDIENTES" . PHP_EOL; // . json_encode($result) . PHP_EOL;
print json_encode($region_a[0]) . PHP_EOL;
print json_encode($region_b[0]) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;;


exit(0);

// UNION DE DOS POLIGONOS INDEPENDIENTES
$region_a = [[[0,0],[0,1],[1,1],[1,0]]];
$region_b = [[[4,4],[4,5],[5,5],[5,4]]];
$pa = MR\Polygon::create()->fillFromArray($region_a);
$pb = MR\Polygon::create()->fillFromArray($region_b);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print "INDEPENDIENTES: " . json_encode($result) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;;

// DIFERENCIA DE UN POLIGONO DENTRO DE OTRO
$region_a = [[[0,0],[3,0],[3,3],[0,3]]];
$region_b = [[[1,1],[2,1],[2,2],[1,2]]];
$pa = MR\Polygon::create()->fillFromArray($region_a);
$pb = MR\Polygon::create()->fillFromArray($region_b);
$result = MR\Algorithm::difference($pa, $pb)->getArray();
print "CON HUECO: " . json_encode($result) . PHP_EOL;

$region_a = [[[0,0],[4,0],[4,3],[0,3]]];
$region_b = [[[2,-1],[6,-1],[6,2],[2,2]]];
$pa = MR\Polygon::create()->fillFromArray($region_a);
$pb = MR\Polygon::create()->fillFromArray($region_b);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print PHP_EOL;
print json_encode($region_a) . PHP_EOL;
print json_encode($region_b) . PHP_EOL;
print "resultado: " . json_encode($result) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL . PHP_EOL;


$region_a = [[[0,0],[1,0],[1,1],[0,0]]];
$region_b = [[[0,0],[1,1],[0,1],[0,0]]];
$pa = MR\Polygon::create()->fillFromArray($region_a);
$pb = MR\Polygon::create()->fillFromArray($region_b);
$result = MR\Algorithm::union($pa, $pb)->getArray();
print PHP_EOL;
print json_encode($region_a) . PHP_EOL;
print json_encode($region_b) . PHP_EOL;
print "resultado: " . json_encode($result) . PHP_EOL;
print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;

//print json_encode(MR\GJTools::ringsToCoordinates($result)) . PHP_EOL;
//print json_encode(MR\GJTools::ringsToGeoJSON($result)) . PHP_EOL;

exit(0);
