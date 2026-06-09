# Martinez Rueda PHP

Robust and tested library for boolean operations on polygons (union, intersection, difference, xor) in PHP.

Some improvements over other libraries: This one is tested (see end-to-end directory) and optimized to use polygons with large amount of vertex (ie, binary search when doing intersections between segments).

This library can use arrays with polygons, geojson strings or geojson files directly.

## Índice

1. [Arquitectura general](#arquitectura-general)
2. [Clases principales](#clases-principales)
3. [Flujo de procesamiento](#flujo-de-procesamiento)
4. [API pública](#api-pública)
5. [Ejemplos de uso](#ejemplos-de-uso)
6. [Optimizaciones implementadas](#optimizaciones-implementadas)
7. [Mejoras sugeridas](#mejoras-sugeridas)
8. [Profilig](#profiling)
9. [Testing](#testing)
A. [Tutorials and Notes](#tutorials-and-notes)

---

## Arquitectura general

La librería implementa un pipeline de tres fases para las operaciones booleanas:

```
Polígono(s) de entrada
       │
       ▼
  [FASE 1] segments()
  Convierte cada polígono en segmentos con información de relleno (Fill).
  Usa RegionIntersecter (self-intersection) para resolver solapamientos
  internos y asignar el flag inside/outside a cada lado del segmento.
       │
       ▼
  [FASE 2] combine() / combineSelect()
  Combina los segmentos de ambos polígonos mediante SegmentIntersecter,
  calculando intersecciones entre los dos conjuntos y propagando el Fill
  cruzado (otherFill). Luego aplica la lógica booleana (union/intersect/
  difference/xor) para filtrar qué segmentos forman parte del resultado.
       │
       ▼
  [FASE 3] polygon()
  Encadena los segmentos resultantes en anillos cerrados (segmentChainer),
  divide anillos con auto-intersecciones, y construye el Polygon final.
```

---

## Clases principales

### `Algorithm` (final)
Clase estática central. Contiene toda la lógica de alto nivel y las operaciones públicas.

| Constante / Método | Descripción |
|---|---|
| `TOLERANCE = 1e-12` | Épsilon para comparaciones geométricas. Equivale aprox. a 1 mm en coordenadas terrestres. |
| `TOLERANCE_SQRT = 1e-6` | Raíz de TOLERANCE, para comparaciones de valores ya elevados al cuadrado. |
| `segments(Polygon)` | Fase 1: convierte un polígono a `PolySegments`. |
| `combine(PolySegments, PolySegments)` | Fase 2: combina dos conjuntos de segmentos. |
| `polygon(PolySegments)` | Fase 3: reconstruye un `Polygon` desde segmentos. |
| `union / intersect / difference / xor` | Operaciones de alto nivel sobre dos `Polygon`. |
| `unionMany(array)` | Unión de N polígonos mediante reducción balanceada. |
| `segmentChainer(array)` | Ensambla segmentos sueltos en anillos cerrados. |
| `splitSelfTouchingRegions(array)` | Divide anillos que se auto-tocan en ciclos simples. |

### `Polygon` (final)
Representa un polígono como una lista de regiones (anillos), donde cada región es un array de objetos `Point`.

```php
$poly->regions    // array de array de Point
$poly->isInverted // true = el interior lógico es el exterior geométrico
$poly->numPoints  // total de puntos
$poly->getArray()       // convierte a array [[x,y], ...]
$poly->getArrayClosed() // igual pero cierra cada anillo
```

### `PolySegments` (final)
Contenedor intermedio entre fases. Almacena segmentos, flag `isInverted` y el bounding box precalculado (`bounds`).

### `Segment` (final)
Un segmento orientado entre dos `Point`. Contiene:
- `myFill`: relleno respecto al polígono propio (`Fill->above`, `Fill->below`).
- `otherFill`: relleno respecto al otro polígono (solo en fase combinada).
- `minX/maxX/minY/maxY/len2`: bounding box y longitud² cacheados para rechazo temprano en intersecciones.

### `Point` (final)
Coordenada 2D con métodos estáticos de geometría:

| Método | Descripción |
|---|---|
| `compare(p1, p2)` | Orden lexicográfico con tolerancia epsilon. |
| `collinear(p1, p2, p3)` | Test de colinealidad por área del triángulo. |
| `linesIntersect(a0, a1, b0, b1)` | Intersección de dos líneas; devuelve `IntersectionPoint` con flags `alongA`/`alongB` (-2,-1,0,1,2). |
| `between(p, a, b)` | ¿Está `p` estrictamente entre `a` y `b` en el segmento? |
| `pointAboveOrOnLine(p, left, right)` | Test de semiplano con tolerancia. |

### `Fill` (final)
Par de booleanos (`above`, `below`) que indica si el espacio a cada lado de un segmento está dentro del polígono.

### `EventList` / `StatusList` (final)
Estructuras de datos basadas en **Skip List** (trait `SkipListCore`). `EventList` mantiene la cola de eventos de barrido ordenada; `StatusList` mantiene el estado activo de segmentos durante el barrido.

### `GJTools` (final)
Utilidades para importar y normalizar GeoJSON:

| Método | Descripción |
|---|---|
| `geojsonToArray($source)` | Acepta string JSON, ruta de archivo, array o stdClass. Devuelve array de polígonos en formato `[[[x,y],...],...]`. |
| `compareCoordinates($a, $b, &$diff)` | Compara dos arrays de coordenadas punto a punto con tolerancia. |
| `canonicalizePolygons($polys, $precision)` | Normaliza polígonos (rotación al mínimo lexicográfico, redondeo). |
| `classifyRings($rings)` | Detecta relaciones interior/exterior entre anillos. |
| `buildPolygons($nodes, $enforceOrientation)` | Construye la estructura final respetando orientación CCW/CW. |

---

## Flujo de procesamiento

### Operación binaria típica

```
Algorithm::union($polyA, $polyB)
    └─► __operate($polyA, $polyB, Selector::Union)
            ├─► segments($polyA)  → PolySegments $a
            ├─► segments($polyB)  → PolySegments $b
            ├─► combine($a, $b)   → CombinedPolySegments
            ├─► selectUnion(...)  → PolySegments (segmentos filtrados)
            └─► polygon(...)      → Polygon resultado
```

### Barrido de línea (Intersecter)

El núcleo del algoritmo es un **barrido de línea** (sweep line) de izquierda a derecha:

1. Se crean eventos START y END para cada extremo de segmento.
2. Los eventos se procesan en orden lexicográfico (x, luego y) desde `EventList`.
3. Al procesar un START: se buscan vecinos en `StatusList` y se calculan intersecciones con el segmento de arriba y de abajo. Si hay intersección, se divide el segmento afectado.
4. Al procesar un END: se eliminan de `StatusList` y se emite el segmento con su Fill calculado.

### Ensamblado de anillos (segmentChainer)

Usa un índice hash `head → id` y `tail → id` para encadenar segmentos en O(1) por inserción. Incluye simplificación colineal en los puntos de empalme para eliminar vértices redundantes.

---

## API pública

### Operaciones binarias

```php
// Todos aceptan dos Polygon y devuelven un Polygon
Algorithm::union(Polygon $a, Polygon $b): Polygon
Algorithm::intersect(Polygon $a, Polygon $b): Polygon
Algorithm::intersection(Polygon $a, Polygon $b): Polygon  // alias de intersect
Algorithm::difference(Polygon $a, Polygon $b): Polygon    // A \ B
Algorithm::differenceRev(Polygon $a, Polygon $b): Polygon // B \ A
Algorithm::xoring(Polygon $a, Polygon $b): Polygon
```

### Operaciones n-arias

```php
// Unión de múltiples polígonos (al menos uno requerido)
Algorithm::unionMany(array $polygons): Polygon
```

### API de bajo nivel (para pipelines manuales)

```php
$segs1 = Algorithm::segments($polygon1);         // Fase 1
$segs2 = Algorithm::segments($polygon2);
$combined = Algorithm::combine($segs1, $segs2);  // Fase 2
$selected = Algorithm::selectUnion($combined);   // Selección lógica
$result   = Algorithm::polygon($selected);       // Fase 3
```

### Importación GeoJSON

```php
$polygons = GJTools::geojsonToArray('/ruta/a/archivo.geojson');
// o pasando JSON directamente:
$polygons = GJTools::geojsonToArray('{"type":"FeatureCollection", ...}');
```

### Construcción manual de polígonos

```php
$poly = Polygon::create()->fillFromArray([
    [[0,0], [10,0], [10,10], [0,10]],  // exterior
    [[2,2], [8,2], [8,8], [2,8]],      // agujero
]);
```

---

## Ejemplos de uso

### Ejemplo 1: Unión de dos rectángulos

```php
$rectA = Polygon::create()->fillFromArray([
    [[0,0], [10,0], [10,10], [0,10]]
]);

$rectB = Polygon::create()->fillFromArray([
    [[5,5], [15,5], [15,15], [5,15]]
]);

$resultado = Algorithm::union($rectA, $rectB);

// Obtener coordenadas
$coords = $resultado->getArray();
// $coords[0] → array de [x,y] del anillo exterior del resultado
```

### Ejemplo 2: Diferencia (recorte)

```php
$base = Polygon::create()->fillFromArray([
    [[0,0], [20,0], [20,20], [0,20]]
]);

$hueco = Polygon::create()->fillFromArray([
    [[5,5], [15,5], [15,15], [5,15]]
]);

// Base con un agujero cuadrado en el centro
$resultado = Algorithm::difference($base, $hueco);
```

### Ejemplo 3: Unión de múltiples polígonos

```php
$polígonos = array_map(function($ring) {
    return Polygon::create()->fillFromArray([$ring]);
}, $arrayDeAnillos);

$unido = Algorithm::unionMany($polígonos);
```

### Ejemplo 4: Desde GeoJSON

```php
$rings = GJTools::geojsonToArray('archivo.geojson');

// Convertir a objetos Polygon
$polygons = array_map(function($ringSet) {
    return Polygon::create()->fillFromArray($ringSet);
}, $rings);

$resultado = Algorithm::unionMany($polygons);
$geojsonCoords = $resultado->getArrayClosed();
```

### Ejemplo 5: Pipeline manual (máximo control)

```php
// Procesar polígonos por separado y reutilizar segmentos
$segsA = Algorithm::segments($polyA);
$segsB = Algorithm::segments($polyB);

// Probar varias operaciones sin re-procesar los polígonos originales
$union  = Algorithm::polygon(Algorithm::selectUnion(Algorithm::combine($segsA, $segsB)));
$inter  = Algorithm::polygon(Algorithm::selectIntersect(Algorithm::combine($segsA, $segsB)));
```

---

## Optimizaciones implementadas

### 1. Skip List para EventList y StatusList
En lugar de listas enlazadas simples (O(n) por búsqueda), se usa una **Skip List** con altura aleatoria hasta 32 niveles. Esto reduce búsquedas e inserciones a **O(log n)** amortizado, crítico en el barrido de línea donde estas operaciones son el cuello de botella.

### 2. Índices hash para segmentChainer
El ensamblado de anillos usa tablas hash (`$headIndex`, `$tailIndex`) indexadas por clave de punto cuantizada. El coste de buscar si un punto ya existe en alguna cadena es **O(1)** en lugar de O(n).

### 3. Bounding box cacheada en Segment
Cada `Segment` precalcula y almacena `minX`, `maxX`, `minY`, `maxY` y `len2` al crearse (y al recalcularse tras división). El rechazo temprano por AABB en `checkIntersection` evita calcular la intersección algebraica entre la gran mayoría de pares de segmentos no solapantes.

### 4. Bounds de PolySegments y cortocircuito geométrico
`PolySegments` almacena el bounding box del conjunto de segmentos. Las operaciones `unionSegments`, `intersectSegments`, `xorSegments` y `differenceSegments` comprueban primero si los bounds se solapan:
- **Union/XOR sin solape**: concatena segmentos directamente sin intersección.
- **Intersection sin solape**: devuelve vacío inmediatamente.
- **Difference sin solape**: devuelve el operando izquierdo directamente.

### 5. Reducción balanceada para unionMany
`reduceBalanced` combina los polígonos en un árbol binario en lugar de linealmente, reduciendo el número de operaciones de O(n) a **O(log n)** niveles de combinación, con mejor comportamiento de memoria.

### 6. Ordenación por posición antes de la reducción
Antes de la reducción, los segmentos se ordenan por la coordenada X mínima de su bounding box. Esto tiende a emparejar polígonos espacialmente cercanos primero, maximizando los cortocircuitos geométricos del punto 4.

### 7. Simplificación colineal en el ensamblado
Al encadenar segmentos, `appendChainIdx` y el caso de extensión en `segmentChainer` eliminan vértices intermedios cuando tres puntos consecutivos son colineales. Esto reduce el número de vértices del resultado sin pérdida de precisión.

### 8. Cuantización de puntos para las claves hash
`pointKey()` cuantiza las coordenadas multiplicando por `1/TOLERANCE` y redondeando. Así, dos puntos dentro de la tolerancia producen la misma clave, evitando cadenas huérfanas por errores de punto flotante.

### 9. Back-pointer `snode` en Node
Cada `Node` de evento guarda una referencia directa al `SkipNode` que lo contiene. El borrado de un evento es **O(altura del nodo)** sin necesidad de buscar, evitando cierres (closures) por nodo y reduciendo presión sobre el GC.

### 10. Splitting de anillos auto-tocados
`splitSelfTouchingRegions` detecta y divide anillos que se auto-intersectan en un punto (figura en "8"), garantizando que el polígono final solo contenga ciclos simples válidos.

---

## Mejoras sugeridas

> Las mejoras detalladas, con propuestas de implementación, se encuentran en el documento [`IMPROVEMENTS.md`](./IMPROVEMENTS.md).

### Resumen

| # | Mejora | Impacto | Complejidad |
|---|---|---|---|
| 1 | Cache de `pointKey` en `Point` | Rendimiento (CPU) | Baja |
| 2 | Parámetro `$precision` ignorado en `ringKey` / `polygonKey` | Corrección de bug | Baja |
| 3 | `removeColinearPointsFromPolygon` no usa tolerancia correcta | Corrección numérica | Baja |
| 4 | `__splitRegionAtDuplicates` es O(n²) recursivo; puede ser O(n) | Rendimiento | Media |
| 5 | Método público `combine()` sin tipo de retorno declarado | Calidad de código | Muy baja |
| 6 | `__operate` usa `CombinedPolySegments` innecesariamente | Simplificación | Baja |
| 7 | `intersectionMany`, `differenceMany`, `xorMany` ausentes | Funcionalidad | Media |
| 8 | `GJTools::buildGeometryFromCoordinates` llama a `json_decode` sobre un array | Bug potencial | Baja |

## Profiling

- check that xdebug module is available and loaded:
php -m | grep xdebug
- launch php with xdebug enabled:
php \
    -dxdebug.mode=profile \
    -dxdebug.start_with_request=yes \
    -dxdebug.output_dir=xdebug-profiler/ \
    tests.php

## Testing

Some tests inside tests.php, extended coverage with PHPUnit:

- php tests.php
- ./vendor/bin/phpunit tests/
- ./vendor/phpstan/phpstan/phpstan --error-format=raw

## Tutorials and notes

* [Interactive tutorial Polygon Clipping (Part 1)](https://sean.fun/a/polygon-clipping-pt1/)
* [Interactive tutorial Polygon Clipping (Part 2)](https://sean.fun/a/polygon-clipping-pt2/)
* [Notes on the Martinez-Rueda Polygon Clipping algorithm](https://liorsinai.github.io/mathematics/2025/01/11/bentley-ottman.html)
* Let's build! Boolean operations of polygons[Part 1 - Introduction](https://wellquite.org/posts/lets_build/polygons_intro/)[Part 2 - Intersections](https://wellquite.org/posts/lets_build/polygons_intersections/)

## Resources

* To understand the algorithm, you can go [here](https://unpkg.com/polybooljs@1.2.0/dist/demo.html)
* Based somewhat on the F. Martinez (2008) algorithm:
    * [Website](https://www4.ujaen.es/~fmartin/bool_op.html)
    * A new algorithm for computing Boolean operations on polygons 2009 [Research Gate](https://www.researchgate.net/publication/220163820_A_new_algorithm_for_computing_Boolean_operations_on_polygons)[Science Direct](https://www.sciencedirect.com/science/article/abs/pii/S0965997813000379)
    * [A simple algorithm for Boolean operations on polygons 2013](https://investigacion.ujaen.es/documentos/5f1cdfbd29995265e44d906f?lang=en)

### History
* This library is a port for PHP of [pypolybool](https://github.com/KaivnD/pypolybool)
* Previoulsy, was a port for JS of [polybooljs](https://github.com/velipso/polybooljs)
* Help from a .NET [port](https://github.com/idormenco/PolyBool.Net)

