<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ColorPicker;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration as Config;
use Bjanczak\FilamentFlexFields\Support\Translations;
use InvalidArgumentException;

final class PickerConfiguration
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function resolve(array $options = []): array
    {
        $config = [
            'layout' => Config::choice($options['layout'] ?? 'advanced', ['advanced', 'grid', 'swatches', 'sliders'], 'color.layout'),
            'trigger' => Config::choice($options['trigger'] ?? 'field', ['field', 'swatch', 'icon'], 'color.trigger'),
            'format' => Config::choice($options['format'] ?? 'hex', ['hex', 'rgb', 'rgba', 'hsl', 'hsla'], 'color.format'),
            'placement' => Config::choice($options['placement'] ?? 'bottom-start', ['bottom-start', 'bottom-end', 'top-start', 'top-end'], 'color.placement'),
        ];
        foreach (['alphaEnabled' => false, 'eyedropperEnabled' => true, 'inline' => false, 'showValue' => true, 'showFormatSelector' => true, 'showSwatches' => false, 'showFields' => true, 'channelFields' => true, 'showChannelLabels' => true, 'closeOnSelect' => false] as $key => $default) {
            $value = $options[$key] ?? $default;
            if (! is_bool($value)) {
                throw new InvalidArgumentException("Color picker {$key} requires boolean.");
            }
            $config[$key] = $value;
        }
        $formats = $options['allowedFormats'] ?? ($config['alphaEnabled'] ? ['hex', 'rgb', 'hsl', 'rgba', 'hsla'] : ['hex', 'rgb', 'hsl']);
        if (! is_array($formats) || ! array_is_list($formats) || $formats === [] || count($formats) > 5) {
            throw new InvalidArgumentException('Color allowedFormats requires a non-empty list of at most five formats.');
        }
        $config['allowedFormats'] = array_values(array_unique(array_map(
            static fn (mixed $format): string => Config::choice($format, ['hex', 'rgb', 'rgba', 'hsl', 'hsla'], 'color.allowedFormats'),
            $formats,
        )));
        if (! $config['alphaEnabled']) {
            $opaque = static fn (string $format): string => match ($format) {
                'rgba' => 'rgb', 'hsla' => 'hsl', default => $format
            };
            $config['format'] = $opaque($config['format']);
            $config['allowedFormats'] = array_values(array_unique(array_map($opaque, $config['allowedFormats'])));
        }
        if (! in_array($config['format'], $config['allowedFormats'], true)) {
            if (array_key_exists('format', $options)) {
                throw new InvalidArgumentException('The storage format must be included in color allowedFormats.');
            }
            $config['format'] = $config['allowedFormats'][0];
        }
        $config['showFormatSelector'] = $config['showFormatSelector'] && count($config['allowedFormats']) > 1;
        foreach (['gridColumns' => [17, 1, 32], 'gridRows' => [11, 1, 32], 'panelWidth' => [248, 220, 420], 'panelRadius' => [20, 0, 32], 'animationDuration' => [150, 0, 500]] as $key => [$default, $min, $max]) {
            $value = Config::integer($options[$key] ?? $default, 'color.'.$key);
            if ($value < $min || $value > $max) {
                throw new InvalidArgumentException("Color picker {$key} is outside its supported range.");
            }
            $config[$key] = $value;
        }
        if (($config['gridRows'] ?? 11) * ($config['gridColumns'] ?? 17) > 512) {
            throw new InvalidArgumentException('Color grids are limited to 512 cells.');
        }
        $colors = $options['gridColors'] ?? null;
        if ($colors !== null) {
            if (! is_array($colors) || ! array_is_list($colors) || count($colors) > 512) {
                throw new InvalidArgumentException('Color palette requires a bounded list.');
            }
            foreach ($colors as $color) {
                if (! ColorValue::valid($color, $config['alphaEnabled'])) {
                    throw new InvalidArgumentException('Invalid palette color.');
                }
            }
            $colors = array_values(array_unique($colors));
        }
        $config['gridColors'] = $colors;
        $config['labels'] = [];
        foreach (['eyedropper', 'format', 'value', 'opacity', 'hue', 'saturation', 'brightness', 'lightness', 'red', 'green', 'blue', 'close', 'invalid'] as $key) {
            $config['labels'][$key] = Translations::get('filament-flex-fields::color-picker.'.$key);
        }

        return $config;
    }
}
