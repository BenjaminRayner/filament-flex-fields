<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Layers;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use InvalidArgumentException;

/** Compact card presentation shared by tracking, listings, dispatch and heatmap experiences. */
final class MapCard
{
    /** @var array<string, mixed> */
    private array $options = ['description' => '', 'position' => 'top-left', 'layer' => null, 'statistics' => [], 'progress' => null, 'progressLabel' => '', 'progressValue' => '', 'gradient' => [], 'lowLabel' => 'Low', 'highLabel' => 'High'];

    public function avatarLabel(string $label): self
    {
        $this->options['avatarLabel'] = $label;

        return $this;
    }

    public function avatar(string $url, string $label): self
    {
        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://') || strlen($url) > 2048) {
            throw new InvalidArgumentException('Map avatars require an HTTPS image URL.');
        }
        $this->options['avatarUrl'] = $url;

        return $this->avatarLabel($label);
    }

    private function __construct(private readonly string $title) {}

    public static function make(string $title): self
    {
        return new self($title);
    }

    public function width(int $pixels): self
    {
        if ($pixels < 180 || $pixels > 400) {
            throw new InvalidArgumentException('Map card width must be between 180 and 400 pixels.');
        }
        $this->options['width'] = $pixels;

        return $this;
    }

    public function description(string $text): self
    {
        $this->options['description'] = $text;

        return $this;
    }

    public function position(string $position): self
    {
        $this->options['position'] = $position;

        return $this;
    }

    public function listings(string $layer): self
    {
        $this->options['layer'] = $layer;

        return $this;
    }

    /** @param array<string, string|int|float> $statistics Label => displayed value. */
    public function statistics(array $statistics): self
    {
        $this->options['statistics'] = $statistics;

        return $this;
    }

    public function progress(float $fraction, string $label = '', string $value = ''): self
    {
        $this->options['progress'] = $fraction;
        $this->options['progressLabel'] = $label;
        $this->options['progressValue'] = $value;

        return $this;
    }

    /** @param list<string> $colors */
    public function gradient(array $colors, string $low = 'Low', string $high = 'High'): self
    {
        $this->options['gradient'] = $colors;
        $this->options['lowLabel'] = $low;
        $this->options['highLabel'] = $high;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = ['title' => $this->title, 'width' => 250, 'avatarUrl' => null, 'avatarLabel' => '', ...$this->options];
        foreach (['title', 'description', 'progressLabel', 'progressValue', 'lowLabel', 'highLabel', 'avatarLabel'] as $key) {
            $result[$key] = PickerConfiguration::text($result[$key], 'map card '.$key);
            if (strlen($result[$key]) > 256) {
                throw new InvalidArgumentException('Map card text is limited to 256 bytes.');
            }
        }
        if (! in_array($result['position'], ['top-left', 'top-right', 'bottom-left', 'bottom-right'], true)
            || count($result['statistics']) > 6 || ($result['progress'] !== null && (! is_finite($result['progress']) || $result['progress'] < 0 || $result['progress'] > 1))) {
            throw new InvalidArgumentException('Invalid map card configuration.');
        }
        $statistics = [];
        foreach ($result['statistics'] as $label => $value) {
            if (! is_string($label) || strlen($label) > 64 || strlen((string) $value) > 64 || (is_float($value) && ! is_finite($value))) {
                throw new InvalidArgumentException('Invalid map statistic.');
            }
            $statistics[] = ['label' => PickerConfiguration::text($label, 'statistic label'), 'value' => PickerConfiguration::text((string) $value, 'statistic value')];
        }
        $result['statistics'] = $statistics;
        if (! array_is_list($result['gradient']) || count($result['gradient']) > 8 || count($result['gradient']) === 1) {
            throw new InvalidArgumentException('Invalid map card gradient.');
        }
        foreach ($result['gradient'] as $color) {
            if (! is_string($color) || ! preg_match('/^#[a-fA-F0-9]{6}(?:[a-fA-F0-9]{2})?$/D', $color)) {
                throw new InvalidArgumentException('Gradient requires #RRGGBB colors.');
            }
        }

        return $result;
    }
}
