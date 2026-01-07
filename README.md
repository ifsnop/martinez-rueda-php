

# Martinez Rueda PHP

Robust and tested library for boolean operations on polygons (union, intersection, difference, xor) in PHP.

Some improvements over other libraries: This one is tested (see end-to-end directory) and optimized to use polygons with large amount of vertex (ie, binary search when doing intersections between segments).

This library can use arrays with polygons, geojson strings or geojson files directly.

# Resources

* To understand the algorithm, you can go [here](https://unpkg.com/polybooljs@1.2.0/dist/demo.html)
* Based somewhat on the F. Martinez (2008) algorithm:
    * [Website](https://www4.ujaen.es/~fmartin/bool_op.html)
    * A new algorithm for computing Boolean operations on polygons 2009 [Research Gate](https://www.researchgate.net/publication/220163820_A_new_algorithm_for_computing_Boolean_operations_on_polygons)[Science Direct](https://www.sciencedirect.com/science/article/abs/pii/S0965997813000379)
    * [A simple algorithm for Boolean operations on polygons 2013](https://investigacion.ujaen.es/documentos/5f1cdfbd29995265e44d906f?lang=en)

# Examples

Some tests and demo implementation inside tests.php, extended coverage with PHPUnit:

- php tests.php
- ./vendor/bin/phpunit tests/
- ./vendor/phpstan/phpstan/phpstan --error-format=raw

For profiling:

- check that xdebug module is available and loaded:
php -m | grep xdebug
- launch php with xdebug enabled:
php \
    -dxdebug.mode=profile \
    -dxdebug.start_with_request=yes \
    -dxdebug.output_dir=xdebug-profiler/ \
    tests.php


# Tutorials and notes

* [Interactive tutorial Polygon Clipping (Part 1)](https://sean.fun/a/polygon-clipping-pt1/)
* [Interactive tutorial Polygon Clipping (Part 2)](https://sean.fun/a/polygon-clipping-pt2/)
* [Notes on the Martinez-Rueda Polygon Clipping algorithm](https://liorsinai.github.io/mathematics/2025/01/11/bentley-ottman.html)
* Let's build! Boolean operations of polygons[Part 1 - Introduction](https://wellquite.org/posts/lets_build/polygons_intro/)[Part 2 - Intersections](https://wellquite.org/posts/lets_build/polygons_intersections/)

# Improvements
* [Speed improvement with inserts by bisection](https://github.com/velipso/polybooljs/issues/23) [Pull](https://github.com/velipso/polybooljs/pull/28)

# History
* This library is a port for PHP of [pypolybool](https://github.com/KaivnD/pypolybool)
* Previoulsy, was a port for JS of [polybooljs](https://github.com/velipso/polybooljs)
* Help from a .NET [port](https://github.com/idormenco/PolyBool.Net)
