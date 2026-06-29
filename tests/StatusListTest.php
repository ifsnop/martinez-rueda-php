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
use Ifsnop\MartinezRueda\StatusEntry;
use Ifsnop\MartinezRueda\Node;

// ═════════════════════════════════════════════════════════════════════════════
// 4. STATUSLIST
// ─────────────────────────────────────────────────────────────────────────────
// StatusList mantiene el orden de barrido. Un insert o remove incorrecto
// rompe los invariantes del plano de barrido y provoca fills erróneos en
// todos los segmentos que entran después.
//
// Cambios respecto a la versión anterior del algoritmo:
//   · StatusList::node() ha sido ELIMINADO. Los eventos (Node) se envuelven
//     internamente en StatusEntry al insertar; el llamador nunca crea ni
//     manipula StatusEntry directamente.
//   · insert(Node $ev): StatusEntry  — devuelve StatusEntry, no Node.
//   · remove(StatusEntry $entry)     — recibe la StatusEntry devuelta por insert.
//   · exists(?StatusEntry $entry)    — recibe StatusEntry|null.
//   · Node->inStatus ha sido ELIMINADO; usar $entry->snode !== null o exists().
//   · findTransition() devuelve array [?StatusEntry, ?StatusEntry]
//     en lugar de un objeto Transition.
// ═════════════════════════════════════════════════════════════════════════════
class StatusListTest extends TestCase
{
    // Helper: crea un Node+Segment mínimos, listos para insertar en StatusList.
    // Ya no se llama a StatusList::node() — ese método no existe.
    private function makeNode(float $x1, float $y1, float $x2, float $y2): Node
    {
        $seg = new Segment(new Point($x1, $y1), new Point($x2, $y2), new Fill(false, true));
        return new Node(isStart: true, pt: $seg->start, seg: $seg, primary: true);
    }

    // ── insert devuelve StatusEntry ──────────────────────────────────────────

    public function testInsertReturnsStatusEntry(): void
    {
        $sl     = new StatusList();
        $ev     = $this->makeNode(0, 1, 1, 1);
        $entry  = $sl->insert($ev);
        $this->assertInstanceOf(StatusEntry::class, $entry);
    }

    public function testInsertEntryReferencesOriginalEvent(): void
    {
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $this->assertSame($ev, $entry->ev);
    }

    public function testInsertSetsSnodeOnEntry(): void
    {
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $this->assertNotNull($entry->snode);
    }

    // ── exists ───────────────────────────────────────────────────────────────

    public function testExistsReturnsFalseForNull(): void
    {
        $sl = new StatusList();
        $this->assertFalse($sl->exists(null));
    }

    public function testExistsReturnsTrueAfterInsert(): void
    {
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $this->assertTrue($sl->exists($entry));
    }

    public function testExistsReturnsFalseAfterRemove(): void
    {
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $sl->remove($entry);
        $this->assertFalse($sl->exists($entry));
    }

    // ── remove ───────────────────────────────────────────────────────────────

    public function testRemoveClearsSnode(): void
    {
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $sl->remove($entry);
        $this->assertNull($entry->snode);
    }

    public function testRemoveIdempotent(): void
    {
        // Eliminar dos veces no debe lanzar excepción
        $sl    = new StatusList();
        $ev    = $this->makeNode(0, 1, 1, 1);
        $entry = $sl->insert($ev);
        $sl->remove($entry);
        $sl->remove($entry); // segunda vez → silencioso
        $this->assertNull($entry->snode);
    }

    // ── Node no queda contaminado por la StatusList ──────────────────────────
    // previous/next del Node pertenecen a la EventList y NO deben ser
    // modificados por la StatusList (fueron la causa del bug de bucle infinito).

    public function testInsertDoesNotTouchNodePreviousNext(): void
    {
        $sl  = new StatusList();
        $ev  = $this->makeNode(0, 1, 1, 1);
        // Establecer valores centinela en el Node
        $sentinel = new Node();
        $ev->previous = $sentinel;
        $ev->next     = $sentinel;

        $sl->insert($ev);

        // La StatusList NO debe haber tocado los enlaces del Node
        $this->assertSame($sentinel, $ev->previous);
        $this->assertSame($sentinel, $ev->next);
    }

    public function testRemoveDoesNotTouchNodePreviousNext(): void
    {
        $sl       = new StatusList();
        $ev       = $this->makeNode(0, 1, 1, 1);
        $sentinel = new Node();
        $ev->previous = $sentinel;
        $ev->next     = $sentinel;

        $entry = $sl->insert($ev);
        $sl->remove($entry);

        $this->assertSame($sentinel, $ev->previous);
        $this->assertSame($sentinel, $ev->next);
    }

    // ── StatusEntry tiene sus propios enlaces previous/next ──────────────────

    public function testEntryLinksAreIndependentFromNode(): void
    {
        $sl     = new StatusList();
        $ev1    = $this->makeNode(0, 4, 1, 4); // más arriba en Y → va primero en StatusList
        $ev2    = $this->makeNode(0, 2, 1, 2);
        $ev3    = $this->makeNode(0, 0, 1, 0); // más abajo en Y → va último

        $e1 = $sl->insert($ev1);
        $e2 = $sl->insert($ev2);
        $e3 = $sl->insert($ev3);

        // Los previous/next son de StatusEntry, no de Node
        $this->assertInstanceOf(StatusEntry::class, $e2->previous);
        $this->assertInstanceOf(StatusEntry::class, $e2->next);
        // Los Node no tienen sus previous/next tocados
        $this->assertNull($ev2->previous);
        $this->assertNull($ev2->next);
    }

    // ── findTransition devuelve array [?StatusEntry, ?StatusEntry] ──────────

    public function testFindTransitionEmptyListReturnsNulls(): void
    {
        $sl = new StatusList();
        $ev = $this->makeNode(0, 0, 1, 1);
        [$before, $after] = $sl->findTransition($ev);
        $this->assertNull($before);
        $this->assertNull($after);
    }

    public function testFindTransitionReturnsStatusEntries(): void
    {
        $sl  = new StatusList();
        $ev1 = $this->makeNode(0, 4, 1, 4); // arriba
        $ev3 = $this->makeNode(0, 0, 1, 0); // abajo
        $sl->insert($ev1);
        $sl->insert($ev3);

        // Buscar transición para un segmento a Y=2 (entre los dos)
        $query         = $this->makeNode(0, 2, 1, 2);
        [$before, $after] = $sl->findTransition($query);

        $this->assertInstanceOf(StatusEntry::class, $before);
        $this->assertInstanceOf(StatusEntry::class, $after);
    }

    public function testFindTransitionBeforeIsAboveQuery(): void
    {
        $sl  = new StatusList();
        $ev1 = $this->makeNode(0, 4, 1, 4); // arriba (before = above)
        $ev3 = $this->makeNode(0, 0, 1, 0); // abajo  (after  = below)
        $sl->insert($ev1);
        $sl->insert($ev3);

        $query        = $this->makeNode(0, 2, 1, 2);
        [$before, $after] = $sl->findTransition($query);

        // before->ev debe ser el segmento que está POR ENCIMA (Y=4)
        $this->assertEquals(4.0, $before->ev->seg->start->y);
        // after->ev debe ser el segmento que está POR DEBAJO (Y=0)
        $this->assertEquals(0.0, $after->ev->seg->start->y);
    }

    // ── Varios inserts mantienen orden correcto ──────────────────────────────

    public function testMultipleInsertsAllExist(): void
    {
        $sl      = new StatusList();
        $entries = [];
        foreach ([4.0, 2.0, 0.0, 6.0] as $y) {
            $ev        = $this->makeNode(0, $y, 1, $y);
            $entries[] = $sl->insert($ev);
        }
        foreach ($entries as $entry) {
            $this->assertTrue($sl->exists($entry));
        }
    }

    public function testRemoveMiddleEntryUpdatesNeighbourLinks(): void
    {
        $sl = new StatusList();
        $e1 = $sl->insert($this->makeNode(0, 4, 1, 4));
        $e2 = $sl->insert($this->makeNode(0, 2, 1, 2));
        $e3 = $sl->insert($this->makeNode(0, 0, 1, 0));

        $sl->remove($e2);

        // e2 ya no existe; e1 y e3 sí
        $this->assertFalse($sl->exists($e2));
        $this->assertTrue($sl->exists($e1));
        $this->assertTrue($sl->exists($e3));
    }
}
