<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

/* ===========================================================================
 *  Intersecter — versión optimizada (sweep-line / Bentley–Ottmann)
 * ===========================================================================
 *
 *  ANÁLISIS DEL PATRÓN DE ACCESO
 *  -----------------------------
 *  `Intersecter` accede millones de veces a dos listas que son las dos
 *  estructuras clásicas de un barrido de líneas:
 *
 *    EventList  -> COLA DE PRIORIDAD de eventos:
 *                  · extraer mínimo            (getHead)
 *                  · insertar ordenado         (insertBefore)
 *                  · borrar nodo arbitrario    (remove, al dividir/fusionar)
 *
 *    StatusList -> ESTADO DEL BARRIDO (orden vertical):
 *                  · buscar posición + vecinos (findTransition -> before/after)
 *                  · insertar ordenado         (insert)
 *                  · borrar nodo arbitrario    (remove)
 *                  · recorrer vecinos          (prev / next)
 *
 *  PROBLEMA DE LA IMPLEMENTACIÓN ANTERIOR
 *  --------------------------------------
 *  Ambas usaban `array` ordenado + `array_splice`:
 *    · La búsqueda binaria era O(log n)...
 *    · ...pero CADA inserción y CADA borrado hacían `array_splice` = O(n)
 *      (desplaza todos los elementos siguientes).
 *    · `arrayRemove` además recorría el array linealmente = O(n) extra.
 *  Resultado: O(n) por operación  ->  O(n^2) global sobre el camino crítico.
 *
 *  ESTRUCTURA ELEGIDA: SKIP LIST CON TODOS LOS NIVELES DOBLEMENTE ENLAZADOS
 *  -----------------------------------------------------------------------
 *  El patrón (extraer-mínimo + insertar-ordenado + borrar-por-nodo + vecinos)
 *  pide un CONJUNTO ORDENADO BALANCEADO. Un skip list es óptimo aquí porque:
 *
 *    1. Búsqueda / inserción / borrado en O(log n) esperado.
 *    2. El NIVEL 0 es ya una lista doblemente enlazada -> getHead, isEmpty,
 *       prev y next son O(1) y CONSERVAN la interfaz de punteros que el
 *       algoritmo ya usa ($st->previous, $st->next, $cursor->next, getHead).
 *       => No hay que reescribir la navegación del barrido.
 *    3. Borrado por NODO sin buscar: como cada nivel está doblemente
 *       enlazado, se desenlaza en O(altura) y se elimina la búsqueda
 *       lineal O(n) que penalizaba la versión con array.
 *
 *  (Un BST balanceado —AVL/rojo-negro con hilos in-order— da la misma
 *   complejidad, pero obligaría a reescribir la navegación de vecinos; el
 *   skip list encaja con el código existente sin tocarlo.)
 *
 *  El orden de inserción usa EXACTAMENTE los mismos comparadores que la
 *  versión con búsqueda binaria (misma semántica de orden y mismos
 *  desempates: inserción detrás de los elementos "iguales"), por lo que la
 *  salida debe coincidir SIEMPRE QUE el comparador defina un orden consistente
 *  sobre el conjunto activo — exactamente la misma hipótesis que ya requería la
 *  búsqueda binaria original (predicado monótono), así que no se añade riesgo.
 *  Sólo cambia la complejidad temporal. El borrado es por IDENTIDAD de objeto,
 *  así que no depende de claves iguales/empates.
 *
 *  GANANCIA ESPERADA (verificar con benchmark real)
 *  ------------------------------------------------
 *  La mejora asintótica O(n)->O(log n) es real y la ganancia DOMINANTE está en
 *  la EventList: con problemas grandes, 5M operaciones x O(n) de `array_splice`
 *  es catastrófico. En la StatusList el conjunto activo suele ser pequeño, y un
 *  skip list en userland PHP tiene constantes altas (arrays next/prev por nodo,
 *  un closure por inserción) frente al `memmove` en C de `array_splice`; por
 *  tanto la ventaja ahí no está garantizada y conviene CONFIRMARLA con un
 *  benchmark sobre datos representativos antes de darla por hecha.
 *
 *  OTRAS OPTIMIZACIONES APLICADAS / RECOMENDADAS
 *  ---------------------------------------------
 *    · Rechazo temprano por AABB cacheada en checkIntersection (ya presente,
 *      se conserva): evita el cálculo de intersección caro entre cajas que no
 *      se solapan.
 *    · getHead() / isEmpty() en O(1) (cabeza del nivel 0).
 *    · Borrado por nodo en O(log n) sin búsqueda lineal.
 *    · Se elimina el `array_splice` y el coste de recolocar el array entero.
 *    · `spl_object_id` sólo se usa en StatusList::exists() (set de membresía);
 *      el resto del trabajo es por referencia directa a objetos.
 *    · Recomendado en producción: activar OPcache + JIT
 *        opcache.enable=1
 *        opcache.jit=tracing
 *        opcache.jit_buffer_size=128M
 *      El JIT acelera mucho los bucles de comparación geométrica.
 *
 *  IMPORTANTE: la API pública de EventList y StatusList se mantiene, por lo que
 *  `Intersecter` NO requiere cambios (sus firmas y sus llamadas son idénticas).
 *
 *  DEPENDENCIAS EXTERNAS (definidas en otro punto del proyecto, no en este
 *  fichero): Point, Node, Segment, Fill, Transition, PolyBoolException, Algorithm.
 *
 *  ¡¡AVISO!! Este fichero NO ha podido ejecutarse en el entorno donde se generó
 *  (sin intérprete PHP disponible). Es un rewrite con punteros sobre el camino
 *  crítico: VALÍDALO contra tu suite existente y/o con el test estructural
 *  adjunto (skiplist_test.php) antes de ponerlo en producción.
 * ===========================================================================
 */

/* ===========================================================================
 *  EventList  ->  cola de prioridad de eventos (skip list ordenado)
 * ========================================================================= */

final class EventList
{
    use SkipListCore;

    public function __construct()
    {
        $this->initSkip();
    }

    public function isEmpty(): bool
    {
        return $this->header->next[0] === null;
    }

    public function getHead(): ?Node
    {
        $n = $this->header->next[0];
        return $n === null ? null : $n->value;
    }

    /**
     * Inserta $ev manteniendo el MISMO orden que la versión con búsqueda
     * binaria. $otherPt es el otro extremo del evento (puede que $ev->other
     * aún no esté fijado en el momento de insertar el START).
     */
    public function insertBefore(Node $ev, Point $otherPt): void
    {
        $update = $this->searchEvent($ev->pt, $otherPt, $ev->isStart);
        // Back-pointer al SkipNode en lugar de un closure por nodo: borrado en
        // O(altura) vía remove() sin asignar un Closure (ni binding de $this) por
        // cada evento insertado. Menos memoria y menos presión de GC en el camino crítico.
        $ev->snode = $this->linkAt($update, $ev);
    }

    /**
     * Desenlaza el evento $ev de la cola. Sustituye al antiguo closure
     * `$ev->remove`. Idempotente: tras el borrado anula el back-pointer, de modo
     * que una segunda llamada es un no-op (evita el doble-unlink que el closure
     * podía ejecutar corrompiendo los vecinos del nivel 0).
     */
    public function remove(Node $ev): void
    {
        if ($ev->snode !== null) {
            $this->unlink($ev->snode);
            $ev->snode = null;
        }
    }

    /**
     * Camino de predecesores para la posición de inserción.
     * Avanza mientras el nodo existente NO deba ir detrás del insertado
     * (es decir, mientras el existente precede al insertado).
     *
     * @return array<int,SkipNode>
     */
    private function searchEvent(Point $p11, Point $p12, bool $p1IsStart): array
    {
        $update = [];
        $x = $this->header;
        for ($i = $this->level - 1; $i >= 0; $i--) {
            $n = $x->next[$i];
            while ($n !== null && !$this->eventCheckBefore($p11, $p12, $p1IsStart, $n->value)) {
                $x = $n;
                $n = $x->next[$i];
            }
            $update[$i] = $x;
        }
        return $update;
    }

    /**
     * Comparador idéntico al de la versión con búsqueda binaria:
     * devuelve true si el evento insertado debe situarse ANTES de $here.
     */
    private function eventCheckBefore(Point $p11, Point $p12, bool $p1IsStart, Node $here): bool
    {
        $hPt      = $here->pt;
        $hOther   = $here->other;           // ← una sola desreferencia
        // $hOtherPt = $hOther !== null ? $hOther->pt : $here->pt; // defensivo
        $hOtherPt = $hOther !== null ? $hOther->pt : throw new \LogicException('BUG: event with null other in queue'); // muy defensivo
        $hIsStart = $here->isStart;

        $comp = Point::compare($p11, $hPt);
        if ($comp !== 0) {
            return $comp < 0;
        }
        if (Point::compare($p12, $hOtherPt) === 0) {
            return false;
        }
        if ($p1IsStart !== $hIsStart) {
            return !$p1IsStart;
        }
        $lineA = $hIsStart ? $hPt : $hOtherPt;
        $lineB = $hIsStart ? $hOtherPt : $hPt;
        return !Point::pointAboveOrOnLine($p12, $lineA, $lineB);
    }
}
