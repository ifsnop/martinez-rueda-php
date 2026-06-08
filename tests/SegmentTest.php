<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('autoloader.php');

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Fill;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Segment;

// ═════════════════════════════════════════════════════════════════════════════
// 2. SEGMENT
// ─────────────────────────────────────────────────────────────────────────────
// Segment mantiene el bounding-box cacheado que usa el rechazo rápido AABB
// de Intersecter.  Si recalcBounds() falla o los límites quedan inconsistentes
// cualquier intersección puede ser descartada sin comprobarse.
// ═════════════════════════════════════════════════════════════════════════════
class SegmentTest extends TestCase
{
    private function seg(float $x1, float $y1, float $x2, float $y2): Segment
    {
        return new Segment(new Point($x1, $y1), new Point($x2, $y2));
    }

    // ── Bounding-box inicial ─────────────────────────────────────────────────

    public function testBoundsHorizontal(): void
    {
        $s = $this->seg(1.0, 2.0, 4.0, 2.0);
        $this->assertSame(1.0, $s->minX);
        $this->assertSame(4.0, $s->maxX);
        $this->assertSame(2.0, $s->minY);
        $this->assertSame(2.0, $s->maxY);
    }

    public function testBoundsWhenStartIsRight(): void
    {
        // start más a la derecha que end → minX/maxX invertidos correctamente
        $s = $this->seg(5.0, 0.0, 1.0, 3.0);
        $this->assertSame(1.0, $s->minX);
        $this->assertSame(5.0, $s->maxX);
        $this->assertSame(0.0, $s->minY);
        $this->assertSame(3.0, $s->maxY);
    }

    public function testBoundsPoint(): void
    {
        // Segmento de longitud 0 (caso límite)
        $s = $this->seg(3.0, 3.0, 3.0, 3.0);
        $this->assertSame(3.0, $s->minX);
        $this->assertSame(3.0, $s->maxX);
    }

    // ── len2 ─────────────────────────────────────────────────────────────────

    public function testLen2(): void
    {
        $s = $this->seg(0.0, 0.0, 3.0, 4.0);
        $this->assertEqualsWithDelta(25.0, $s->len2, 1e-12);
    }

    // ── recalcBounds tras modificación ──────────────────────────────────────

    public function testRecalcBoundsAfterMutation(): void
    {
        $s      = $this->seg(0.0, 0.0, 1.0, 1.0);
        $s->end = new Point(10.0, 10.0);
        $s->recalcBounds();
        $this->assertSame(10.0, $s->maxX);
        $this->assertSame(10.0, $s->maxY);
    }

    // ── Fill opcional ────────────────────────────────────────────────────────

    public function testFillDefaultNull(): void
    {
        $s = $this->seg(0.0, 0.0, 1.0, 1.0);
        $this->assertNull($s->otherFill);
    }

    public function testFillProvided(): void
    {
        $fill = new Fill(true, false);
        $s    = new Segment(new Point(0, 0), new Point(1, 1), $fill);
        $this->assertTrue($s->myFill->below);
        $this->assertFalse($s->myFill->above);
    }

    // ── __toString ───────────────────────────────────────────────────────────

    public function testToString(): void
    {
        $s = $this->seg(1.0, 2.0, 3.0, 4.0);
        $this->assertStringContainsString('1', (string)$s);
        $this->assertStringContainsString('3', (string)$s);
    }
}
