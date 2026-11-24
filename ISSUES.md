# Known issues and findings

Results of a robustness review done on 2026-11-24 (randomized testing against an
independent point-in-polygon oracle, the polygon-clipping end-to-end fixtures,
PHPStan, and a dedicated study of coordinate-scale precision with lon/lat inputs).

Each entry lists **the problem**, **how it was found / how to reproduce it**, the
**proposed fix** and **how to verify** it, so items can be fixed and checked one
at a time. Tick the box when an item is done.

- Severity: **High** = wrong results on realistic input · **Medium** = wrong results
  on unusual input, or a gap that hides bugs · **Low** = robustness, tooling, cleanup.
- Line numbers refer to branch `fix/edge-shortcut-phpstan-fuzz` and will drift.
- **Progress check:** `tests/issues/IssuesProgressTest.php` has one or more tests
  per item, named after its ID (`testF1_…`, `testIssue01_…`). A pass means fixed,
  a fail means still open, and the failure message says what's missing. The file
  isn't in the default suite, so it doesn't break CI. Run it with:

  ```
  vendor/bin/phpunit --testsuite issues --testdox --colors=never
  ```

  The decision items (3, 7) can't know which way you'll decide; those tests check
  that whichever decision you make is applied consistently and documented. If you
  fix an item differently from the proposal, check its test still measures the
  right thing. A green check doesn't replace running `vendor/bin/phpunit` and
  `php tests.php`. For example, the snap experiment in item 2 turns its checks
  green but breaks `tests.php` #8.
- `tests/RandomizedClippingTest.php` is the main tool for checking any geometric
  change. Run a deeper pass with
  `FUZZ_ITERS=200 FUZZ_SEEDS=5 vendor/bin/phpunit tests/RandomizedClippingTest.php`.

## Summary

| ID | Title | Severity | Status |
|---|---|---|---|
| [F1](#f1-lost-regions-when-bounding-boxes-dont-overlap) | Lost regions when bounding boxes don't overlap | High | Fixed (`c80e851`) |
| [F2](#f2-phpstan-never-analysed-any-file) | PHPStan never analysed any file | Low | Fixed (`45bdeee`) |
| [F3](#f3-single-geometry-fixtures-were-always-skipped) | 15 single-geometry fixtures always skipped | Medium | Fixed (`a11d7ab`) |
| [F4](#f4-gjtools-collinear-removal-deleted-the-wrong-vertex) | GJTools collinear removal deleted the wrong vertex | High | Fixed (this branch) |
| [1](#1-scale-dependent-tolerances-small-lonlat-features-give-wrong-results) | Scale-dependent tolerances: small lon/lat features give wrong results | High | Open, fix proposed and tested |
| [2](#2-endpoint-snapping-tolerance-is-relative-to-segment-length) | Endpoint snapping tolerance is relative to segment length | High | Open, needs study |
| [3](#3-fill-rule-for-overlapping-parts-and-duplicated-rings-is-undecided) | Fill rule for overlapping parts / duplicated rings undecided | Medium | Open, decision needed (behaviour is consistent) |
| [4](#4-e2e-comparison-uses-the-library-itself-false-passes) | E2E comparison uses the library itself (false passes) | Medium | Open |
| [5](#5-polygoncreate-leaves-isinverted-uninitialized) | `Polygon::create()` leaves `isInverted` uninitialized | Low | Open |
| [6](#6-many-with-a-single-polygon-return-the-raw-input) | `*Many()` with a single polygon return the raw input | Low | Open |
| [7](#7-gjtools-rejects-the-whole-input-when-one-ring-is-degenerate) | GJTools rejects the whole input when one ring is degenerate | Low | Open, decision needed |
| [8](#8-pointkey-overflows-and-projected-coordinates-are-not-supported) | `pointKey()` overflow / projected coordinates | Low (lon/lat) | Open |
| [9](#9-remaining-phpstan-findings) | Remaining PHPStan findings | Low | Open |
| [10](#10-repository-and-ci-hygiene) | Repository and CI hygiene | Low | Mostly fixed; README table pending |
| [L](#known-limitations-accepted) | Known limitations (accepted) | — | Documented |

---

## Fixed

### F1. Lost regions when bounding boxes don't overlap

- [x] Fixed in `c80e851`.

**Problem.** `unionSegments`, `xorSegments` and `differenceSegments`
(`src/Ifsnop/MartinezRueda/Algorithm.php`) skip `combineSelect()` when the
operands' bounding boxes don't overlap, and pass the phase-1 segments directly to
`polygon()`. Phase 1 also produces *internal* edges, which have the same fill on
both sides (for example the edge shared by two adjacent rings of a multipolygon).
`combineSelect()` would discard them. Left in, they break the ring chainer, and
whole regions disappear.

**Repro (before the fix).** `union([[0,0],[1,0],[1,1],[0,1]] + [[1,0],[2,0],[2,1],[1,1]], far-away square)`
returned only the first square. The randomized test failed on 2.7% (grid) and
5.8% (rotated grid) of operations.

**Fix.** `boundaryOnly()` filters `myFill->above !== myFill->below` on the
shortcut paths only, so the input of `combine()` is unchanged.

**Verify.** `RandomizedClippingTest::testAdjacentRingsWithDisjointBoundingBoxes`
and the `grid`/`rotgrid` cases fail on the old code and pass now.

### F2. PHPStan never analysed any file

- [x] Fixed in `45bdeee`.

`phpstan.neon` pointed to `src/Ifnsop/...` (typo) with a `*.php` glob that
PHPStan doesn't expand. It now points to `./src/Ifsnop/MartinezRueda`. The
findings it now reports are listed in [9](#9-remaining-phpstan-findings).

### F3. Single-geometry fixtures were always skipped

- [x] Fixed in `a11d7ab`.

15 polygon-clipping fixtures have a single input geometry, and the E2E test
skipped them. They now run against an empty polygon, which is equivalent to
cleaning the geometry. The cases that differ for known reasons are listed in
`KNOWN_DEVIATIONS` in `tests/PolygonClippingE2ETest.php` and reported as
*incomplete*. See [3](#3-fill-rule-for-overlapping-parts-and-duplicated-rings-is-undecided),
[4](#4-e2e-comparison-uses-the-library-itself-false-passes) and
[L](#known-limitations-accepted).

### F4. GJTools collinear removal deleted the wrong vertex

- [x] Fixed on this branch.

**Problem.** `GJTools::removeColinearPointsFromPolygon` (used by
`geojsonToArray()` on every input ring) checked whether B lies on the line A–C,
but then deleted **A** instead of B. Real corners were lost and the shape of
GeoJSON input changed:
`[[0,0],[1,3],[2,0],[3,0],[4,0],[4,4],[0,4]]` became `…[1,3],[3,0]…`. The corner
(2,0) was removed and the redundant (3,0) was kept. It also compared a
cross product (an area) against the absolute 1e-12, which collapsed small lon/lat
rings: a 1 cm triangle (fixture `very-small-polygon`) turned into 2 points.

**Fix.** B is removed when its *distance* to the line from the last **kept**
vertex to the next vertex is below `Algorithm::TOLERANCE`. This also removes
consecutive collinear points, and the first vertex is checked too, since the ring
is circular. A ring left with fewer than 3 distinct vertices is returned open, so
`closeRing()` still throws the "invalid ring" exception (code 101) as before.

**Verify.** The new `GJToolsTest::testRemoveColinear*` tests (4) fail on the old
code and pass now. A side effect: `thin-vertical-triangle-2` (9e-15 wide) is now
treated as degenerate by GJTools too, which is consistent with
[L](#known-limitations-accepted).

---

## Open

### 1. Scale-dependent tolerances: small lon/lat features give wrong results

- [ ] Open. Fix proposed and tested, not applied.

**Severity:** High for anyone clipping small features (buildings, parcels) in degrees.

**Problem.** Three predicates compare a **cross product** against the absolute
`Algorithm::TOLERANCE = 1e-12`:

- `Point::collinear` (`src/Ifsnop/MartinezRueda/Point.php:31`)
- `Point::pointAboveOrOnLine` (`Point.php:69`)
- the parallel test in `Point::linesIntersect` (`Point.php:120`, `abs($axb) < TOLERANCE`)

A cross product is area-like, so it scales with length². For features L degrees
wide, angles below about `1e-12 / L²` count as degenerate: about 0.6° at
L = 1e-5° (≈1 m), and every angle at L = 1e-6°. Crossing edges are then called
"parallel" and "collinear", the sweep makes inconsistent decisions, and whole
regions are dropped or added. **All three must be fixed together.** Fixing one or
two makes the sweep inconsistent and doesn't help. `between()` (`Point.php:89`)
matters only at the margin.

Position doesn't matter; only feature size does. Results at lon/lat -3.7, 40 or
180 are the same as at 0.

**Evidence** (failure rate, random polygons near Madrid, after F1):

| feature size | 1e-6° | 1e-5° | 1e-4° | 1e-3° | 1e-2° | ≥ 1e-1° |
|---|---|---|---|---|---|---|
| current code | 75% | 16% | 13% | 12.5%* | 6.8%* | ~0 |
| with proposed fix | 0.9–1.3%** | 0 | 0 | 0 | 0 | 0 |

\* small features inside a large operation. \*\* only features of 1e-10 to 1e-9°.

Minimal repro (two triangles about 15 m wide; the union comes back empty):

```
A = [[-3.699848772543026,40.40004447766722],[-3.699861673144788,40.40005385016095],[-3.6998228041022325,40.40011705732156]]
B = [[-3.699906427846182,40.40018978313583],[-3.69998721385193,40.400144433425936],[-3.6998277419565775,40.39995413437151]]
Algorithm::union(A, B) == []      // expected: a 7-vertex polygon
```

Fixture `very-small-polygon` (a 1 cm lon/lat triangle) is also returned empty.

**Proposed fix ("length-type tolerances").** Turn each area test into a distance
test, `|cross| <= TOLERANCE × length`, meaning "the point is within 1e-12°
(≈0.1 µm) of the line". `compare`, `__eq`, `pointKey` and the coordinates are
untouched. The length is the L1 norm, which avoids `sqrt`/`max()`; it is between
1× and 4× the longest side, which is enough for a tolerance.

```diff
--- a/src/Ifsnop/MartinezRueda/Point.php
+++ b/src/Ifsnop/MartinezRueda/Point.php
@@ public static function collinear(Point $point1, Point $point2, Point $point3): bool
         $dx2 = $point2->x - $point3->x;
         $dy2 = $point2->y - $point3->y;
 
-        return abs($dx1 * $dy2 - $dx2 * $dy1) < Algorithm::TOLERANCE;
+        $l = abs($dx1) + abs($dy1) + abs($dx2) + abs($dy2); // bound on the longest side (factor <= 4)
+        return abs($dx1 * $dy2 - $dx2 * $dy1) <= Algorithm::TOLERANCE * $l; // <=: three identical points (l=0) are collinear
     }
@@ public static function pointAboveOrOnLine(Point $point, Point $left, Point $right): bool
         $orient = (($rx - $lx) * ($py - $ly)) - (($ry - $ly) * ($px - $lx));
-        return $orient >= -Algorithm::TOLERANCE;
+        return $orient >= -Algorithm::TOLERANCE * (abs($rx - $lx) + abs($ry - $ly));
     }
@@ public static function between(Point $p, Point $a, Point $b): bool
         $cross = $abx * $apy - $aby * $apx;
-        $epsGeom = max(1.0, $len) * (1.0 * $eps);
+        $epsGeom = $len * $eps; // distance from p to line ab < eps
         if (abs($cross) > $epsGeom) return false;
@@ public static function linesIntersect(Point $a0, Point $a1, Point $b0, Point $b1): ?array
         $axb = $adx * $bdy - $ady * $bdx;
 
-        if (abs($axb) < Algorithm::TOLERANCE) {
+        if (abs($axb) < Algorithm::TOLERANCE * (abs($adx) + abs($ady) + abs($bdx) + abs($bdy))) {
             return null;
         }
```

**Measured with the fix** (on top of F1 and F4):

- Fuzz: 0 failures at every feature size from 1e-5° to 10°, at all lon/lat
  positions tried, out of 51,200 cases.
- `very-small-polygon` is kept with its exact area.
- PHPUnit: all OK.
- Speed: +5–8% (Europe/Australia benchmarks, `tests.php`).
- Continental real data (Europe, Australia) gives identical results.

**Alternatives that were tested and rejected:**

| Alternative | Why rejected |
|---|---|
| Normalizing coordinates (move to origin and scale to unit size, transform back) | Rejected by the maintainer. It also caused new gross errors where the current code is right, and fails 5.75% on small rotated grids. |
| Per-operation tolerance from the bounding box | Same weakness as normalization: a small feature inside a large operation fails. It also breaks 4 existing tests. |
| Exact predicates (Shewchuk orient2d, no epsilon) | Fixes scale, but breaks almost-coincident inexact edges at every scale (8–10% on rotated grids). +9–16% runtime. |

**Risks of the proposed fix:**

- "On the line" now means within 1e-12° of distance whatever the edge length.
  That is slightly looser for edges longer than 1°, and much stricter for short
  edges, which is the point of the fix.
- Outputs keep genuine µm-scale steps that the old test simplified away (see
  `tests.php` #13 below).
- Features smaller than about 1e-9° (0.1 mm) still fail. Don't lower TOLERANCE to
  compensate: the float spacing at 180° is 2.8e-14.

**Side effect to handle.** `tests.php` case #13 fails with the fix. The output
keeps a real 5.4e-10° (≈60 µm) step at `[40.49956209027567,-3.5237618118787744]`
that the old tolerance removed. #13 was added to guard exactly this kind of
almost-collinear case, so update its expected output (or compare with a tolerance)
after checking the new vertex by hand.

**Verify.**

1. `php tests.php`: only #13 changes; check it as described above.
2. `vendor/bin/phpunit`: `very-small-polygon::union` should pass for real once
   [4](#4-e2e-comparison-uses-the-library-itself-false-passes) is fixed.
3. Add a lon/lat small-scale placement to `RandomizedClippingTest::PLACEMENTS`,
   for example `'small' => [-3.7, 1e-5]`. It fails before the fix and passes after.

### 2. Endpoint snapping tolerance is relative to segment length

- [ ] Open. Needs its own study.

**Severity:** High. It affects normal-sized features with typical 7-decimal GeoJSON.

**Problem.** `Point::__calcAlongUsingValue` (`Point.php:154`) snaps an
intersection to a segment endpoint when the intersection lies within
`TOLERANCE_SQRT = 1e-6` (`Algorithm.php:10`) **of the segment's length** from that
endpoint. On a 10 km edge that is 1 cm, which is inconsistent with the 1e-12
tolerances used everywhere else. When a vertex sits a few 1e-8° off a
neighbour's edge (common after rounding to 7 decimals), it gets snapped, the other
segment is split at a point that isn't on it, and the result is grossly wrong.
This doesn't depend on scale; issue 1's fix doesn't touch it.

**Repro** (≈10 km polygons near Madrid, 7 decimals). The union is wrong at 678 of
2,000 random sample points:

```
A = [[[-4.1161297,40.4826806],[-3.8667245,40.6494051],[-3.9778742,40.8156752],[-4.2272794,40.6489507]],
     [[-3.6724398,40.5387099],[-3.4230347,40.7054344],[-3.5341843,40.8717045],[-3.7835895,40.70498]]]
B = [[[-3.8111497,40.5662701],[-3.7280146,40.6218449],[-3.8947392,40.8712501],[-3.9778742,40.8156752]],
     [[-4.088115,40.2608357],[-4.00498,40.3164105],[-4.0605548,40.3995456],[-4.1436899,40.3439707]],
     [[-3.783135,40.3444252],[-3.4505949,40.5667245],[-3.6173194,40.8161297],[-3.9498596,40.5938303]]]
Algorithm::union(A, B)   // compare with an even-odd point-in-polygon test of A ∪ B
```

A computed intersection at `-3.8667245461813025,40.64940515255276` is 4.6e-8 from
A's vertex `-3.8667245,40.6494051` and is snapped onto it.

**Experiment.** With only `TOLERANCE_SQRT = 1e-9`, the repro drops from 678 to
25 wrong points. Combined with issue 1's fix, the rotated-grid fuzz with 7-decimal
rounding goes from 4–10% wrong to 0%. But `tests.php` #8 starts failing, so the
constant can't simply be changed.

**Proposed fix.** Make snapping a *length* test consistent with `compare()`: snap
when the distance from the intersection to the endpoint is below a length
tolerance, instead of comparing the parameter `t` against 1e-6. Then investigate
`tests.php` #8: decide whether its expected output depends on the over-eager
snapping, or whether #8 exposes a case the new rule must handle.

**Verify.** The repro above should give 0 wrong points. Add a `ROUND=7` variant
(round all generated coordinates to 7 decimals) to `RandomizedClippingTest`; the
`rotgrid` generator at 0.1° scale near (-3.7, 40.4) should then pass. Run
`tests.php` and PHPUnit.

### 3. Fill rule for overlapping parts and duplicated rings is undecided

- [ ] Open. Decision needed.

**Severity:** Medium. It only affects invalid input (GeoJSON forbids overlapping
parts), but such input is common in practice.

**Problem.** When a polygon's own rings overlap, the library applies the
**even-odd** rule: the overlap cancels out, and a ring listed twice cancels itself.
It does this **consistently**: union with an empty polygon, difference, intersect
and xor all agree, which `IssuesProgressTest::testIssue03_*Consistently` checks.
polygon-clipping, the source of the E2E fixtures, **unions** the parts of a
MultiPolygon instead. As a result, 8 single-geometry fixtures differ:
`issue-61`, `issue-61-2`, `issue-62`, `issue-62-2`, `issue-68`,
`parallel-lines-with-crossing`, `split-almost-vertical-segment` and
`union-same-shape-multiple-times`. For all 8, our output matches the even-odd
reading of the input at 100% of sample points. They are listed in
`KNOWN_DEVIATIONS`. The rule isn't documented anywhere.

(Correction to an earlier version of this file:
`subtract-same-shape-multiple-times::difference` passes **by rule**. Its expected
output is the subject polygon unchanged, which is what even-odd gives when the
subtracted MultiPolygon is the same triangle twice. It isn't an accident of
`combine()`.)

**Proposed fix.** Decide the rule and document it in the README.

- **If even-odd** (current behaviour, no code change): document it. Then either
  keep the 8 fixtures as known deviations, or replace their expected outputs with
  even-odd results checked by hand.
- **If union of parts (nonzero-like):** phase 1 (`RegionIntersecter`) would have
  to treat each part separately. The simplest way is to `unionMany` the parts
  before the operation. Changing the fill toggle in phase 1 to a winding count is
  the alternative. Either way, decide what happens inside a single
  self-intersecting ring too, and update the oracle in
  `tests/Support/GeometryOracle.php` (`inside()` encodes even-odd), which the
  `selfx` and `grid` generators rely on.

**Verify.** `testIssue03_fillRuleIsDocumented` passes once the README names the
rule. The two consistency tests must keep passing. If you choose union of parts,
the 8 fixtures should leave `KNOWN_DEVIATIONS`.

### 4. E2E comparison uses the library itself (false passes)

- [ ] Open.

**Severity:** Medium. It hides bugs.

**Problem.** When the coordinate comparison fails, `tests/PolygonClippingE2ETest.php`
falls back to `comparePolygons()` (line 99), which computes `xor(got, expected)`
**with the library under test** and checks that the result is empty. Any bug the
library has in `xor` hides the same bug in the operation being tested.

**Proof.** `very-small-polygon::union` currently **passes**, although the library
returns `[]` for it. `xor([], 1-cm triangle)` is also `[]`, because of issue 1, so
the harness concludes they're equal.

**Proposed fix.** Compare with an independent method. Use the point-sampling
oracle in `tests/Support/GeometryOracle.php` (`inside()` on random points inside both
results' bounding box, skipping points near edges), or compare areas plus a
sampled symmetric difference. Keep the exact coordinate comparison as the first,
fast check.

**Verify.** `very-small-polygon::union` must fail (or be reported as a known
deviation) until issue 1 is fixed, and pass after.

### 5. `Polygon::create()` leaves `isInverted` uninitialized

- [ ] Open.

**Problem.** `Polygon::$isInverted` is a typed property without a default, and
only `fillFromArray()` sets it. Using `Polygon::create()` directly as an operand
throws `Error: Typed property ...::$isInverted must not be accessed before initialization`.

**Repro.** `Algorithm::union(Polygon::create(), $p)`.

**Proposed fix.** `public bool $isInverted = false;` in
`src/Ifsnop/MartinezRueda/Polygon.php`, or set it in `__construct()` (line 53).
Then `PolygonClippingE2ETest` can use `Polygon::create()` instead of the
`fillFromArray([])` workaround.

**Verify.** Add a test where an empty polygon is used as either operand of every
operation.

### 6. `*Many()` with a single polygon return the raw input

- [ ] Open.

**Problem.** `unionMany`, `intersectMany`, `xorMany` and `differenceMany`
(`Algorithm.php:662–717`) return the input polygon unchanged when given one
polygon. With two or more, the result is cleaned (self-intersections resolved,
overlapping rings merged by the fill rule). So `unionMany([$p])` and
`unionMany([$p, $empty])` can differ.

**Repro.** `unionMany([two overlapping rings])` returns the two rings as given.

**Proposed fix.** For n = 1 return the cleaned polygon, as `union($p, empty)` does:
`self::polygon(self::makePolySegments(self::boundaryOnly(self::segments($p)->segments), $p->isInverted))`.
Decide first whether "identity" was intentional, for example for speed.

**Verify.** Compare `unionMany([$p])` with `union($p, empty)` on the
single-geometry fixtures.

### 7. GJTools rejects the whole input when one ring is degenerate

- [ ] Open. Decision needed.

**Problem.** `GJTools::geojsonToArray()` throws `InvalidArgumentException` when
any ring has fewer than 3 distinct points after cleaning (code 101 from
`closeRing()`, line 240, or "Anillo degenerado" at line 201). One bad hole makes
the whole GeoJSON unreadable. `GJToolsTest::testDegenerateRingsAndInvalidCoords`
asserts this exception, but its own comment says the degenerate hole "should be
ignored".

**Proposed fix.** Decide the contract. Either skip degenerate rings (optionally
reporting them), and drop a polygon whose exterior ring is degenerate, or keep
throwing and fix the test comment. Skipping is friendlier for real data, which
often contains collapsed slivers.

**Verify.** Update `testDegenerateRingsAndInvalidCoords` to the chosen contract.

### 8. `pointKey()` overflows, and projected coordinates are not supported

- [ ] Open.

**Severity:** Low for lon/lat, High for projected data (UTM, Web Mercator).

**Problem.**

- `Algorithm::pointKey()` (`Algorithm.php:14`) computes `(int)round(x * 1e12)`.
  That exceeds the 64-bit integer range for |x| > 9.2e6 and wraps around, giving
  meaningless keys for the chainer. For example, `(int)round(2e7*1e12)` is
  `1553255926290448384`. Lon/lat never gets there (180 × 1e12 = 1.8e14).
- More generally, a length tolerance of 1e-12 is below the float precision of
  projected coordinates in metres (spacing ≈ 1e-9 at 4.5e6). Fuzzing at UTM-like
  magnitudes (5e5–2e7) gave about 7% wrong results on near-coincident edges.

**Proposed fix.** Key with `sprintf('%.0f', round($x * TOLERANCE_INV))` (no
integer cast), or with a coarser grid. For projected data, use a tolerance
floored by magnitude, such as `max(1e-12, 64 × PHP_FLOAT_EPSILON × max|coord|)`.
Only needed if projected coordinates must be supported; otherwise document that
the library expects lon/lat degrees.

**Verify.** Add a placement such as `[4500000, 1000]` to
`RandomizedClippingTest::PLACEMENTS` (expected to fail today).

### 9. Remaining PHPStan findings

- [ ] Open.

`vendor/bin/phpstan analyse` (level 5) now reports 32 findings:

| Finding | Where | Assessment / proposed fix |
|---|---|---|
| Conditions always true/false (`$lenC >= 2`, `>= 3`, `< 2`, `if` always false) | `Algorithm.php` segmentChainer, lines 242, 275, 299, 303, 317 | Probably false positives: PHPStan assumes chains always have 2 points because it doesn't follow the by-reference `array_unshift`/`$chain[] =`. **Check manually**; if confirmed, add a precise `@var` type or restructure so the length is tracked explicitly. |
| `is_object()` / `is_array()` always true (6) | `GJTools.php` 39, 242, 506, 529, 547, 566 | Redundant checks after type declarations. Remove them, or loosen the parameter types if callers really pass mixed values. |
| `is_array()` on a string is always false | `GJTools::buildGeometryFromCoordinates` (line 578) | The docblock says `@param string` but the method accepts arrays too. Fix the docblock to `string\|array`. |
| 19 "unused method" and 1 "unused constant" | public API (`union`, `intersect`, `unionMany`, `GJTools::geojsonToArray`, ...) and `Algorithm::DEBUG` | False positives from shipmonk/dead-code-detector: the public API is used by callers outside `src/`. Configure the detector to treat public methods as used (or analyse `tests/` too). Remove `DEBUG` if it's really unused. |
| Nullable access (level 8 only) | `Intersecter.php` 328–350, `StatusList.php` 79–100 | Possible null `Node`/`Segment`/`StatusEntry`. Check whether these can really be null in the sweep; if not, add asserts or non-null types. |

Also consider running PHPStan in CI (see [10](#10-repository-and-ci-hygiene)).

### 10. Repository and CI hygiene

- [ ] Open. Only the README table is left.

- [x] **`.gitignore`** with `vendor/` and `.phpunit.result.cache`, committed
  (`822013f`).
- [x] **Broken README link** to `IMPROVEMENTS.md`: now points to this file
  (`80a1595`).
- [ ] **The README's "Mejoras sugeridas" table is outdated.** Current status of
  its 8 items:

  | # | Item | Status |
  |---|---|---|
  | 1 | `pointKey` cache | Done (`Point::$_cachedKey`) |
  | 2 | `$precision` ignored in `ringKey`/`polygonKey` | Done (both pass `$precision`) |
  | 3 | `removeColinearPointsFromPolygon` tolerance | Fixed by [F4](#f4-gjtools-collinear-removal-deleted-the-wrong-vertex) |
  | 4 | `__splitRegionAtDuplicates` O(n²) recursive | Still present (`Algorithm.php:586`); not measured |
  | 5 | `combine()` return type | Done (`: array`) |
  | 6 | `__operate` / `CombinedPolySegments` | `__operate` is gone; the `CombinedPolySegments` class is now unused, so delete it |
  | 7 | `intersectionMany`, `differenceMany`, `xorMany` | Done |
  | 8 | `buildGeometryFromCoordinates` | Accepts arrays now; docblock still wrong (see [9](#9-remaining-phpstan-findings)) |

  Drop the done items from the README table (or drop the table and rely on this
  file).
- [x] **PHPStan in CI**: added as a **non-blocking** step (`continue-on-error: true`)
  that reports its findings as GitHub annotations. When
  [9](#9-remaining-phpstan-findings) is fixed, remove `continue-on-error` so new
  findings fail the build.
- [x] **CI Composer cache key**: `composer.lock` is now committed, so
  `hashFiles('**/composer.lock')` matches. CI also gets reproducible dependency
  versions (PHPUnit 10.5.67, PHPStan 2.3.7, dead-code-detector 0.14.1). Refresh
  the lock with `composer update`.
- [x] **Required PHP extensions** (`dom`, `mbstring`, `xmlwriter`) are documented
  in the README's Testing section.

---

## Known limitations (accepted)

- **Slivers narrower than `TOLERANCE` (1e-12) are dropped as degenerate.**
  Fixtures `nearly-vertical-segment`, `thin-vertical-triangle` and
  `thin-vertical-triangle-2` (widths ≈1e-15, below float precision at those
  coordinates) are expected to differ from polygon-clipping. They are listed in
  `KNOWN_DEVIATIONS`. This is consistent with the rest of the library and is not
  worth fixing.
- **Features smaller than about 1e-9° (0.1 mm)** will still be unreliable after
  issue 1's fix, because they are close to the 1e-12 length tolerance. That size
  has no physical meaning for lon/lat data.
