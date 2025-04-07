<?php

namespace Ifsnop\MartinezRueda;

class LinkedList {
    private $root;

    public function __construct() {
        $this->root = new Node(isRoot: true);
    }

    public function exists(?Node $node) {
        return $node !== null && $node !== $this->root;
    }

    public function isEmpty() {
        return $this->root->next === null;
    }

    public function getHead() {
        return $this->root->next;
    }

    public function insertBefore(Node $node, callable $check) {
        $last = $this->root;
        $here = $this->root->next;

        while ($here !== null) {
            if ($check($here)) {
                $node->previous = $here->previous;
                $node->next = $here;
                $here->previous->next = $node;
                $here->previous = $node;
                return;
            }
            $last = $here;
            $here = $here->next;
        }
        $last->next = $node;
        $node->previous = $last;
        $node->next = null;
    }

    public function findTransition(callable $check) {
        $previous = $this->root;
        $here = $this->root->next;

        while ($here !== null) {
            if ($check($here)) {
                break;
            }
            $previous = $here;
            $here = $here->next;
        }

        $insertFunc = function(Node $node) use ($previous, $here) {
            $node->previous = $previous;
            $node->next = $here;
            $previous->next = $node;
            if ($here !== null) {
                $here->previous = $node;
            }
            return $node;
        };

        return new Transition(
            before: $previous === $this->root ? null : $previous,
            after: $here,
            insert: $insertFunc
        );
    }

    public static function node(Node $data) {
        $data->previous = null;
        $data->next = null;

        $removeFunc = function() use ($data) {
            $data->previous->next = $data->next;
            if ($data->next !== null) {
                $data->next->previous = $data->previous;
            }
            $data->previous = null;
            $data->next = null;
        };

        $data->remove = $removeFunc;
        return $data;
    }
}
