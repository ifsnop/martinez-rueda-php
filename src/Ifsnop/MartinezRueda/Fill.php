<?php

// Define el espacio de nombres (namespace) donde se encuentra esta clase.
// Esto ayuda a evitar conflictos de nombres en proyectos grandes.
namespace Ifsnop\MartinezRueda;

// Se declara una clase llamada Fill.
class Fill {
    // Se definen dos propiedades públicas: $below y $above.
    public $below;
    public $above;

    // Constructor de la clase, acepta dos parámetros booleanos opcionales.
    // Si no se pasan, serán null por defecto.
    public function __construct(bool $below = null, bool $above = null) {
        // Se asignan los valores recibidos a las propiedades del objeto.
        $this->below = $below;
        $this->above = $above;
    }

    // Método mágico __toString, se ejecuta cuando el objeto se usa como cadena.
    // Devuelve una cadena con los valores de above y below separados por coma.
    public function __toString() {
        return "{$this->above},{$this->below}";
    }

    // Método mágico __debugInfo, define cómo se mostrará el objeto al hacer un var_dump.
    public function __debugInfo() {
        return ["below" => $this->below, "above" => $this->above];
    }
}
