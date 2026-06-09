<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class Polygon
{
	public array $regions;
	public bool $isInverted;
	public int $numPoints;

	public function __toString(): string
	{
		$str = "";
		foreach ($this->regions as $region) {
			$str .= "[";
			foreach ($region as $points) {
				$str .= $points . ",";
			}
			$str = substr($str, 0, -1) . "],";
		}
		return substr($str, 0, -1);
	}

	public function getArray(): array
	{
		$arr_regions = [];
		foreach ($this->regions as $region) {
			$arr_points = [];
			foreach ($region as $point) {
				$arr_points[] = $point->getArray();
			}
			$arr_regions[] = $arr_points;
		}
		return $arr_regions;
	}

	public function getArrayClosed(): array
	{
		$arr_regions = [];
		foreach ($this->regions as $region) {
			$arr_points = [];
			foreach ($region as $point) {
				$arr_points[] = $point->getArray();
			}
			$arr_points[] = $arr_points[0];
			$arr_regions[] = $arr_points;
		}
		return $arr_regions;
	}

	public function __construct()
	{
		$this->regions = [];
		$this->numPoints = 0;
	}

	public static function create()
	{
		return new self();
	}

	public function fillFromArray(array $regions, bool $isInverted = false)
	{
		// Helpers para identificar la estructura
		$isPointObj = static fn($v) => $v instanceof Point;
		$isCoordPair = static fn($v) => is_array($v) && count($v) === 2 && is_numeric($v[0]) && is_numeric($v[1]);
		$isRing = function ($arr) use ($isPointObj, $isCoordPair): bool {
			if (!is_array($arr) || empty($arr)) return false;
			foreach ($arr as $v) {
				if (!($isPointObj($v) || $isCoordPair($v))) return false;
			}
			return true;
		};
		$isPolygon = function ($arr) use ($isRing): bool {
			if (!is_array($arr) || empty($arr)) return false;
			foreach ($arr as $ring) {
				if (!$isRing($ring)) return false;
			}
			return true;
		};

		// Normaliza a una lista de anillos (cada anillo = array de Point o [x,y])
		$toRings = function ($input) use ($isRing, $isPolygon): array {
			$rings = [];
			if ($isRing($input)) {
				// Caso: un solo anillo
				$rings[] = $input;
			} elseif ($isPolygon($input)) {
				// Caso: Polygon (array de anillos)
				foreach ($input as $ring) {
					$rings[] = $ring;
				}
			} elseif (is_array($input)) {
				// Caso: MultiPolygon (array de polígonos y/o anillos mezclados)
				foreach ($input as $maybe) {
					if ($isRing($maybe)) {
						$rings[] = $maybe;
					} elseif ($isPolygon($maybe)) {
						foreach ($maybe as $ring) {
							$rings[] = $ring;
						}
					} else {
						throw new \InvalidArgumentException(
							'fillFromArray: estructura no reconocida (se esperaba anillo o polígono).'
						);
					}
				}
			} else {
				throw new \InvalidArgumentException('fillFromArray: se esperaba un array.');
			}
			return $rings;
		};

		$rings = $toRings($regions);

		$_regions = [];
		foreach ($rings as $region) {
			$tmp = [];
			foreach ($region as $pt) {
				if ($pt instanceof Point) {
					$tmp[] = $pt;
					$this->numPoints++;
				} elseif (is_array($pt) && count($pt) === 2) {
					// Soporta arrays numéricos y asociativos con 2 valores
					[$x, $y] = array_values($pt);
					$tmp[] = new Point((float)$x, (float)$y);
					$this->numPoints++;
				} else {
					throw new \InvalidArgumentException('fillFromArray: punto inválido en anillo.');
				}
			}

			if (!empty($tmp)) {
				$_regions[] = $tmp;
			}
		}

		$this->regions = array_merge($this->regions, $_regions);
		$this->isInverted = $isInverted;
		return $this;
	}
}
