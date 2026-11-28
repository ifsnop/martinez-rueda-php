<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Tests\Support\RadarCoverage;

include_once('autoloader.php');

/**
 * Suite de estrés: coberturas radar exigentes (ver tests/Support/RadarCoverage.php).
 *
 * 32 escenarios deterministas, 16 de 8 radares y 16 de 9 radares, todos dentro de un
 * círculo de 300 km, con 720 vértices por radar y bordes muy irregulares:
 *   - 6 con coordenadas de precisión completa (alcance 150-250 km),
 *   - 6 con coordenadas redondeadas a 7 decimales (como un GeoJSON típico),
 *   - 4 de alcance largo (250-450 km): zonas cubiertas por los 8 o 9 radares.
 * Cada escenario calcula las capas de cobertura exacta por dos métodos y las comprueba
 * de forma independiente de la librería. Un escenario tarda ~1-3 minutos.
 *
 * No está en la suite por defecto. Se ejecuta en cada merge a main
 * (.github/workflows/stress.yml), repartido en trozos. En local:
 *
 *     vendor/bin/phpunit --testsuite stress
 *     STRESS_SHARDS=4 STRESS_SHARD=0 vendor/bin/phpunit --testsuite stress   # trozo 1 de 4
 *
 * El escenario "8 radares, seed 2, 7 decimales" encontró el fallo de ISSUES.md #11.
 */
final class RadarCoverageStressTest extends TestCase
{
    public static function provideScenarios(): array
    {
        $all = [];
        foreach ([8, 9] as $radars) {
            for ($seed = 1; $seed <= 6; $seed++) {
                $all["$radars radares, seed $seed, precisión completa"] = [$radars, $seed, null, false];
                $all["$radars radares, seed $seed, 7 decimales"] = [$radars, $seed, 7, false];
            }
            for ($seed = 1; $seed <= 4; $seed++) {
                $all["$radars radares, seed $seed, alcance largo"] = [$radars, $seed, null, true];
            }
        }
        $shards = (int)(getenv('STRESS_SHARDS') ?: 1);
        $shard = (int)(getenv('STRESS_SHARD') ?: 0);
        $i = 0;
        $mine = [];
        foreach ($all as $name => $args) {
            if ($i++ % $shards === $shard) {
                $mine[$name] = $args;
            }
        }
        return $mine;
    }

    /**
     * @dataProvider provideScenarios
     */
    public function testCoverageLayersAreConsistent(int $radars, int $seed, ?int $round, bool $longRange): void
    {
        $scenario = RadarCoverage::generate($seed, $radars, $round, $longRange);
        $result = RadarCoverage::check($scenario['coverages']);
        fwrite(STDERR, sprintf("  %d radares, seed %d%s%s: %d anillos, %d vértices, %ss/%ss, puntos por nº de radares %s\n",
            $radars, $seed, $round ? ", $round decimales" : '', $longRange ? ', alcance largo' : '',
            $result['stats']['anillos'], $result['stats']['vértices'], $result['stats']['segundos'][0], $result['stats']['segundos'][1],
            implode(' ', $result['stats']['puntos por nº de radares'])));
        $this->assertSame([], $result['problems'], implode(PHP_EOL, $result['problems']));
    }
}
