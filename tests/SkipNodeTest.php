<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", 1);

include_once('autoloader.php');

use PHPUnit\Framework\TestCase;
use Ifsnop\MartinezRueda\Node;
use Ifsnop\MartinezRueda\SkipNode;
use Ifsnop\MartinezRueda\Point;

// ═════════════════════════════════════════════════════════════════════════════
// 3. SKIPNODE
// ─────────────────────────────────────────────────────────────────────────────
// SkipNode es el bloque de construcción de EventList y StatusList.  Errores
// en la inicialización de next/prev provocan bucles infinitos o lecturas de
// nulos en toda la skip-list.
// ═════════════════════════════════════════════════════════════════════════════
class SkipNodeTest extends TestCase
{
    private function dummyNode(): Node
    {
        return new Node(isStart: true, pt: new Point(0, 0));
    }

    public function testConstructorSetsValue(): void
    {
        $n  = $this->dummyNode();
        $sn = new SkipNode($n, 3);
        $this->assertSame($n, $sn->value);
    }

    public function testConstructorSetsHeight(): void
    {
        $sn = new SkipNode(null, 5);
        $this->assertSame(5, $sn->height);
    }

    public function testNextArrayInitializedToNull(): void
    {
        $sn = new SkipNode(null, 4);
        $this->assertCount(4, $sn->next);
        foreach ($sn->next as $slot) {
            $this->assertNull($slot);
        }
    }

    public function testPrevArrayInitializedToNull(): void
    {
        $sn = new SkipNode(null, 4);
        $this->assertCount(4, $sn->prev);
        foreach ($sn->prev as $slot) {
            $this->assertNull($slot);
        }
    }

    public function testHeightOne(): void
    {
        $sn = new SkipNode(null, 1);
        $this->assertCount(1, $sn->next);
        $this->assertCount(1, $sn->prev);
    }

    public function testNullValue(): void
    {
        $sn = new SkipNode(null, 2);
        $this->assertNull($sn->value);
    }

    public function testNextCanBeAssigned(): void
    {
        $sn1 = new SkipNode(null, 2);
        $sn2 = new SkipNode(null, 2);
        $sn1->next[0] = $sn2;
        $this->assertSame($sn2, $sn1->next[0]);
    }
}

