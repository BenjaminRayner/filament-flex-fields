<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

/** Local, non-antimeridian outer-ring collision policy; shared boundaries may be allowed. */
final class ZoneCollision
{
    private const EPS = 1.0e-10;

    /**
     * @param  array<string, mixed>  $collection
     * @param  array<string, mixed>  $config
     */
    public static function detected(array $collection, array $config): bool
    {
        if (! $config['preventZoneOverlap']) {
            return false;
        }
        $zones = array_values(array_filter($collection['features'], static fn (array $feature): bool => $feature['geometry']['type'] === 'Polygon'));
        $overlays = $config['collisionWithOverlays'] ? array_values(array_filter($config['features']['features'], static fn (array $feature): bool => $feature['geometry']['type'] === 'Polygon')) : [];
        foreach ($zones as $index => $zone) {
            foreach ([...array_slice($zones, $index + 1), ...$overlays] as $other) {
                if (self::rings($zone['geometry']['coordinates'][0], $other['geometry']['coordinates'][0], $config['allowZoneTouching'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $a
     * @param  list<array{0: float, 1: float}>  $b
     */
    public static function rings(array $a, array $b, bool $allowTouching = true): bool
    {
        if (count($a) < 4 || count($b) < 4) {
            return false;
        }
        $x = self::bounds($a);
        $y = self::bounds($b);
        if ($x[2] < $y[0] || $y[2] < $x[0] || $x[3] < $y[1] || $y[3] < $x[1]) {
            return false;
        }
        for ($i = 0; $i < count($a) - 1; $i++) {
            for ($j = 0; $j < count($b) - 1; $j++) {
                [$p, $q, $r, $s] = [$a[$i], $a[$i + 1], $b[$j], $b[$j + 1]];
                $u = self::cross($p, $q, $r);
                $v = self::cross($p, $q, $s);
                $w = self::cross($r, $s, $p);
                $z = self::cross($r, $s, $q);
                if ($u * $v < -self::EPS ** 2 && $w * $z < -self::EPS ** 2) {
                    return true;
                }
                if (! $allowTouching && (self::on($p, $q, $r) || self::on($p, $q, $s) || self::on($r, $s, $p) || self::on($r, $s, $q))) {
                    return true;
                }
            }
        }
        foreach ($a as $point) {
            if (self::inside($point, $b)) {
                return true;
            }
        }
        foreach ($b as $point) {
            if (self::inside($point, $a)) {
                return true;
            }
        }
        $offset = max(1.0e-8, min($x[2] - $x[0], $x[3] - $x[1], $y[2] - $y[0], $y[3] - $y[1]) * 1.0e-6);
        foreach ([$a, $b] as $index => $ring) {
            for ($i = 0; $i < count($ring) - 1; $i++) {
                [$p, $q] = [$ring[$i], $ring[$i + 1]];
                $mid = [($p[0] + $q[0]) / 2, ($p[1] + $q[1]) / 2];
                if (self::inside($mid, $index === 0 ? $b : $a)) {
                    return true;
                }
                $length = hypot($q[0] - $p[0], $q[1] - $p[1]);
                if ($length === 0.0) {
                    continue;
                }
                foreach ([-1, 1] as $sign) {
                    $sample = [$mid[0] + ($p[1] - $q[1]) / $length * $offset * $sign, $mid[1] + ($q[0] - $p[0]) / $length * $offset * $sign];
                    if (self::inside($sample, $a) && self::inside($sample, $b)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return array{float, float, float, float}
     */
    private static function bounds(array $ring): array
    {
        if ($ring === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }
        $lng = array_column($ring, 0);
        $lat = array_column($ring, 1);

        return [min($lng), min($lat), max($lng), max($lat)];
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private static function inside(array $point, array $ring): bool
    {
        $inside = false;
        for ($i = 0; $i < count($ring) - 1; $i++) {
            [$a, $b] = [$ring[$i], $ring[$i + 1]];
            if (self::on($a, $b, $point)) {
                return false;
            }
            if (($a[1] > $point[1]) !== ($b[1] > $point[1]) && $point[0] < ($b[0] - $a[0]) * ($point[1] - $a[1]) / ($b[1] - $a[1]) + $a[0]) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $p
     */
    private static function cross(array $a, array $b, array $p): float
    {
        return ($b[0] - $a[0]) * ($p[1] - $a[1]) - ($b[1] - $a[1]) * ($p[0] - $a[0]);
    }

    /**
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $p
     */
    private static function on(array $a, array $b, array $p): bool
    {
        return abs(self::cross($a, $b, $p)) <= self::EPS && $p[0] >= min($a[0], $b[0]) - self::EPS && $p[0] <= max($a[0], $b[0]) + self::EPS && $p[1] >= min($a[1], $b[1]) - self::EPS && $p[1] <= max($a[1], $b[1]) + self::EPS;
    }
}
