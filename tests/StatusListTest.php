<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('autoloader.php');

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Fill;
use Ifsnop\MartinezRueda\Point;
use Ifsnop\MartinezRueda\Segment;
use Ifsnop\MartinezRueda\StatusList;
use Ifsnop\MartinezRueda\Node;

// ═════════════════════════════════════════════════════════════════════════════
// 4. STATUSLIST
// ─────────────────────────────────────────────────────────────────────────────
// StatusList mantiene el orden de barrido.  Un insert o remove incorrecto
// rompe los invariantes del plano de barrido y provoca fills erróneos en
// todos los segmentos que entran después.
// ═════════════════════════════════════════════════════════════════════════════
class StatusListTest extends TestCase
{
    // Helper: crea un Node+Segment mínimos, listos para StatusList
    private function makeNode(float $x1, float $y1, float $x2, float $y2): Node
    {
        $seg = new Segment(new Point($x1, $y1), new Point($x2, $y2), new Fill(false, true));
        $ev  = new Node(isStart: true, pt: $seg->start, seg: $seg, primary: true);
        return $ev;
    }

    public function testNodeHelperResetsLinks(): void
    {
        $ev       = $this->makeNode(0, 0, 1, 1);
        $ev->next = $ev; // enlace espurio
        $result   = StatusList::node($ev);
        $this->assertNull($result->previous);
        $this->assertNull($result->next);
        $this->assertSame($ev, $result);
    }

    public function testInsertSetsInStatus(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0); // segmento horizontal base para buscar transición
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $this->assertTrue($target->inStatus);
    }

    public function testInsertReturnsSameNode(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $result = $sl->insert($anchor, $target);
        $this->assertSame($target, $result);
    }

    public function testRemoveClearsInStatus(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $sl->remove($target);
        $this->assertFalse($target->inStatus);
    }

    public function testRemoveClearsSnode(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $sl->remove($target);
        $this->assertNull($target->snode);
    }

    public function testExistsReturnsFalseForNull(): void
    {
        $sl = new StatusList();
        $this->assertFalse($sl->exists(null));
    }

    public function testExistsReturnsFalseWhenNotInserted(): void
    {
        $sl = new StatusList();
        $n  = StatusList::node($this->makeNode(0, 0, 1, 1));
        $this->assertFalse($sl->exists($n));
    }

    public function testExistsReturnsTrueAfterInsert(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $this->assertTrue($sl->exists($target));
    }

    public function testExistsReturnsFalseAfterRemove(): void
    {
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $sl->remove($target);
        $this->assertFalse($sl->exists($target));
    }

    public function testRemoveIdempotent(): void
    {
        // Eliminar dos veces no debe lanzar excepción
        $sl     = new StatusList();
        $anchor = $this->makeNode(0, 0, 1, 0);
        $target = StatusList::node($this->makeNode(0, 1, 1, 1));
        $sl->insert($anchor, $target);
        $sl->remove($target);
        $sl->remove($target); // segunda vez → silencioso
        $this->assertFalse($target->inStatus);
    }

    public function testFindTransitionEmptyList(): void
    {
        $sl  = new StatusList();
        $ev  = $this->makeNode(0, 0, 1, 1);
        $tr  = $sl->findTransition($ev);
        $this->assertNull($tr->before);
        $this->assertNull($tr->after);
    }
}
