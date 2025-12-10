<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\GJTools;

final class GJToolsTest extends TestCase
{
    /** Utilidad: assert de estructura [ [ [ [x,y], ... ], [hole], ... ], ... ] */
    private function assertPolygonStructure(array $polygons): void
    {
        $this->assertIsArray($polygons);
        foreach ($polygons as $poly) {
            $this->assertIsArray($poly);
            $this->assertNotEmpty($poly); // al menos anillo exterior
            foreach ($poly as $ring) {
                $this->assertIsArray($ring);
                $this->assertGreaterThanOrEqual(3, count($ring)); // triángulo mínimo
                foreach ($ring as $pt) {
                    $this->assertIsArray($pt);
                    $this->assertCount(2, $pt);
                    $this->assertIsFloat($pt[0]);
                    $this->assertIsFloat($pt[1]);
                }
            }
        }
    }

    public function testSimplePolygon(): void
    {
        $gj = [
            'type' => 'Polygon',
            'coordinates' => [
                // exterior ring cerrado
		[[0,0], [10,0], [10,10], [0,10], [0,0]]
            ],
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(1, $out);
        $this->assertCount(1, $out[0]); // 1 anillo
	$this->assertEqualsWithDelta([[[0,0], [10,0], [10,10], [0,10], [0,0]]], $out[0], 0.01);
        $this->assertPolygonStructure($out);
    }

    public function testPolygonWithHoleAnd3DCoords(): void
    {
        $gj = [
            'type' => 'Polygon',
            'coordinates' => [
                // exterior (cerrado)
                [[0,0,5], [10,0,5], [10,10,5], [0,10,5], [0,0,5]],
                // hueco (cerrado)
                [[2,2,99], [8,2,99], [8,8,99], [2,8,99], [2,2,99]],
            ],
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(1, $out);          // un polígono
        $this->assertCount(2, $out[0]);       // 1 exterior + 1 hueco
        // 3D reducido a 2D y cierre eliminado
	// Ojo, geojsonToPolygons normaliza y ordena el polígono
        $this->assertEqualsWithDelta([[0.0,0.0],[10.0,0.0],[10.0,10.0],[0.0,10.0],[0.0,0.0]], $out[0][0], 0.1);
        $this->assertEqualsWithDelta([[2.0,2.0],[2.0,8.0],[8.0,8.0],[8.0,2.0],[2.0,2.0]], $out[0][1], 0.1);
        $this->assertPolygonStructure($out);
    }

    public function testMultiPolygon(): void
    {
        $gj = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                // Polígono 1
                [
                    [[0,0],[5,0],[5,5],[0,5],[0,0]],
                ],
                // Polígono 2 con hueco
                [
                    [[10,10],[20,10],[20,20],[10,20],[10,10]],
                    [[12,12],[18,12],[18,18],[12,18],[12,12]]
                ],
            ],
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(2, $out);
        $this->assertCount(1, $out[0]);
        $this->assertCount(2, $out[1]);
        $this->assertPolygonStructure($out);
    }

    public function testFeature(): void
    {
        $gj = [
            'type' => 'Feature',
            'properties' => ['name' => 'test'],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [[0,0],[1,0],[1,1],[0,1],[0,0]]
                ]
            ]
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(1, $out);
        $this->assertPolygonStructure($out);
    }

    public function testFeatureCollectionMixedGeometries(): void
    {
        $gj = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [1,2]
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[0,0],[1,1]]
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [[0,0],[2,0],[2,2],[0,2],[0,0]]
                        ]
                    ]
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'MultiPolygon',
                        'coordinates' => [
                            [[[10,10],[11,10],[11,11],[10,11],[10,10]]]
                        ]
                    ]
                ],
            ],
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(2, $out); // solo extrae polígonos
        $this->assertPolygonStructure($out);
    }

    public function testGeometryCollection(): void
    {
        $gj = [
            'type' => 'GeometryCollection',
            'geometries' => [
                [
                    'type' => 'Polygon',
                    'coordinates' => [
                        [[0,0],[3,0],[3,3],[0,3],[0,0]]
                    ]
                ],
                [
                    'type' => 'MultiPolygon',
                    'coordinates' => [
                        [[[5,5],[6,5],[6,6],[5,6],[5,5]]]
                    ]
                ],
                [
                    'type' => 'Point',
                    'coordinates' => [99,99]
                ],
            ]
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(2, $out);
        $this->assertPolygonStructure($out);
    }

    public function testNullGeometryAndWeirdRoot(): void
    {
        $gj = [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [],
        ];

        $out = GJTools::geojsonToArray($gj);
        $this->assertCount(0, $out);

        $weird = [
            // sin 'type', pero con 'features'
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [
                            [[0,0],[1,0],[1,1],[0,1],[0,0]]
                        ]
                    ]
                ]
            ]
        ];

        $out2 = GJTools::geojsonToArray($weird);
        $this->assertCount(1, $out2);
        $this->assertPolygonStructure($out2);
    }

    public function testDegenerateRingsAndInvalidCoords(): void
    {
        $gj = [
            'type' => 'Polygon',
            'coordinates' => [
                // exterior con puntos no numéricos y duplicado de cierre
                [[0,'a'], [5,0], [5,5], [0,5], [0,5], [0,0]],
                // hueco degenerado (< 4 puntos con cierre) → debe ignorarse
                [[1,1],[2,2],[1,1]]
            ],
        ];

	$this->expectException(\InvalidArgumentException::class);
	$this->expectExceptionCode(101);
        $out = GJTools::geojsonToArray($gj);
        // exterior válido tras filtrar: [ [5,0],[5,5],[0,5],[0,0] ] o similar si (0,'a') se descarta
        //$this->assertCount(1, $out);
        //$this->assertCount(1, $out[0]); // hueco degenerado eliminado
        //$this->assertGreaterThanOrEqual(3, count($out[0][0]));
        //$this->assertPolygonStructure($out);
    }

    public function testEnforceOrientation(): void
    {
        // Exterior dado en CW y hueco en CCW para forzar corrección
        $gj = [
            'type' => 'Polygon',
            'coordinates' => [
                // Exterior CW (square 0,0 -> 0,10 -> 10,10 -> 10,0)
                [[0,0], [0,10], [10,10], [10,0], [0,0]],
                // Hueco CCW (se deberá invertir a CW)
                [[2,2], [8,2], [8,8], [2,8], [2,2]],
            ],
        ];

        $outNo = GJTools::geojsonToArray($gj, false);
        $outYes = GJTools::geojsonToArray($gj, true);

        // Sin enforcement, las orientaciones se mantienen
        $this->assertNotEmpty($outNo);
        $this->assertNotEmpty($outYes);

        // Con enforcement: exterior CCW (>0) y hueco CW (<0)
        $exterior = $outYes[0][0];
        $hole = $outYes[0][1];

        $areaExterior = $this->ringSignedArea($exterior);
        $areaHole     = $this->ringSignedArea($hole);

        $this->assertGreaterThan(0.0, $areaExterior, 'El exterior debe ser CCW (>0)');
        $this->assertLessThan(0.0, $areaHole, 'El hueco debe ser CW (<0)');

        $this->assertPolygonStructure($outYes);
    }

    public function testInputAsJsonStringAndFilePath(): void
    {
        $geo = [
            'type' => 'Polygon',
            'coordinates' => [
                [[0,0],[2,0],[2,2],[0,2],[0,0]]
            ]
        ];

        $json = json_encode($geo);

        $outFromString = GJTools::geojsonToArray($json);
        $this->assertCount(1, $outFromString);

        // Escribir a archivo temporal
        $tmp = tempnam(sys_get_temp_dir(), 'gj_');
        file_put_contents($tmp, $json);
        try {
            $outFromFile = GJTools::geojsonToArray($tmp);
            $this->assertCount(1, $outFromFile);
            $this->assertEquals($outFromString, $outFromFile);
        } finally {
            @unlink($tmp);
        }
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GJTools::geojsonToArray('{invalid json}');
    }

    /** ===== Helpers internos del test ===== */

    /** Shoelace para validar orientación en tests */
    private function ringSignedArea(array $ring): float
    {
        $n = count($ring);
        if ($n < 3) return 0.0;
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $sum += ($ring[$i][0] * $ring[$j][1]) - ($ring[$j][0] * $ring[$i][1]);
        }
        return $sum / 2.0;
    }
}
