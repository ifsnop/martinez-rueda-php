<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\SkippedTest;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\GJTools;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\GeometryOracle;

include_once('autoloader.php');
require_once __DIR__ . '/../PolygonClippingE2ETest.php';

/**
 * Seguimiento de ISSUES.md: cada test comprueba si un punto de la lista está
 * arreglado. PASA = arreglado, FALLA = sigue abierto (el mensaje explica qué falta).
 *
 * No forma parte de la suite por defecto (phpunit.xml: defaultTestSuite), así que
 * no rompe la CI mientras queden puntos abiertos. Para ver el progreso:
 *
 *     vendor/bin/phpunit --testsuite issues --testdox --colors=never
 *
 * (Con colores, PHPUnit pinta los mensajes de fallo en blanco sobre rojo, que en
 * algunos terminales no se lee bien; por eso se recomienda --colors=never.)
 *
 * Los nombres empiezan por el identificador de ISSUES.md (F1..F4 = ya arreglados,
 * sirven de regresión; Issue01..Issue10 = abiertos). Cuando un punto se arregla
 * con una solución distinta a la propuesta, revisar que su test sigue midiendo
 * lo que importa. Para los puntos que requieren una decisión (3, 7) el test no
 * presupone la decisión: comprueba que, sea cual sea, se aplica de forma coherente.
 */
final class IssuesProgressTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private static function poly(array $rings): Polygon
    {
        return Polygon::create()->fillFromArray($rings);
    }

    private static function empty(): Polygon
    {
        return Polygon::create()->fillFromArray([]);
    }

    private static function area(array $rings): float
    {
        $s = 0.0;
        foreach ($rings as $r) {
            $a = 0.0;
            $n = count($r);
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                $a += $r[$j][0] * $r[$i][1] - $r[$i][0] * $r[$j][1];
            }
            $s += abs($a) / 2;
        }
        return $s;
    }

    /**
     * assertTrue con un mensaje propio y corto. Los asserts de PHPUnit que
     * comparan cadenas o arrays vuelcan el contenido completo (README, ficheros,
     * coordenadas), y con colores eso es un bloque rojo ilegible.
     */
    private function ok(bool $condition, string $message): void
    {
        $this->assertTrue($condition, $message);
    }

    /** Resume la lista de discrepancias del oráculo en una línea. */
    private function assertNoProblems(array $problems, string $what): void
    {
        $first = $problems ? strtok($problems[0], PHP_EOL) : '';
        $this->ok($problems === [], count($problems) . " operaciones incorrectas en: $what (primera: $first)");
    }

    private static function fileContains(string $relPath, string $regex): bool
    {
        $file = self::ROOT . '/' . $relPath;
        return is_file($file) && preg_match($regex, (string)file_get_contents($file)) === 1;
    }

    // ── Ya arreglados (regresión) ────────────────────────────────────────────

    /** F1: anillos adyacentes con bounding boxes disjuntas no pierden regiones. */
    public function testF1_adjacentRingsWithDisjointBoundingBoxesKeepAllRegions(): void
    {
        $two = [[[0, 0], [1, 0], [1, 1], [0, 1]], [[1, 0], [2, 0], [2, 1], [1, 1]]];
        $far = [[[10, 10], [11, 10], [11, 11], [10, 11]]];
        $this->assertNoProblems(GeometryOracle::check($two, $far), 'dos cuadrados adyacentes frente a uno lejano');
        $this->assertNoProblems(GeometryOracle::fuzz('grid', 1, 20, 0.0, 1.0), 'generador grid');
    }

    /** F2: todas las rutas de phpstan.neon existen (PHPStan analiza algo). */
    public function testF2_phpstanConfigPointsToExistingPaths(): void
    {
        $neon = file_get_contents(self::ROOT . '/phpstan.neon');
        $this->ok(preg_match('/paths:\s*\n((?:\s*-\s*\S+\s*\n)+)/', $neon, $m) === 1, 'phpstan.neon no tiene una lista "paths:"');
        preg_match_all('/-\s*(\S+)/', $m[1], $paths);
        foreach ($paths[1] as $p) {
            $this->ok(file_exists(self::ROOT . '/' . trim($p, "'\"")), "phpstan.neon apunta a una ruta inexistente: $p");
        }
    }

    /** F3: los fixtures de una sola geometría se ejecutan (no se saltan). */
    public function testF3_singleGeometryFixturesRun(): void
    {
        $case = 'issue-85';
        $dir = self::ROOT . "/tests/end-to-end/$case";
        $e2e = new PolygonClippingE2ETest('testEndToEnd');
        try {
            $e2e->testEndToEnd("$case::union", 'union', "$dir/args.geojson", "$dir/union.geojson");
        } catch (SkippedTest $e) {
            $this->fail("El fixture de una sola geometría $case se salta: " . $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }

    /** F4: GJTools elimina el punto colineal intermedio, no la esquina anterior. */
    public function testF4_gjtoolsRemovesTheCollinearVertexNotTheCorner(): void
    {
        $got = GJTools::removeColinearPointsFromPolygon([[0, 0], [1, 3], [2, 0], [3, 0], [4, 0], [4, 4], [0, 4]]);
        $this->ok(
            $got === [[0, 0], [1, 3], [2, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
            'removeColinearPointsFromPolygon devuelve ' . json_encode($got) . ' (debería quitar (3,0) y conservar (2,0))'
        );
    }

    // ── 1. Tolerancias dependientes de la escala ─────────────────────────────

    /** 1: dos triángulos de ~15 m cerca de Madrid; la unión salía vacía. */
    public function testIssue01_smallLonLatTrianglesAreClippedCorrectly(): void
    {
        $A = [[[-3.699848772543026, 40.40004447766722], [-3.699861673144788, 40.40005385016095], [-3.6998228041022325, 40.40011705732156]]];
        $B = [[[-3.699906427846182, 40.40018978313583], [-3.69998721385193, 40.400144433425936], [-3.6998277419565775, 40.39995413437151]]];
        $this->ok(Algorithm::union(self::poly($A), self::poly($B))->getArray() !== [], 'la unión de los dos triángulos sale vacía');
        $this->assertNoProblems(GeometryOracle::check($A, $B), 'triángulos de ~15 m');
    }

    /** 1: el triángulo de 1 cm del fixture very-small-polygon se conserva. */
    public function testIssue01_oneCentimetreTriangleIsKept(): void
    {
        $tri = [[[-122.3793225342952, 37.708405012714316], [-122.3793225, 37.708405], [-122.37932240498668, 37.70840501108731]]];
        $out = Algorithm::union(self::poly($tri), self::empty())->getArray();
        $this->ok($out !== [], 'el triángulo de 1 cm desaparece (union con vacío devuelve [])');
        $this->ok(abs(self::area($tri) - self::area($out)) <= 1e-17,
            sprintf('el área cambia: %.3e en vez de %.3e', self::area($out), self::area($tri)));
    }

    /** 1: polígonos aleatorios de ~1 m (1e-5°) cerca de Madrid. */
    public function testIssue01_randomMetreScaleFeatures(): void
    {
        foreach (['star', 'tiles'] as $gen) {
            foreach ([1, 3] as $seed) {
                $this->assertNoProblems(
                    GeometryOracle::fuzz($gen, $seed, 20, [-3.7, 40.4], 1e-5),
                    "generador $gen, escala 1e-5°, semilla $seed"
                );
            }
        }
    }

    // ── 2. Tolerancia de ajuste a extremos relativa a la longitud ────────────

    /** 2: polígonos de ~10 km con 7 decimales; la unión fallaba en ~1/3 del área. */
    public function testIssue02_tenKilometreUnionWithSevenDecimals(): void
    {
        $A = [[[-4.1161297, 40.4826806], [-3.8667245, 40.6494051], [-3.9778742, 40.8156752], [-4.2272794, 40.6489507]],
              [[-3.6724398, 40.5387099], [-3.4230347, 40.7054344], [-3.5341843, 40.8717045], [-3.7835895, 40.70498]]];
        $B = [[[-3.8111497, 40.5662701], [-3.7280146, 40.6218449], [-3.8947392, 40.8712501], [-3.9778742, 40.8156752]],
              [[-4.088115, 40.2608357], [-4.00498, 40.3164105], [-4.0605548, 40.3995456], [-4.1436899, 40.3439707]],
              [[-3.783135, 40.3444252], [-3.4505949, 40.5667245], [-3.6173194, 40.8161297], [-3.9498596, 40.5938303]]];
        $this->assertNoProblems(GeometryOracle::check($A, $B, 1000), 'caso de ISSUES.md #2');
    }

    /** 2: rejillas giradas redondeadas a 7 decimales (aristas casi coincidentes). */
    public function testIssue02_rotatedGridsRoundedToSevenDecimals(): void
    {
        foreach ([1, 2, 3] as $seed) {
            $this->assertNoProblems(
                GeometryOracle::fuzz('rotgrid', $seed, 20, [-3.7, 40.4], 0.02, 7),
                "rotgrid, escala 0.02°, 7 decimales, semilla $seed"
            );
        }
    }

    // ── 3. Regla de relleno para partes solapadas / anillos duplicados ───────

    /**
     * Pertenencia del punto (x,y) al polígono P según cada camino de la librería.
     * Si la regla es coherente, todos los valores coinciden.
     */
    private static function membershipByPath(array $P, float $x, float $y): array
    {
        $big = [[[-100, -100], [100, -100], [100, 100], [-100, 100]]];
        $far = [[[1000, 1000], [1001, 1000], [1001, 1001]]];
        $in = fn(Polygon $r) => GeometryOracle::inside($r->getArray(), $x, $y);
        return [
            'union(P, vacío)'          => $in(Algorithm::union(self::poly($P), self::empty())),
            'difference(P, lejano)'    => $in(Algorithm::difference(self::poly($P), self::poly($far))),
            'intersect(P, grande)'     => $in(Algorithm::intersect(self::poly($P), self::poly($big))),
            'NOT difference(grande, P)' => !$in(Algorithm::difference(self::poly($big), self::poly($P))),
            'NOT xor(P, grande)'       => !$in(Algorithm::xoring(self::poly($P), self::poly($big))),
        ];
    }

    /**
     * 3: la regla de relleno elegida está documentada en el README. Hoy la
     * librería aplica par-impar de forma coherente (ver los dos tests
     * siguientes, que ya pasan y sirven de regresión); falta decidir si esa es
     * la regla deseada frente a la unión de partes de polygon-clipping, y
     * documentarla (y, si se elige unión de partes, cambiar el código).
     */
    public function testIssue03_fillRuleIsDocumented(): void
    {
        $this->ok(
            self::fileContains('README.md', '/par-impar|even-odd|even\/odd|nonzero|non-zero|uni[oó]n de (las )?partes/i'),
            'README no documenta la regla de relleno para partes solapadas o anillos repetidos'
        );
    }

    /** 3 (regresión): un anillo repetido se interpreta igual en todas las operaciones. */
    public function testIssue03_duplicatedRingIsTreatedConsistently(): void
    {
        $tri = [[0, 0], [4, 0], [2, 3]];
        $m = self::membershipByPath([$tri, $tri], 2.0, 1.0);
        $this->ok(count(array_unique($m)) === 1, 'Interpretación incoherente del anillo duplicado: ' . json_encode($m)
            . '. Decidir la regla (par-impar: se cancela; unión de partes: cuenta una vez) y aplicarla en todos los caminos.');
    }

    /** 3 (regresión): la zona de solape de dos partes se interpreta igual en todas las operaciones. */
    public function testIssue03_overlappingPartsAreTreatedConsistently(): void
    {
        $P = [[[0, 0], [3, 0], [3, 3], [0, 3]], [[1, 1], [4, 1], [4, 4], [1, 4]]];
        $m = self::membershipByPath($P, 2.0, 2.0);
        $this->ok(count(array_unique($m)) === 1, 'Interpretación incoherente del solape: ' . json_encode($m));
    }

    // ── 4. La comparación E2E usa la propia librería ─────────────────────────

    /**
     * 4: comparePolygons() no usa la librería que se está probando. (El test
     * siguiente comprueba el síntoma, pero arreglar el punto 1 también lo hace
     * pasar sin arreglar el arnés; por eso se mira el código del método.)
     */
    public function testIssue04_e2eComparisonDoesNotCallTheLibrary(): void
    {
        $m = new ReflectionMethod(PolygonClippingE2ETest::class, 'comparePolygons');
        $lines = file($m->getFileName());
        $body = implode('', array_slice($lines, $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
        $body = preg_replace('#//[^\n]*|/\*.*?\*/#s', '', $body); // sin comentarios
        $this->ok(
            preg_match('/runOp|Algorithm::|MR\\\\Algorithm/', $body) !== 1,
            'comparePolygons() usa la propia librería (runOp/Algorithm) para decidir si dos resultados son iguales'
        );
    }

    /** 4: un resultado vacío no puede considerarse igual a un polígono no vacío. */
    public function testIssue04_e2eComparisonRejectsEmptyVersusTinyPolygon(): void
    {
        $tri = [[-122.3793225342952, 37.708405012714316], [-122.3793225, 37.708405],
                [-122.37932240498668, 37.70840501108731], [-122.3793225342952, 37.708405012714316]];
        $this->ok(
            !PolygonClippingE2ETest::comparePolygons([], [[$tri]]),
            'comparePolygons() da por iguales [] y un triángulo de 1 cm (usa xor de la librería para comparar)'
        );
    }

    // ── 5. Polygon::create() sin inicializar ─────────────────────────────────

    /** 5: un Polygon::create() sin rellenar se puede usar como operando vacío. */
    public function testIssue05_unfilledPolygonCanBeUsedAsOperand(): void
    {
        $sq = [[[0, 0], [1, 0], [1, 1], [0, 1]]];
        try {
            $r = Algorithm::union(Polygon::create(), self::poly($sq));
        } catch (\Error $e) {
            $this->fail('Polygon::create() como operando lanza: ' . $e->getMessage());
        }
        $this->ok(GeometryOracle::inside($r->getArray(), 0.5, 0.5), 'union(vacío, cuadrado) no contiene el cuadrado');
    }

    // ── 6. *Many() con un solo polígono ──────────────────────────────────────

    /** 6: unionMany([P]) limpia P igual que union(P, vacío). */
    public function testIssue06_manyWithOnePolygonReturnsCleanedResult(): void
    {
        $P = [[[0, 0], [3, 0], [3, 3], [0, 3]], [[1, 1], [4, 1], [4, 4], [1, 4]]];
        $expected = Algorithm::union(self::poly($P), self::empty())->getArray();
        foreach (['unionMany', 'intersectMany', 'xorMany', 'differenceMany'] as $fn) {
            $this->ok($expected === Algorithm::$fn([self::poly($P)])->getArray(), "$fn([P]) devuelve P sin limpiar (distinto de union(P, vacío))");
        }
    }

    // ── 7. GJTools rechaza toda la entrada por un anillo degenerado ──────────

    /**
     * 7: o bien se ignora el anillo degenerado, o bien se mantiene la excepción y
     * el test GJToolsTest::testDegenerateRingsAndInvalidCoords deja de decir que
     * "debe ignorarse".
     */
    public function testIssue07_degenerateRingContractIsConsistent(): void
    {
        $gj = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [5, 0], [5, 5], [0, 5], [0, 0]],
            [[1, 1], [2, 2], [1, 1]],
        ]];
        try {
            $out = GJTools::geojsonToArray($gj);
            $this->ok(count($out) === 1, 'se esperaba el polígono exterior sin el hueco degenerado');
        } catch (\InvalidArgumentException $e) {
            $this->ok(
                !self::fileContains('tests/GJToolsTest.php', '/debe ignorarse/'),
                'GJTools lanza excepción por un hueco degenerado, pero GJToolsTest dice que debe ignorarse. Decidir el contrato.'
            );
        }
    }

    // ── 8. pointKey y coordenadas proyectadas ────────────────────────────────

    /** 8: puntos a 18.447 km de distancia no comparten clave (desbordamiento int64). */
    public function testIssue08_pointKeyDoesNotOverflow(): void
    {
        $key = new ReflectionMethod(Algorithm::class, 'pointKey');
        $key->setAccessible(true);
        $a = new Point(1e6, 0.0);
        $b = new Point(1e6 + 18446744.073709552, 0.0);
        $this->ok($key->invoke(null, $a) !== $key->invoke(null, $b),
            'pointKey() desborda: (1e6, 0) y (1e6 + 18446744.07, 0) tienen la misma clave ' . $key->invoke(null, $a));
    }

    /** 8: coordenadas proyectadas tipo UTM (metros, ~4.5e6) con aristas casi coincidentes. */
    public function testIssue08_projectedCoordinates(): void
    {
        foreach ([1, 2, 3] as $seed) {
            $this->assertNoProblems(
                GeometryOracle::fuzz('rotgrid', $seed, 20, [450000.0, 4500000.0], 1000.0),
                "rotgrid en coordenadas UTM, semilla $seed"
            );
        }
    }

    // ── 9. PHPStan ───────────────────────────────────────────────────────────

    /** 9: PHPStan (configuración del repositorio) no informa de ningún error. */
    public function testIssue09_phpstanReportsNoErrors(): void
    {
        $bin = self::ROOT . '/vendor/bin/phpstan';
        if (!is_file($bin)) {
            $this->markTestSkipped('PHPStan no está instalado (composer install).');
        }
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin)
            . ' analyse --no-progress --error-format=json --memory-limit=1G -c '
            . escapeshellarg(self::ROOT . '/phpstan.neon') . ' 2>/dev/null';
        $cwd = getcwd();
        chdir(self::ROOT);
        $json = shell_exec($cmd);
        chdir($cwd);
        $res = json_decode((string)$json, true);
        $this->ok(is_array($res), 'no se pudo ejecutar PHPStan: ' . substr((string)$json, 0, 200));
        $errors = [];
        foreach ($res['files'] ?? [] as $file => $f) {
            foreach ($f['messages'] as $msg) {
                $errors[] = basename($file) . ':' . $msg['line'] . ' ' . $msg['message'];
            }
        }
        $errors = array_merge($errors, $res['errors'] ?? []);
        $sample = array_map(fn($e) => mb_strimwidth($e, 0, 110, '…'), array_slice($errors, 0, 3));
        $this->ok($errors === [], count($errors) . ' errores de PHPStan, p.ej.:' . PHP_EOL . implode(PHP_EOL, $sample));
    }

    // ── 10. Repositorio y CI ─────────────────────────────────────────────────

    public function testIssue10_gitignoreCoversVendorAndPhpunitCache(): void
    {
        $tracked = trim((string)shell_exec('git -C ' . escapeshellarg(self::ROOT) . ' ls-files .gitignore'));
        $this->ok($tracked !== '', '.gitignore no está en el repositorio (git add .gitignore)');
        $this->ok(self::fileContains('.gitignore', '#^/?vendor/?$#m'), '.gitignore no existe o no excluye vendor/');
        $this->ok(self::fileContains('.gitignore', '/\.phpunit\.result\.cache/'), '.gitignore no excluye .phpunit.result.cache');
    }

    public function testIssue10_readmeHasNoBrokenLocalLinks(): void
    {
        $readme = file_get_contents(self::ROOT . '/README.md');
        preg_match_all('#\]\(\./([^)\#]+)\)#', $readme, $m);
        $missing = array_values(array_filter($m[1], fn($p) => !file_exists(self::ROOT . '/' . $p)));
        $this->ok($missing === [], 'README.md enlaza ficheros que no existen: ' . implode(', ', $missing));
    }

    /** 10: la tabla "Mejoras sugeridas" del README no lista como pendientes cosas ya hechas. */
    public function testIssue10_readmeImprovementsTableIsUpToDate(): void
    {
        $stale = [
            'sin tipo de retorno'                   => '5 (combine() ya tiene tipo de retorno)',
            '`intersectionMany`, `differenceMany`, `xorMany` ausentes' => '7 (los *Many existen)',
            'Cache de `pointKey`'                   => '1 (pointKey ya se cachea)',
            'no usa tolerancia correcta'            => '3 (arreglado en F4)',
        ];
        $readme = (string)file_get_contents(self::ROOT . '/README.md');
        $found = array_values(array_filter($stale, fn($v, $k) => str_contains($readme, $k), ARRAY_FILTER_USE_BOTH));
        $this->ok($found === [], 'la tabla "Mejoras sugeridas" del README sigue listando puntos ya hechos: ' . implode('; ', $found));
    }

    public function testIssue10_ciRunsPhpstan(): void
    {
        $this->ok(self::fileContains('.github/workflows/php.yml', '/phpstan/i'), 'la CI (.github/workflows/php.yml) no ejecuta PHPStan');
    }

    public function testIssue10_ciCacheKeyMatchesCommittedFiles(): void
    {
        $wf = file_get_contents(self::ROOT . '/.github/workflows/php.yml');
        if (str_contains($wf, "hashFiles('**/composer.lock')")) {
            $tracked = trim((string)shell_exec('git -C ' . escapeshellarg(self::ROOT) . ' ls-files composer.lock'));
            $this->ok($tracked !== '', 'la caché de la CI usa hashFiles(composer.lock), pero composer.lock no está en el repositorio');
        }
        $this->addToAssertionCount(1);
    }

    public function testIssue10_readmeListsRequiredPhpExtensions(): void
    {
        $missing = array_values(array_filter(
            ['dom', 'mbstring', 'xmlwriter'],
            fn($ext) => !self::fileContains('README.md', "/\\b$ext\\b/")
        ));
        $this->ok($missing === [], 'README no menciona las extensiones necesarias para PHPUnit: ' . implode(', ', $missing));
    }
}
