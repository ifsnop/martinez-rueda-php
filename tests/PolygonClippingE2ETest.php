<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda as MR;
use Ifsnop\MartinezRueda\Tests\Support\RegionComparator;

include_once('autoloader.php');

final class PolygonClippingE2ETest extends TestCase
{
    /**
     * Casos de polygon-clipping (una sola geometría) cuyo resultado esperado no
     * coincide con el de esta librería por motivos conocidos. Se ejecutan igual
     * (una excepción de la librería sigue siendo un error), pero si el resultado
     * difiere se marcan como "incomplete" en lugar de fallar. Si algún día
     * coinciden, el test pasa normalmente y conviene quitar la entrada.
     */
    private const EVEN_ODD = 'la entrada es un MultiPolygon cuyas partes se solapan; esta librería '
        . 'aplica la regla par-impar (el solape se cancela) y polygon-clipping las une. '
        . 'El resultado coincide con la interpretación par-impar de la entrada.';
    private const SUB_TOLERANCE = 'polígono más estrecho que Algorithm::TOLERANCE (1e-12, por debajo de la '
        . 'precisión de float en esas coordenadas): la librería lo descarta como degenerado y polygon-clipping '
        . 'lo conserva. Limitación aceptada (ISSUES.md, "Known limitations").';
    private const KNOWN_DEVIATIONS = [
        'issue-61::union'                         => self::EVEN_ODD,
        'issue-61-2::union'                       => self::EVEN_ODD,
        'issue-62::union'                         => self::EVEN_ODD,
        'issue-62-2::union'                       => self::EVEN_ODD,
        'issue-68::union'                         => self::EVEN_ODD,
        'parallel-lines-with-crossing::union'     => self::EVEN_ODD,
        'split-almost-vertical-segment::union'    => self::EVEN_ODD,
        'union-same-shape-multiple-times::union'  => self::EVEN_ODD,
        'nearly-vertical-segment::union'          => self::SUB_TOLERANCE,
        'thin-vertical-triangle::union'           => self::SUB_TOLERANCE,
        'thin-vertical-triangle-2::union'         => self::SUB_TOLERANCE,
    ];

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


    private static function isDebugEnabled(): bool
    {
        return in_array('--debug', $_SERVER['argv'] ?? []);
    }

    /**
     * ¿Son iguales dos regiones? Usa un comparador independiente de la librería
     * (tests/Support/RegionComparator.php, ISSUES.md #4): fronteras a menos de δ en los
     * dos sentidos y mismo interior en puntos de muestra. Antes se usaba el xor de la
     * propia librería (un bug en xor escondía bugs en las demás operaciones) y una
     * comparación de coordenadas con tolerancia 0.1 (11 km en lon/lat).
     *
     * @param array       $coordsA lista de anillos o MultiPolygon
     * @param array       $coordsB lista de anillos o MultiPolygon
     * @param string|null $diff    (salida) motivo de la primera diferencia encontrada
     */
    public static function comparePolygons(array $coordsA, array $coordsB, ?string &$diff = null): bool
    {
        return RegionComparator::equal($coordsA, $coordsB, null, $diff);
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
	//if ( false === strpos($d, "almost-colinear-segments-but-not") )
	//    continue;
	//if ( false === strpos($d, "continents_europe") )
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
	    //print PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . json_encode($acc) . PHP_EOL;
	    //print PHP_EOL . json_encode($geomList[$i]) . PHP_EOL;
	    //print "llamando a $op" . PHP_EOL;
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

        if (count($geoms) === 0) {
            $this->markTestSkipped("[$label] args sin geometrías: $argsPath");
        }

	// Una sola geometría: se opera contra un polígono vacío, lo que equivale a
	// "limpiar" la geometría (resolver auto-intersecciones, anillos solapados,
	// segmentos degenerados...). Así se ejecutan los casos de polygon-clipping
	// que sólo tienen un argumento (union de una geometría consigo misma).
	if (count($geoms) === 1) {
	    $geoms[] = [];
	}

	$got = self::runOpMulti($geoms, $op);

	if ( self::isDebugEnabled() )
	    print "got:\t" . json_encode($got) . PHP_EOL;

	$knownReason = self::KNOWN_DEVIATIONS[$label] ?? null;

	// El esperado se lee directamente del GeoJSON (sin GJTools, que es parte de la
	// librería probada) y se compara con la salida en bruto de la librería.
	$expected = self::geomsFromGeoJSON(self::readGeoJSON($expectedPath));
	if ( self::isDebugEnabled() )
	    print "exp:\t" . json_encode($expected) . PHP_EOL . PHP_EOL;

	$diff = null;
	$equal = self::comparePolygons($got, $expected, $diff);

	if (!$equal && $knownReason !== null) {
	    $this->markTestIncomplete("[$label] desviación conocida: $knownReason" . PHP_EOL .
		"diff: " . $diff . PHP_EOL .
		"got: " . json_encode($got) . PHP_EOL .
		"expected: " . json_encode($expected));
	}

        $this->assertTrue(
	    $equal,
            "Diferencia detectada" . PHP_EOL .
	    "input" . PHP_EOL . $input .PHP_EOL .
	    "operation" . PHP_EOL . "\t" . $op . PHP_EOL .
            "got" . PHP_EOL . "\t" . json_encode($got) . PHP_EOL .
            "expected" . PHP_EOL . "\t" . json_encode($expected) . PHP_EOL .
	    "diff" . PHP_EOL . "\t" . $diff . PHP_EOL . PHP_EOL
        );
    }
}
