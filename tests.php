<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

use Ifsnop\MartinezRueda as MR;

include_once('autoloader.php');

$test = [
    0 => [
	'region_a' => [[[0,0], [0,1], [1,1], [1,0]]],
	'region_b' => [[[1,0], [2,0], [2,1], [1,1]]],
	'result_union' => [[[2,1], [2,0], [0,0], [0,1]]],
    ],
    1 => [
	'region_a' => [[[0,0],[-1,0],[-1,1],[0,1],[-0.5,0.5]]],
	'region_b' => [[[0,0],[-2,2],[0,2]]],
	'result_union' => [[[0,2],[0,0],[-1,0],[-1,1],[-2,2]]],
	'result_intersect' => [[[-1,1],[-0.5,0.5],[0,1]]],
    ],
];

foreach( $test as $test_number => $test_predicates ) {
    $pa = MR\Polygon::create()->fillFromArray($test_predicates['region_a']);
    $pb = MR\Polygon::create()->fillFromArray($test_predicates['region_b']);
    $result = MR\Algorithm::union($pa, $pb)->getArray();

    if ( MR\Algorithm::arrays_are_equal($result, $test_predicates['result_union']) )
	print "Result PASS {$test_number}" . PHP_EOL;
    else
	print "Result FAIL {$test_number}" . PHP_EOL;

    if ( !isset($test_predicates['result_intersect']) )
	continue;

    $result = MR\Algorithm::intersect($pa, $pb)->getArray();
    if ( MR\Algorithm::arrays_are_equal($result, $test_predicates['result_intersect']) )
	print "Result PASS {$test_number} (intersect)" . PHP_EOL;
    else
	print "Result FAIL {$test_number} (intersect)" . PHP_EOL;

}

exit(0);



$region_a = [[[0,0],[-1,0],[-1,1],[0,1],[-0.5,0.5]]];
$region_b = [[[0,0],[-2,2],[0,2]]];
$result_union = [[[0,2],[0,0],[-1,0],[-1,1],[-2,2]]];
$result_intersect = [[[-1,1],[-0.5,0.5],[0,1]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa, $pb)->getArray();
if ( arrays_are_equal($result, $result_union) ) {
    print "OK 2" . PHP_EOL;
}
$result = intersect($pa, $pb)->getArray();
if ( arrays_are_equal($result, $result_intersect) ) {
    print "OK 3" . PHP_EOL;
}

$region_a = [[[-5.69091796875,75.50265886674975],[-6.218261718749999,75.29215785826014],[-6.87744140625,74.8219342035653],[-5.38330078125,74.61344527005673],[-3.27392578125,74.78737860165963],[-2.83447265625,75.26423875224219],[-3.251953125,75.59040636514479],[-5.69091796875,75.50265886674975]]];
$region_b = [[[-1.4501953125,75.1125778338579],[-1.9116210937499998,75.40331785380344],[-3.2958984375,75.49165372814439],[-3.80126953125,75.33672086232664],[-5.5810546875,74.95939165894974],[-7.31689453125,74.62510096387147],[-5.515136718749999,74.15208909789665],[-4.19677734375,74.86215220305225],[-2.373046875,74.55503734449476],[-1.4501953125,75.1125778338579]]];
$result_union = [[[-1.4501953125,75.1125778338579],[-2.373046875,74.55503734449476],[-3.5953601631730767,74.7608739958216],[-4.527530738644315,74.68400974275426],[-5.515136718749999,74.15208909789665],[-7.31689453125,74.62510096387147],[-6.539602834083374,74.77479298912367],[-6.87744140625,74.8219342035653],[-6.218261718749999,75.29215785826014],[-5.69091796875,75.50265886674975],[-3.251953125,75.59040636514479],[-3.110402964367213,75.47981657363235],[-1.9116210937499998,75.40331785380344]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa,$pb)->getArray();
if ( arrays_are_similar($result,$result_union) ) {
    print "OK 4" . PHP_EOL;
}

$region_a = [[[-4.1748046875, 75.52464464250062], [-6.701660156249999, 75.52464464250062], [-6.74560546875, 74.44346576284508], [-3.75732421875, 74.44935750063425], [-3.7353515625, 74.76429887097666], [-4.8779296875, 74.76718570583334], [-4.866943359375, 75.30331101068566], [-3.8452148437499996, 75.30331101068566], [-3.8452148437499996, 75.52464464250062], [-4.1748046875, 75.52464464250062]]];
$region_b = [[[-4.383544921875, 75.59587329063447], [-4.427490234375, 74.36371391783985], [-2.6806640625, 74.36667478672423], [-2.65869140625, 75.59860599198842], [-4.383544921875, 75.59587329063447]]];
$result_union = [[[-4.393979238329456,75.30331101068566],[-4.413142181792476,74.7660113749556],[-4.8779296875,74.76718570583334],[-4.866943359375,75.30331101068566]],
    [[-2.65869140625,75.59860599198842],[-2.6806640625,74.36667478672423],[-4.427490234375,74.36371391783985],[-4.4244826451389665,74.44804212159751],[-6.74560546875,74.44346576284508],[-6.701660156249999,75.52464464250062],[-4.386085311755,75.52464464250062],[-4.383544921875,75.59587329063447]]];
$result_intersect = [[[-3.8452148437499996,75.52464464250062], [-3.8452148437499996,75.30331101068566], [-4.393979238329456,75.30331101068566], [-4.386085311755,75.52464464250062]],
    [[-3.7353515625,74.76429887097666], [-3.75732421875,74.44935750063425], [-4.4244826451389665,74.44804212159751], [-4.413142181792476,74.7660113749556]]];
$pa = Polygon::create()->fillFromArray($region_a);
$pb = Polygon::create()->fillFromArray($region_b);
$result = union($pa,$pb)->getArray();
print json_encode($result) . PHP_EOL;
if ( arrays_are_equal($result,$result_union) ) {
    print "OK 5" . PHP_EOL;
}
$result = intersect($pa,$pb)->getArray();
print json_encode($result) . PHP_EOL;
if ( arrays_are_equal($result,$result_intersect) ) {
    print "OK 6" . PHP_EOL;
}

exit(0);

