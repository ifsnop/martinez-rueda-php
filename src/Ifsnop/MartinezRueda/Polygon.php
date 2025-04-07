<?php

namespace Ifsnop\MartinezRueda;

class Polygon {
    public $regions;
    public $isInverted;

    public function __toString():string {
	$str = "";
	foreach($this->regions as $region) {
	    $str .= "[";
	    $var_dump($region);
	    foreach($region as $points) {
		var_dump($points);
		$str .= $points . ",";
	    }
	    $str = substr($str, 0, -1) . "],";
	}
	return substr($str, 0, -1);
    }

    public function getArray() {
	$arr_regions = [];
	foreach($this->regions as $region) {
	    $arr_points = [];
	    foreach($region as $point) {
		$arr_points[] = $point->getArray();
	    }
	    $arr_regions[] = $arr_points;
	}
	return $arr_regions;
    }

    public function __construct(){
	print __METHOD__ . PHP_EOL;
    }

    public static function create() {
	print __METHOD__ . PHP_EOL;
	return new self();
    }
    public function fillFromArray(array $regions, bool $isInverted = false) {
	print __METHOD__ . PHP_EOL;
        $_regions = [];
        foreach ($regions as $region) {
            $tmp = [];
            foreach ($region as $pt) {
                if ($pt instanceof Point) {
                    $tmp[] = $pt;
                } elseif (is_array($pt) && count($pt) == 2) {
                    list($x, $y) = $pt;
                    $tmp[] = new Point($x, $y);
                }
            }
            $_regions[] = $tmp;
        }
        $this->regions = $_regions;
        $this->isInverted = $isInverted;
	return $this;
    }
    public function fillFromPolySegments(PolySegments $regions, bool $isInverted = false) {
	print __METHOD__ . PHP_EOL;
        $_regions = [];
        foreach ($regions as $region) {
            $tmp = [];
	    print "Polygon::__construct" . PHP_EOL;
	    if ( is_bool($region)) {
		var_dump($region);
		var_dump($regions);
		print_r($regions); // falla cuando le pasas un polysegments, porque hay segments y una variable con isInverted!
		print "ERROR1" . PHP_EOL;
		exit(0);
	    }
            foreach ($region as $pt) {
		print_r($pt);
                if ($pt instanceof Point) {
                    $tmp[] = $pt;
                } elseif (is_array($pt) && count($pt) == 2) {
                    list($x, $y) = $pt;
                    $tmp[] = new Point($x, $y);
                }
            }
            $_regions[] = $tmp;
        }

        $this->regions = $_regions;
        $this->isInverted = $isInverted;
	return $this;
    }
}

