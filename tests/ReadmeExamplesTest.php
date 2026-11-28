<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Algorithm;
use Ifsnop\MartinezRueda\Polygon;
use Ifsnop\MartinezRueda\Tests\Support\RegionComparator;

include_once('autoloader.php');

/**
 * Ejecuta los ejemplos de código de la API de bajo nivel TAL COMO ESTÁN EN EL README y
 * comprueba que dan lo mismo que las operaciones de alto nivel. El ejemplo del README
 * llevaba tiempo sin funcionar (selectUnion() con un argumento en vez de tres) sin que
 * nada lo detectase; si la API cambia sin actualizar la documentación, esto falla.
 */
final class ReadmeExamplesTest extends TestCase
{
    private const CASES = [
        'solapados'     => [[[0, 0], [10, 0], [10, 10], [0, 10]], [[5, 5], [15, 5], [15, 15], [5, 15]]],
        'B dentro de A' => [[[0, 0], [10, 0], [10, 10], [0, 10]], [[2, 2], [4, 2], [4, 4], [2, 4]]],
        'disjuntos'     => [[[0, 0], [1, 0], [1, 1], [0, 1]], [[5, 5], [6, 5], [6, 6]]],
    ];

    /** Bloque ```php que sigue al encabezado dado en el README. */
    private static function readmeBlock(string $heading): string
    {
        $readme = (string)file_get_contents(__DIR__ . '/../README.md');
        $pos = strpos($readme, $heading);
        self::assertNotFalse($pos, "no se encuentra el encabezado «{$heading}» en el README");
        $ok = preg_match('/```php\n(.*?)```/s', substr($readme, $pos), $m);
        self::assertSame(1, $ok, "no hay bloque php tras «{$heading}»");
        return $m[1];
    }

    /** Ejecuta un bloque con las variables dadas y devuelve las variables resultantes. */
    private static function runBlock(string $code, array $vars): array
    {
        return (static function (string $__code, array $__vars): array {
            extract($__vars);
            eval('use Ifsnop\MartinezRueda\Algorithm; use Ifsnop\MartinezRueda\Polygon; ' . $__code);
            return get_defined_vars();
        })($code, $vars);
    }

    public static function provideCases(): array
    {
        return array_map(fn($c) => $c, self::CASES);
    }

    /**
     * @dataProvider provideCases
     */
    public function testLowLevelApiSection(array $a, array $b): void
    {
        $pa = Polygon::create()->fillFromArray([$a]);
        $pb = Polygon::create()->fillFromArray([$b]);
        $vars = self::runBlock(self::readmeBlock('### API de bajo nivel (para pipelines manuales)'), ['polygon1' => $pa, 'polygon2' => $pb]);
        $this->assertInstanceOf(Polygon::class, $vars['result']);
        $this->assertTrue(RegionComparator::equal($vars['result']->getArray(), Algorithm::union($pa, $pb)->getArray(), null, $why), (string)$why);
    }

    /**
     * @dataProvider provideCases
     */
    public function testManualPipelineExample(array $a, array $b): void
    {
        $pa = Polygon::create()->fillFromArray([$a]);
        $pb = Polygon::create()->fillFromArray([$b]);
        $vars = self::runBlock(self::readmeBlock('### Ejemplo 5: Pipeline manual (máximo control)'), ['polyA' => $pa, 'polyB' => $pb]);
        $this->assertTrue(RegionComparator::equal($vars['union']->getArray(), Algorithm::union($pa, $pb)->getArray(), null, $why), "union: $why");
        $this->assertTrue(RegionComparator::equal($vars['inter']->getArray(), Algorithm::intersect($pa, $pb)->getArray(), null, $why), "intersect: $why");
    }
}
