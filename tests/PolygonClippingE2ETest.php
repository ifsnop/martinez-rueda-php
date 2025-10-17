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

    private static function runOp(array $A_mp, array $B_mp, string $op): array {
        // Tu port ifsnop/MartinezRueda: Polygon::create()->fillFromArray + funciones globales
        $pa = MR\Polygon::create()->fillFromArray($A_mp);
        $pb = MR\Polygon::create()->fillFromArray($B_mp);
        switch ($op) {
            case 'union':        return MR\Algorithm::union($pa,$pb)->getArray();
            case 'intersection': return MR\Algorithm::intersect($pa,$pb)->getArray();
            case 'difference':   return MR\Algorithm::difference($pa,$pb)->getArray();
            case 'xor':          return MR\Algorithm::xoring($pa,$pb)->getArray();
        }
        throw new InvalidArgumentException("Operación no soportada: $op");
    }

    // ==========================
    // Descubrimiento de casos
    // ==========================
    public static function provideAllCases(): array {
        $root = self::fixturesRoot();
        $ops  = self::opsFilter();
        $max  = 36; //self::maxCases();

        $datasets = [];
        $dirs = @scandir($root);
        if ($dirs === false) {
            self::markTestSkipped("No se pudo listar $root");
        }

        foreach ($dirs as $d) {

	    if ( false === strpos($d, "issue-60-8") )
		continue;

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

    private static function runOpMulti(array $geomList, string $op): array
    {
	if (empty($geomList)) return [];

	// Empezamos con la primera geometría como acumulador
	$acc = $geomList[0];
	// print json_encode($acc) . PHP_EOL;
	// Para 'difference' restamos todas las siguientes al sujeto inicial
	// Para union/intersection/xor reducimos secuencialmente
	$n = count($geomList);
	for ($i = 1; $i < $n; $i++) {
	    // print json_encode($geomList[$i]) . PHP_EOL;
	    $acc = self::runOp($acc, $geomList[$i], $op);
	    // (Opcional) micro‑optimizaciones:
	    // if ($op === 'intersection' && empty($acc)) break;
	}
	return $acc;
    }

    /**
     * @dataProvider provideAllCases
     */
    public function testEndToEnd(string $label, string $op, string $argsPath, string $expectedPath): void {

	print "label: $label" . PHP_EOL;
	print "op: $op" . PHP_EOL;
	print "argsPath: $argsPath" . PHP_EOL;
	print "expectedPath: $expectedPath" . PHP_EOL;
        $argsGJ = self::readGeoJSON($argsPath);
        $geoms  = self::geomsFromGeoJSON($argsGJ);
	print "count geoms: " . count($geoms) . PHP_EOL;
        if (count($geoms) < 2) {
            $this->markTestSkipped("[$label] args sin geometrías: $argsPath");
        }
	foreach($geoms as $k => $g) print "$k: " . json_encode($g) . PHP_EOL;

	$got = self::runOpMulti($geoms, $op);
	$got_normalized = MR\GJTools::ringsToCoordinates($got);

        $expectedGJ = self::readGeoJSON($expectedPath);
        $expGeoms   = self::geomsFromGeoJSON($expectedGJ);
        $exp        = isset($expGeoms[0]) ? $expGeoms[0] : []; // MultiPolygon vacío si corresponde
	$exp_normalized = MR\GJTools::fixGeometries($exp);
	$exp_normalized = MR\GJTools::canonicalizePolygons($exp_normalized, (int)abs(floor(log10(abs(MR\Algorithm::TOLERANCE)))));
        

	//print "got  : " . json_encode($got) . PHP_EOL;
	//print "exp  : " . json_encode($exp) . PHP_EOL . PHP_EOL;
	print "exp_n: " . json_encode($exp_normalized) . PHP_EOL . PHP_EOL;
	print "got_n: " . json_encode($got_normalized) . PHP_EOL;
	//print "got  : " . json_encode($got) . PHP_EOL;
	$diff = [];
	//$ret = self::compareCoordinates($got_normalized, $exp_normalized, $diff);
	//var_dump($ret); exit(1);
	//if ($ret) print "EQUAL" . PHP_EOL; else print "DIFFERENT" . PHP_EOL;
	//exit(1);

        $this->assertTrue(
	    self::compareCoordinates($got_normalized, $exp_normalized, $diff),
            "Diferencia detectada" . PHP_EOL .
            "got (recortado):      " . json_encode($got_normalized) . PHP_EOL .
            "expected (recortado): " . json_encode($exp_normalized) . PHP_EOL .
	    "diff: " . json_encode($diff) . PHP_EOL
        );
    }
}
