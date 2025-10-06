<?php

namespace Ifsnop\MartinezRueda;

class LinkedList {
    private Node $root;

    public function __construct() {
        $this->root = new Node(isRoot: true);
    }

    /**
     * Verifica si un nodo existe y es válido (no es null ni root)
     */
    public function exists(?Node $node): bool {
        return $node !== null && $node !== $this->root;
    }

    /**
     * Verifica si la lista está vacía
     */
    public function isEmpty(): bool {
        return $this->root->next === null;
    }

    /**
     * Obtiene el primer nodo de la lista (cabeza)
     */
    public function getHead(): ?Node {
        return $this->root->next;
    }

    /**
     * Inserta un nodo antes del primer nodo que cumpla la condición
     * Si ningún nodo cumple la condición, lo inserta al final
     *
     * @param Node $node Nodo a insertar
     * @param callable $check Función que recibe un Node y retorna bool
     */
    public function insertBefore(Node $node, callable $check): void {
        if (Algorithm::DEBUG) {
            print __METHOD__ . PHP_EOL;
        }

        $previous = $this->root;
        $current = $this->root->next;

        // Buscar la posición donde insertar
        while ($current !== null) {
            if ($check($current)) {
                // Insertar antes de current
                $this->linkNodes($previous, $node, $current);
                return;
            }
            $previous = $current;
            $current = $current->next;
        }

        // No se encontró posición, insertar al final
        $this->linkNodes($previous, $node, null);
    }

    /**
     * Encuentra la transición entre dos nodos según una condición
     * Retorna un objeto Transition con los nodos before/after y una función insert
     *
     * @param callable $check Función que recibe un Node y retorna bool
     * @return Transition
     */
    public function findTransition(callable $check): Transition {
        if (Algorithm::DEBUG) {
            print __METHOD__ . PHP_EOL;
        }

        $previous = $this->root;
        $current = $this->root->next;

        // Buscar el primer nodo que cumpla la condición
        while ($current !== null && !$check($current)) {
            $previous = $current;
            $current = $current->next;
        }

        // Crear función de inserción para esta transición
        $insertFunc = function(Node $node) use ($previous, $current): Node {
            $this->linkNodes($previous, $node, $current);
            return $node;
        };

        return new Transition(
            before: $previous === $this->root ? null : $previous,
            after: $current,
            insert: $insertFunc
        );
    }

    /**
     * Prepara un nodo para ser usado en la lista
     * Inicializa sus enlaces y función de eliminación
     *
     * @param Node $node Nodo a preparar
     * @return Node El mismo nodo preparado
     */
    public static function node(Node $node): Node {
        if (Algorithm::DEBUG) {
            print __METHOD__ . PHP_EOL;
        }

        $node->previous = null;
        $node->next = null;

        // Crear función de eliminación
        $node->remove = function() use ($node): void {
            if ($node->previous !== null) {
                $node->previous->next = $node->next;
            }
            if ($node->next !== null) {
                $node->next->previous = $node->previous;
            }
            $node->previous = null;
            $node->next = null;
        };

        return $node;
    }

    /**
     * Método privado para enlazar tres nodos consecutivamente
     * Optimiza el código repetido de enlazar nodos
     *
     * @param Node $before Nodo anterior
     * @param Node $middle Nodo a insertar en el medio
     * @param Node|null $after Nodo siguiente (puede ser null si es el final)
     */
    private function linkNodes(Node $before, Node $middle, ?Node $after): void {
        $middle->previous = $before;
        $middle->next = $after;
        $before->next = $middle;

        if ($after !== null) {
            $after->previous = $middle;
        }
    }
}
