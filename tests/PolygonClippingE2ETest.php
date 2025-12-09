<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda as MR;

include_once('autoloader.php');

final class PolygonClippingE2ETest extends TestCase
{
    // ==========================
    // Parámetros vía entorno
    // ==========================
    private static function fixturesRoot(): string {

	// https://github.com/mfogel/polygon-clipping/

        // Directorio donde están las carpetas tipo: test/end-to-end/<case>/
        // Por defecto: tests/fixtures/polygon-clipping/test/end-to-end
        $path = getenv('PC_FIXTURES_DIR');
        if (!$path || !is_dir($path)) {
            $path = __DIR__ . '/fixtures/polygon-clipping/test/end-to-end';
        }
        if (!is_dir($path)) {
            self::markTestSkipped("No existe el directorio de fixtures: $path. Define PC_FIXTURES_DIR.");
        }
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private static function opsFilter(): array {
        $all = ['union','intersection','difference','xor'];
        $env = getenv('PC_OPS');
        if ($env === false || strtolower(trim($env)) === 'all' || trim($env) === '') return $all;
        $chosen = array_map('trim', explode(',', strtolower($env)));
        $out = array_values(array_intersect($all, $chosen));
        return $out ? $out : $all;
    }

    private static function maxCases(): int {
        $m = getenv('PC_MAX');
        return $m !== false ? max(1, (int)$m) : PHP_INT_MAX;
    }

    private static function almostEqual(float $a, float $b): bool {
	// print $a . "-" . $b. "=" . ($a-$b) . PHP_EOL;
        return abs($a-$b) <= 0.1; //MR\Algorithm::TOLERANCE;
    }

    private static function isDebugEnabled(): bool
    {
        return in_array('--debug', $_SERVER['argv'] ?? []);
    }

    /**
    * Compara dos arrays de coordenadas estilo MultiPolygon haciendo un XOR,
    * si los dos arrays son iguales, el resultado debería ser conjunto vacio.
    * No importa si los polígonos están representados de forma distinta (por
    * ejemplo un polígono con hueco o dos polígonos que dejan el hueco entre
    * ellos).
    *
    * Se usa como alternativa si la comparación normal no funciona, como última
    * opción.
    *
    * @param array      $coordsA
    * @param array      $coordsB
    * @param array|null $diff            (salida) detalles del primer desacuerdo encontrado.
    * @return bool
    */
    public static function comparePolygons(
	array $coordsA,
	array $coordsB,
	?array &$diff = null
    ): bool {
	$diff = null;

	$res = self::runOp($coordsA, $coordsB, "xor");
	$diff = MR\GJTools::geojsonToArray($res);
	return $diff == [];
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
	    //print "ABORT1" . PHP_EOL;
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
		// print "ABORT2" . PHP_EOL;
                return false;
            }

            for ($r = 0; $r < $ra; $r++) {
                $ringA = $polyA[$r];
                $ringB = $polyB[$r];

                $pa = count($ringA);
                $pb = count($ringB);
                if ($pa !== $pb) {
                    $diff = ['where' => 'point_count', 'polygon' => $p, 'ring' => $r, 'a' => $pa, 'b' => $pb];
		    // print "ABORT3" . PHP_EOL;
                    return false;
                }

                // Punto a punto
                for ($k = 0; $k < $pa; $k++) {
                    $ax = (float)$ringA[$k][0]; $ay = (float)$ringA[$k][1];
                    $bx = (float)$ringB[$k][0]; $by = (float)$ringB[$k][1];

                    if (!self::almostEqual($ax, $bx)) {
                        $diff = [
                            'where'   => 'point_x',
                            'polygon' => $p, 'ring' => $r, 'point' => $k,
                            'a' => $ax, 'b' => $bx
                        ];
			// print "ABORT4" . PHP_EOL;
                        return false;
                    }
                    if (!self::almostEqual($ay, $by)) {
                        $diff = [
                            'where'   => 'point_y',
                            'polygon' => $p, 'ring' => $r, 'point' => $k,
                            'a' => $ay, 'b' => $by
                        ];
			// print "ABORT5" . PHP_EOL;
                        return false;
                    }
                }
            }
        }

        return true;
    }

    // ==========================
    // Carga GeoJSON local
    // ==========================
    private static function readGeoJSON(string $path): array {
        $txt = file_get_contents($path);
        if ($txt === false) throw new RuntimeException("No se pudo leer: $path");
        $data = json_decode($txt, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("JSON inválido en $path: " . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Convierte un GeoJSON (Feature/FeatureCollection/Polygon/MultiPolygon) a
     * lista de geoms, donde cada geom es un multipolígono:
     *   geom := [ polygon1, polygon2, ... ]
     *   polygon := [ ringExterior, hole1, ... ]
     *   ring := [ [x,y], ... ]
     */
    private static function geomsFromGeoJSON(array $gj): array {
        $out = [];
        $push = function(array $geom, $feature = null) use (&$out) {
            $t = isset($geom['type']) ? $geom['type'] : null;
            if ($t === 'Polygon') {
		if ( $feature === 'FeatureCollection' ) {
		    $out[] = $geom['coordinates'];
		} else {
		    $out[] = [$geom['coordinates']];
		}
            } elseif ($t === 'MultiPolygon') {
                $out[] = $geom['coordinates'];
            }
        };
        $t = isset($gj['type']) ? $gj['type'] : null;
        if ($t === 'Feature') {
            $push($gj['geometry']);
        } elseif ($t === 'FeatureCollection') {
            foreach (($gj['features'] ?? []) as $f) $push($f['geometry'], $t); // Si es FeatureCollection, los polygon es como si fueran multipolygon
        } elseif ($t === 'GeometryCollection') {
            foreach (($gj['geometries'] ?? []) as $g) $push($g, $t);
        } elseif ($t === 'Polygon' || $t === 'MultiPolygon') {
            $push($gj);
        } else {
            if (is_array($gj) && array_values($gj) === $gj) { // lista
                foreach ($gj as $g) if (is_array($g)) $push($g);
            }
        }
        return $out;
    }

    // ==========================
    // Descubrimiento de casos
    // ==========================
    public static function provideAllCases(): array {
        $root = self::fixturesRoot();
        $ops  = self::opsFilter();
        $max  = 142; // 42; //self::maxCases();

        $datasets = [];
        $dirs = @scandir($root);
        if ($dirs === false) {
            self::markTestSkipped("No se pudo listar $root");
        }

        foreach ($dirs as $d) {
	//if ( false === strpos($d, "poly-with-hole-and-square") )
	//    continue;
	//    if ( false === strpos($d, "multipoly-with-hole-and-square") )
	//	continue;
	//    if ( false === strpos($d, "no-bbox-overlap") )
	//	continue;
	//    if ( false === strpos($d, "multipoly-and-square") )
	//	continue;
	//    if ( false === strpos($d, "issue-38") )
	// f6	continue;
	//    if ( false === strpos($d, "issue-60-8") )
	//	continue;

	//    if ( false === strpos($d, "dont-consume-prev-segment-3") )
	//	continue;

            if ($d === '.' || $d === '..') continue;
            $caseDir = $root . DIRECTORY_SEPARATOR . $d;
            if (!is_dir($caseDir)) continue;

            $args = $caseDir . DIRECTORY_SEPARATOR . 'args.geojson';
            if (!is_file($args)) continue;

            // busca expected por operación
            foreach ($ops as $op) {
                $exp = $caseDir . DIRECTORY_SEPARATOR . $op . '.geojson';
                if (!is_file($exp)) continue;

                $label = $d . '::' . $op;
                $datasets[$label] = [$label, $op, $args, $exp];
                if (count($datasets) >= $max) break 2;
            }
        }

        if (empty($datasets)) {
            self::markTestSkipped("No se encontraron casos en $root (¿PC_FIXTURES_DIR correcto?).");
        }
        return $datasets;
    }

    private static function runOp(array $A_mp, array $B_mp, string $op): array {
        // Tu port ifsnop/MartinezRueda: Polygon::create()->fillFromArray + funciones globales
        $pa = MR\Polygon::create()->fillFromArray($A_mp);
        $pb = MR\Polygon::create()->fillFromArray($B_mp);

	if ( self::isDebugEnabled() ) {
	    print "pa:\t" . json_encode($pa->getArray()) . PHP_EOL;
	    print "pb:\t" . json_encode($pb->getArray()) . PHP_EOL;
	}

        switch ($op) {
            case 'union':        return MR\Algorithm::union($pa,$pb)->getArray();
            case 'intersection': return MR\Algorithm::intersect($pa,$pb)->getArray();
            case 'difference':   return MR\Algorithm::difference($pa,$pb)->getArray();
            case 'xor':          return MR\Algorithm::xoring($pa,$pb)->getArray();
        }
        throw new InvalidArgumentException("Operación no soportada: $op");
    }

    private static function runOpMulti(array $geomList, string $op): array
    {
	if (empty($geomList)) return [];
	if ( self::isDebugEnabled() )
	    print "runOpMulti" . PHP_EOL .
		"geomLst:" . json_encode($geomList) . PHP_EOL;

	if ( self::isDebugEnabled() ) {
	    foreach($geomList as $k => $geom) {
		print "$k:\t" . json_encode($geom) . PHP_EOL;
	    }
	}

	// $geomList = $geomList[0];
	// Empezamos con la primera geometría como acumulador
	$acc = $geomList[0];
	if ( self::isDebugEnabled() )
	    print "acc 0:\t" . json_encode($acc) . PHP_EOL;
	// Para 'difference' restamos todas las siguientes al sujeto inicial
	// Para union/intersection/xor reducimos secuencialmente
	$n = count($geomList);
	// print "runOpMulti 0: " . json_encode($acc) . PHP_EOL;
	for ($i = 1; $i < $n; $i++) {
	    // print "runOpMulti $i: " . json_encode($geomList[$i]) . PHP_EOL;
	    if ( self::isDebugEnabled() )
		print $op . ":\t" . json_encode($geomList[$i]) . PHP_EOL;
	    $acc = self::runOp($acc, $geomList[$i], $op);
	    if ( self::isDebugEnabled() )
		print "acc $i\t" . json_encode($acc) . PHP_EOL;
	    // (Opcional) micro‑optimizaciones:
	    if ($op === 'intersection' && empty($acc)) break;
	}
	if ( self::isDebugEnabled() )
	    print "total:\t" . json_encode($acc) . PHP_EOL . PHP_EOL;
	return $acc;
    }

    /**
     * @dataProvider provideAllCases
     */
    public function testEndToEnd(string $label, string $op, string $argsPath, string $expectedPath): void {

	if ( self::isDebugEnabled() ) {
	    print "label: $label" . PHP_EOL;
	    print "op: $op" . PHP_EOL;
	    print "argsPath: $argsPath" . PHP_EOL;
	    print "expectedPath: $expectedPath" . PHP_EOL;
	}

        $argsGJ = self::readGeoJSON($argsPath);
        $geoms  = self::geomsFromGeoJSON($argsGJ);

	$input = ""; foreach($geoms as $k => $g) $input .= "op $k:\t" . json_encode($g) . PHP_EOL;
//	$input_n = ""; foreach($geoms as $k => $g) $input .= "\top$k => " . json_encode(MR\GJTools::geojsonToPolygons($g)) . PHP_EOL;
//	$geoms_n = []; foreach($geoms as $k => $g) { $geoms_n[] = MR\GJTools::geojsonToPolygons($g); }

	if ( self::isDebugEnabled() ) {
	    print "testEndToEnd" . PHP_EOL .
		$input . PHP_EOL;
	}

        if (count($geoms) < 2) {
            $this->markTestSkipped("[$label] args sin geometrías: $argsPath");
        }

	$got = self::runOpMulti($geoms, $op);

	if ( self::isDebugEnabled() )
	    print "got:\t" . json_encode($got) . PHP_EOL;

	$got_normalized = MR\GJTools::geojsonToArray($got);
	if ( self::isDebugEnabled() )
	    print "got_n:\t" . json_encode($got_normalized) . PHP_EOL;

	$exp_normalized = MR\GJTools::geojsonToArray($expectedPath);
	if ( self::isDebugEnabled() )
	    print "exp:\t" . json_encode($exp_normalized) . PHP_EOL . PHP_EOL;

	$diff = [];

        $this->assertTrue(
	    self::compareCoordinates($got_normalized, $exp_normalized, $diff) ||
	    self::comparePolygons($got_normalized, $exp_normalized, $diff),
            "Diferencia detectada" . PHP_EOL .
	    "input" . PHP_EOL . $input .PHP_EOL .
            "got" . PHP_EOL . "\t" . json_encode($got_normalized) . PHP_EOL .
            "expected" . PHP_EOL . "\t" . json_encode($exp_normalized) . PHP_EOL .
	    "diff" . PHP_EOL . "\t" . json_encode($diff) . PHP_EOL . PHP_EOL
        );
    }
}
