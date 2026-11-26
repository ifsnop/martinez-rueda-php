<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\GeometryOracle;
use Ifsnop\MartinezRueda\Tests\Support\RadarCoverage;

include_once('autoloader.php');

/**
 * Caso de uso real (coberturas radar) en versión reducida para la suite normal, y el
 * caso mínimo de ISSUES.md #11. La versión exigente (8 y 9 radares, 32 escenarios) está
 * en tests/stress y se ejecuta en cada merge a main.
 */
final class RadarCoverageTest extends TestCase
{
    /**
     * ISSUES.md #11: dos piezas calculadas en operaciones distintas comparten un vértice
     * que difiere en ~4 ulps (2.8e-14°) y un vértice está a 7.7e-15° de la arista de la
     * otra. Los predicados los consideran iguales/alineados, las coordenadas no, y la unión
     * salía VACÍA. Reducido de un escenario de 8 radares (seed 2, 7 decimales).
     */
    public function testUnionOfPiecesWithVerticesDifferingInAFewUlps(): void
    {
        $s = [[-4.198542751508911, 40.46079948281863], [-4.198043193580838, 40.46064771317846], [-4.173255741665298, 40.47733405872846]];
        $i = [[-4.0337834162637805, 40.444787067928594], [-4.201429297374132, 40.458368265571025],
              [-4.173255741665326, 40.47733405872843], [-4.117957492323765, 40.51455957508919]];
        $u = Algorithm::union(Polygon::create()->fillFromArray([$s]), Polygon::create()->fillFromArray([$i]))->getArray();
        $this->assertNotSame([], $u, 'la unión sale vacía');

        $all = array_merge($s, $i);
        $xs = array_column($all, 0);
        $ys = array_column($all, 1);
        mt_srand(1);
        $pts = [];
        for ($k = 0; $k < 3000; $k++) {
            $pts[] = [min($xs) + mt_rand() / mt_getrandmax() * (max($xs) - min($xs)),
                      min($ys) + mt_rand() / mt_getrandmax() * (max($ys) - min($ys))];
        }
        $wrong = GeometryOracle::countWrong([$s], [$i], $u, fn(bool $a, bool $b) => $a || $b, $pts, 1e-9);
        $this->assertSame(0, $wrong, "$wrong puntos mal clasificados en la unión");
    }

    public static function provideScenarios(): array
    {
        return [
            '4 radares, precisión completa' => [1, null],
            '4 radares, 7 decimales'        => [2, 7],
        ];
    }

    /**
     * @dataProvider provideScenarios
     */
    public function testCoverageLayersAreConsistent(int $seed, ?int $round): void
    {
        $scenario = RadarCoverage::generate($seed, 4, $round, false, 180);
        $result = RadarCoverage::check($scenario['coverages'], 2000);
        $this->assertSame([], $result['problems'], implode(PHP_EOL, $result['problems']));
    }
}
