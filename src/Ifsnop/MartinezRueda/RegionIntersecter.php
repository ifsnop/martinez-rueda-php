<?php

declare(strict_types=1);

namespace Ifsnop\MartinezRueda;

final class RegionIntersecter extends Intersecter
{
    public function __construct()
    {
        parent::__construct(true);
    }

    public function addRegion(array $region)
    {
        $point1 = null;
        $point2 = end($region);
        foreach ($region as $currentPoint) {
            $point1 = $point2;
            $point2 = $currentPoint;
            $forward = Point::compare($point1, $point2);

            if ($forward === 0) {
                continue;
            }

            $segment = $this->newSegment(
                $forward < 0 ? $point1 : $point2,
                $forward < 0 ? $point2 : $point1
            );

            $this->eventAddSegment($segment, true);
        }
    }
}
