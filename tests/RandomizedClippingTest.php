<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\GeometryOracle;
use Ifsnop\MartinezRueda\Tests\Support\PolygonGenerator;

include_once('autoloader.php');

/**
 * Test aleatorio (property-based) de las cuatro operaciones booleanas.
 *
 * Para cada par de polígonos generado aleatoriamente se calcula union,
 * intersect, difference y xor, y se compara el resultado con un oráculo
 * independiente (tests/Support/GeometryOracle.php): para puntos de muestra
 * aleatorios, "dentro del resultado" debe ser igual a op(dentro de A, dentro de B),
 * usando la regla par-impar (la misma que aplica la librería). Se ignoran los
 * puntos muy cercanos a cualquier arista, donde la clasificación es ambigua.
 *
 * Las semillas son fijas, así que el test es determinista. Variables de entorno:
 *   FUZZ_ITERS  pares de polígonos por generador y semilla (por defecto 20)
 *   FUZZ_SEEDS  número de semillas por generador (por defecto 2)
 */
final class RandomizedClippingTest extends TestCase
{
    /** [desplazamiento, escala, decimales] para simular coordenadas lon/lat habituales. */
    private const PLACEMENTS = [
        'unit'    => [0.0, 1.0, null],
        'lonlat'  => [-3.7, 0.01, null],          // rasgos de ~1-10 km en grados, cerca de Madrid
        'metre'   => [-3.7, 1e-5, null],          // rasgos de ~1-5 m en grados (edificios, parcelas): ISSUES.md #1
        'geojson' => [[-3.7, 40.4], 0.02, 7],     // ~1-15 km, redondeado a 7 decimales como un GeoJSON: ISSUES.md #2
    ];

    public static function provideCases(): array
    {
        $seeds = (int)(getenv('FUZZ_SEEDS') ?: 2);
        $cases = [];
        foreach (array_keys(PolygonGenerator::GENERATORS) as $gen) {
            foreach (self::PLACEMENTS as $pname => [$offset, $scale, $round]) {
                for ($seed = 1; $seed <= $seeds; $seed++) {
                    $cases["$gen/$pname/seed$seed"] = [$gen, $seed, $offset, $scale, $round];
                }
            }
        }
        return $cases;
    }

    /**
     * @dataProvider provideCases
     */
    public function testOperationsMatchPointOracle(string $gen, int $seed, float|array $offset, float $scale, ?int $round): void
    {
        $iters = (int)(getenv('FUZZ_ITERS') ?: 20);
        $failures = GeometryOracle::fuzz($gen, $seed, $iters, $offset, $scale, $round);

        $this->assertSame(
            [],
            array_slice($failures, 0, 3),
            count($failures) . " operaciones incorrectas (generador {$gen}: " . PolygonGenerator::GENERATORS[$gen] . ")"
        );
    }

    /**
     * Regresión: dos cuadrados con una arista común dentro del mismo polígono,
     * operados contra un polígono cuya bounding box no se solapa (atajo de
     * unionSegments/xorSegments/differenceSegments). Antes se perdía el segundo cuadrado.
     */
    public function testAdjacentRingsWithDisjointBoundingBoxes(): void
    {
        $two = [[[0, 0], [1, 0], [1, 1], [0, 1]], [[1, 0], [2, 0], [2, 1], [1, 1]]];
        $far = [[[10, 10], [11, 10], [11, 11], [10, 11]]];
        $mk = fn($r) => Polygon::create()->fillFromArray($r);
        $in = fn($rings, $x, $y) => GeometryOracle::inside($rings, $x, $y);

        $union = Algorithm::union($mk($two), $mk($far))->getArray();
        $this->assertTrue($in($union, 1.5, 0.5), 'union pierde el segundo cuadrado');
        $this->assertTrue($in($union, 0.5, 0.5));
        $this->assertTrue($in($union, 10.5, 10.5));

        $xor = Algorithm::xoring($mk($two), $mk($far))->getArray();
        $this->assertTrue($in($xor, 1.5, 0.5), 'xor pierde el segundo cuadrado');

        $diff = Algorithm::difference($mk($two), $mk($far))->getArray();
        $this->assertTrue($in($diff, 1.5, 0.5), 'difference pierde el segundo cuadrado');
        $this->assertTrue($in($diff, 0.5, 0.5));
        $this->assertFalse($in($diff, 10.5, 10.5));
    }
}
