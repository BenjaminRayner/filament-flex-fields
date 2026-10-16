<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location;

use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/** Map controls share the same Hugeicons source and SVG cache as other FLEX fields. */
final class MapIcon
{
    /** @var array<string, string> Semantic commands have distinct artwork. */
    private const array ACTIONS = [
        'locate' => 'gps-01',
        'follow' => 'target-02',
        'fit' => 'pin-location-03',
        'zoom-in' => 'plus-sign',
        'zoom-out' => 'minus-sign',
        'bearing' => 'navigation-05',
        'fullscreen' => 'expand',
        'clear' => 'location-update-01',
        'point' => 'pin-location-01',
        'polyline' => 'straight-edge',
        'polygon' => 'pentagon',
        'rectangle' => 'rectangular',
        'draw-circle' => 'circle',
        'undo' => 'undo-02',
        'redo' => 'redo-02',
        'rotate-left' => 'rotate-ccw-square',
        'rotate-right' => 'rotate-cw-square',
        'remove' => 'delete-02',
        'reset' => 'refresh-04',
        'play' => 'play',
        'pause' => 'pause',
        'plus' => 'plus-sign',
        'minus' => 'minus-sign',
        'north' => 'navigation-05',
        'close' => 'multiplication-sign',
    ];

    /** @var list<string> Previous public icon names remain compatibility aliases. */
    private const array NAMES = ['expand', 'circle', 'redo-02', 'rectangular', 'navigation-05',
        'straight-edge', 'eraser', 'search-minus', 'refresh-04', 'undo-02', 'pentagon', 'locate-fixed', 'search-add'];

    public static function render(string $name, string $class = 'fff-location-icon'): HtmlString
    {
        if (! isset(self::ACTIONS[$name]) && ! in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException('Unknown map icon: '.$name);
        }
        $name = self::ACTIONS[$name] ?? ($name === 'locate-fixed' ? 'gps-01' : $name);

        return new HtmlString(svg('hugeicons-'.$name, $class, ['aria-hidden' => 'true', 'focusable' => 'false'])->toHtml());
    }
}
