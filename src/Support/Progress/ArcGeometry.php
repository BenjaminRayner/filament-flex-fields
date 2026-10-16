<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Progress;

final class ArcGeometry
{
    /** @return array{radius: float, strokeWidth: float, centerX: float, centerY: float, circumference: float, arcLength: float, progressLength: float, gapLength: float, rotation: float, viewBox: string, gradientX1: float, gradientY1: float, gradientX2: float, gradientY2: float, viewBoxHeight: float, semicircleFloorInsetPercent: float} */
    public static function forProgress(bool $semicircle, float $gap, float $ratio): array
    {
        $radius = 42.0;
        $stroke = 8.0;
        $span = max(1.0, ($semicircle ? 210.0 : 360.0) - $gap);
        $rotation = $semicircle ? 270.0 - $span / 2.0 : 90.0 + $gap / 2.0;
        $centerY = $semicircle ? 46.0 : 50.0;
        $endpoint = $centerY + $radius * sin(deg2rad($rotation));
        $height = $semicircle ? (float) ceil($endpoint + $stroke / 2.0 + 2.0) : 100.0;
        $circumference = 2.0 * M_PI * $radius;
        $length = $circumference * $span / 360.0;

        return [
            'radius' => $radius, 'strokeWidth' => $stroke, 'centerX' => 50.0, 'centerY' => $centerY,
            'circumference' => $circumference, 'arcLength' => $length,
            'progressLength' => $length * max(0.0, min(1.0, $ratio)),
            'gapLength' => $circumference - $length, 'rotation' => $rotation,
            'viewBox' => '0 0 100 '.(int) $height, 'viewBoxHeight' => $height,
            'semicircleFloorInsetPercent' => $semicircle ? round(($height - $endpoint) / $height * 100.0, 3) : 0.0,
            'gradientX1' => 8.0, 'gradientY1' => $semicircle ? 62.0 : 88.0,
            'gradientX2' => 92.0, 'gradientY2' => $semicircle ? 18.0 : 12.0,
        ];
    }
}
