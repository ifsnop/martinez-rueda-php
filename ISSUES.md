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
| [1](#1-scale-dependent-tolerances-small-lonlat-features-give-wrong-results) | Scale-dependent tolerances: small lon/lat features give wrong results | High | Fixed (this branch) |
| [2](#2-endpoint-snapping-tolerance-is-relative-to-segment-length) | Endpoint snapping tolerance is relative to segment length | High | Fixed (this branch) |
| [3](#3-fill-rule-for-overlapping-parts-and-duplicated-rings-is-undecided) | Fill rule for overlapping parts / duplicated rings undecided | Medium | Open, decision needed (behaviour is consistent) |
| [4](#4-e2e-comparison-uses-the-library-itself-false-passes) | E2E comparison uses the library itself (false passes) | Medium | Fixed (this branch) |
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

- [x] Fixed on this branch: the patch below is applied to `Point.php`.

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

**Side effect (handled).** With the fix, `tests.php` case #13 keeps one more
vertex, `[40.49956209027567,-3.5237618118787744]`. Checked by hand: it is exactly
where B's top edge crosses A's left edge, 5.4e-10° (≈60 µm) below A's top-left
corner. The old area test called the three points collinear
(|cross| = 3.8e-13 < 1e-12) and dropped it, cutting off a sliver of about
1.9e-13 deg². The expected output of #13 now includes the vertex. It fails with
the old `Point.php`, so #13 also guards this fix.

**Verification done.**

1. `php tests.php`: 20/20, with #13 updated as above. Runtime 0.65 s vs 0.62 s
   (+5%).
2. `vendor/bin/phpunit`: OK. `very-small-polygon::union` passes for real and is
   no longer in `KNOWN_DEVIATIONS`.
3. `RandomizedClippingTest::PLACEMENTS` has a new `'metre' => [-3.7, 1e-5]`
   placement (features of 1–5 m): 14 cases, 5 of them fail with the old code.
   A deeper pass (`FUZZ_ITERS=100 FUZZ_SEEDS=3`) passes everywhere.
4. Progress suite: the three `testIssue01_*` checks pass.

### 2. Endpoint snapping tolerance is relative to segment length

- [x] Fixed on this branch: rounded inputs are normalized by default (`InputNormalizer`).

**Severity:** High. It affected normal-sized features with typical 7-decimal GeoJSON.

**What the problem really was.** When the sweep finds edge A crossing edge B "almost
at" B's endpoint, it doesn't split A at the true crossing. It assumes a T-junction
and splits A **at B's endpoint** (`eventDivide($ev1, $b2)` in
`Intersecter::checkIntersection`). "Almost" means within
`TOLERANCE_SQRT = 1e-6` × |B| (`Point::__calcAlongUsingValue`), which is 3 cm on a
30 km edge.

- **Origin of the 1e-6.** The initial port (April 2025) used polybooljs's single
  epsilon, 1e-10, for this. On 2026-06-08 `TOLERANCE` went to 1e-12, but this test was
  switched to `TOLERANCE_SQRT` (1e-6), "the square root of TOLERANCE, for squared
  values". The value it compares isn't squared: it's a dimensionless position along
  the segment. The snap became 10,000× looser than the original.
- **The repro case.** Three snaps were applied to endpoints 1.4e-8–4.6e-8° (1.5–5 mm)
  off the other edge: shared corners that rounding to 7 decimals had separated. Bending
  an edge without re-checking its relations with other segments leaves the sweep
  inconsistent, and a third of the union was lost.
- **The snap is a symptom, not the root cause.** With a distance-based snap
  (< 1e-12°, consistent with item 1) the repro passes, but other rounded grids still
  fail (10–17 operations of 400), and an exact-data case that passes today breaks:
  the snap also absorbs the floating-point error of computed crossings. No snap value
  removes the failures; changing it only moves them.
- **The real ingredient** is near-degenerate geometry at a scale larger than the
  tolerance. The minimal case: three edges that were one straight line before rounding
  to 7 decimals, now 0.3–2.4 mm apart at an angle of 1e-8 rad. Exact data: 0 failures.
  The same data rounded to 7 decimals (how neighbouring parcels arrive in GeoJSON): up
  to 5% of operations grossly wrong.

**Other libraries** (same 802 polygon pairs, each checked against its own fill rule):

| Library | Approach | Rounded grids, 7 decimals |
|---|---|---|
| polybooljs 1.2.2 (origin of this port) | parametric snap, eps 1e-10 | 14–46% failures, mostly exceptions |
| martinez-polygon-clipping 0.9.1 | exact `orient2d` + 4-ulp snap | 1–2%, and 4.5% even on exact data |
| polygon-clipping 0.15.7 | T-junction only if on the line to machine precision; all crossings rounded to previously seen coordinates | 0–1% (exceptions) |
| Clipper 6.4.2 | integer coordinates; every crossing rounded to the grid | 0% |
| jsts 2.12.1 (JTS) | snapping with tolerance, snap rounding if topology is inconsistent | 0% wrong (some exceptions) |
| this library before the fix | parametric snap, 1e-6 | 0–4.75% |

The literature agrees. Epsilon tests inside predicates give contradictory decisions
that no epsilon can fix (Kettner et al. 2004/2008). The accepted remedy is consistent,
global rounding: snap rounding (Greene; Hobby 1999; Halperin & Packer's iterated snap
rounding), or Milenkovic's "data normalization" (1988): before operating, move vertices
within ε of an edge onto it and split the edge there (*vertex shifting* + *edge
cracking*), until nothing changes. polybooljs had the same debate (PR #8, 2017, scale
epsilon by segment length; never merged).

**Fix.** `InputNormalizer` applies Milenkovic's normalization to all operands together
before every public operation (`union`, `intersect`, `difference`, `xoring`, `*Many`):
vertices closer than the tolerance are unified, and edges passing closer than the
tolerance to a vertex are split at it, repeated until stable. No coordinate moves more
than the tolerance. Grid indexes keep it near-linear (~30 ms for Europe + a shifted
copy). New optional parameter `?float $precision` on all those operations:

- `null` (default): **automatic**. If every coordinate has at most 5–9 decimals, the
  tolerance is that quantum (1e-7 for 7 decimals). Integers or full-precision data are
  not normalized: there's no way to tell "exact integers" from "rounded to integers",
  and tolerance 1 would destroy integer geometry (tested: it breaks
  `issue-36`, `issue-38` and `multipolys-with-disjoint-polys`).
- `0.0`: disabled (previous behaviour).
- `> 0`: explicit tolerance in coordinate units.

The low-level API (`segments()`, `combine()`, `polygon()`) doesn't normalize.

**Alternatives measured and rejected:**

| Alternative | Result |
|---|---|
| Lower the sweep snap to 1e-8 ("phase 0") | All existing tests pass and the repro is fixed, but rounded-grid failures only drop 78 → 32 of 2,400, and get worse at 0.001° (0 → 6). Not applied. |
| Distance-based sweep snap (< 1e-12°) | Repro fixed, but an exact-data case breaks. |
| Fixed 1 mm floor (normalize with 1e-8) | Not enough for 7 decimals: rounding moves vertices up to ~8 mm (6–13 failures of 300 remain). |
| Round inputs to a 1 mm grid | Makes it worse: exact data goes from 0 to 16 failures of 300. Rounding is what creates the problem. |
| Validate and retry (JTS style), integers / snap rounding (Clipper style) | Much larger changes; not needed given the results below. |

**Verification done.**

- Strategy matrix (rounded grids with 6, 7 and 9 decimals, exact grids, tiles,
  1 m stars; 300 operations each): automatic normalization gives **0 failures
  everywhere**, against 2–23 before.
- `php tests.php`: 20/20 (#13 has 15-decimal coordinates, so it isn't normalized).
- `vendor/bin/phpunit`: 308 tests OK. New: `tests/InputNormalizerTest.php` (14 tests:
  detection, unification, edge cracking, explicit and disabled precision, `*Many`) and a
  `geojson` placement in `RandomizedClippingTest` (rounded to 7 decimals; 2 of its 14
  cases fail with normalization disabled). Deep pass (`FUZZ_ITERS=60 FUZZ_SEEDS=4`): OK.
- Progress suite: both `testIssue02_*` checks pass.
- Speed: +3–9% when normalization applies (Europe 0.584 → 0.604–0.635 s); detection alone
  is 7 ms on Europe, whose 12-decimal data isn't normalized. `tests.php` shows no
  measurable difference.
- PHPStan: no new findings (32, as before).

**Remaining limitations.**

- Full-precision data (≥ 10 decimals) with near-degenerate geometry at the mm scale is
  not normalized automatically (for example, reprojected data). Pass an explicit
  `$precision` in that case.
- Mixed-precision inputs (some coordinates with 7 decimals, others with 15): detection
  sees 15 and doesn't normalize, so the behaviour is the same as before. Pass an
  explicit `$precision`.
- With normalization, features smaller than the data quantum collapse (e.g. a 1 mm
  triangle in 7-decimal data); they aren't representable at that precision anyway.
- The sweep's `TOLERANCE_SQRT` (1e-6) stays as it is; with normalization its value no
  longer affects the rounded-data results. Its comment and the README now describe what
  it really is.

**Sources.**
[Hobby 1999](https://www.nokia.com/bell-labs/publications-and-media/publications/practical-segment-intersection-with-finite-precision-output) ·
[Iterated snap rounding](https://www.cgl.cs.tau.ac.il/projects/iterated-snap-rounding/) ·
[Kettner et al.](https://cs.nyu.edu/~exact/doc/classroom.pdf) ·
[Milenkovic 1988](https://www.bohrium.com/paper-details/verifiable-implementations-of-geometric-algorithms-using-finite-precision-arithmetic/812418101453783041-2508) ·
[JTS OverlayNGRobust](https://locationtech.github.io/jts/javadoc/org/locationtech/jts/operation/overlayng/OverlayNGRobust.html) ·
[Clipper2 robustness](https://angusj.com/clipper2/Docs/Robustness.htm) ·
[polybooljs PR #8](https://gh.ali360.cloudns.ch/velipso/polybooljs/pull/8) ·
[martinez issue #155](https://www.github.com/w8r/martinez/issues/155)

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

- [x] Fixed on this branch.

**Severity:** Medium. It hid bugs.

**Problems found** (three, not one):

1. `PolygonClippingE2ETest::comparePolygons()` decided whether two results were equal
   with `xor(got, expected)` **computed by the library under test**, so a bug in `xor`
   hid bugs in every operation. Before item 1 was fixed, `very-small-polygon::union`
   passed although the library returned `[]`.
2. The "exact" comparison before it, `compareCoordinates()`, accepted differences of
   **0.1 per coordinate** (`almostEqual`), which is 11 km in lon/lat. The expected
   output was also loaded through `GJTools`, which is part of the library (the F4 bug
   affected it).
3. The continent checks in `tests.php` only compared the **number of vertices**
   (`numPoints == 1340`). Also, `$fail` was reset after them, so a continent FAIL never
   changed the exit code or CI.

**Fix.**

- `tests/Support/RegionComparator.php`: an independent comparison. Every vertex of each
  region lies within δ of the other's boundary (both ways; finds missing or extra
  pieces however small or far, lost holes, moved vertices). Same even-odd interior at
  2,000 deterministic sample points away from the boundaries (finds swapped insides).
  δ = 1e-10 × coordinate magnitude, instead of 0.1. Ring order, start vertex,
  orientation and extra collinear vertices don't matter. Edge lookups use a grid index
  (`tests/Support/BoundaryIndex.php`).
- The E2E harness reads the expected GeoJSON directly (no `GJTools`) and compares it
  with the library's raw output. `compareCoordinates()` and `almostEqual()` were
  removed.
- `tests.php` continents: checked with the point oracle, plus an exact boundary check
  for xor (`RegionComparator::xorBoundariesConsistent`: the boundary of A xor B is the
  union of both boundaries). `$fail` is initialized before them. The vertex count is
  now only printed.
- `GeometryOracle::countWrong()` only measures edge distances for points that
  disagree, which makes the oracle cheap on polygons with thousands of vertices.

**Verification done.**

- All 116 E2E cases still pass with the strict comparison (11 incomplete, as before),
  so the weak comparison wasn't hiding further failures.
- With the `Point.php` from before item 1, `very-small-polygon::union` now **fails**
  ("region is empty"), where it used to pass.
- Injected errors in the library output:

  | Injected error | Old harness | New harness |
  |---|---|---|
  | One vertex moved 1e-7 | 8 of 105 caught | 88 of 105 |
  | Tiny extra piece far away | 31 of 105 caught | 98 of 105 |

  The remaining cases have empty results, so there's nothing to inject into.
- A missing ring (an island) in the continent xor: `tests.php` now FAILs for both
  Australia and Europe and exits with 1. The point oracle alone missed it.
- `RegionComparatorTest`: 8 tests of the comparator itself.
- Cost: `tests.php` 0.65 s → 1.3 s, because of the continent checks.

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
- [x] **README documented methods that don't exist** (`Algorithm::intersection`,
  `Algorithm::differenceRev`) and described `TOLERANCE`/`TOLERANCE_SQRT` wrongly
  ("≈ 1 mm", "for squared values"). Fixed together with item 2; the API section now
  lists the real methods, including the `*Many` ones.

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
