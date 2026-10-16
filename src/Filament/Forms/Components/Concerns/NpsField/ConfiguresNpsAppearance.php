<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField;

use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use Bjanczak\FilamentFlexFields\Support\Nps\OptionValue;
use Closure;
use InvalidArgumentException;

trait ConfiguresNpsAppearance
{
    protected string|Closure $variant = 'pills';

    protected string|Closure|null $minLabel = null;

    protected string|Closure|null $maxLabel = null;

    protected bool|Closure $isColorCoded = false;

    /** @var array<array-key, string>|Closure|null */
    protected array|Closure|null $emojiImages = null;

    /** @var array<array-key, string>|Closure */
    protected array|Closure $icons = [];

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $colors = null;

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $textColors = null;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        return in_array($variant, ['pills', 'segments', 'emojis'], true) ? $variant
            : throw new InvalidArgumentException('NpsField variant must be pills, segments or emojis.');
    }

    public function minLabel(string|Closure|null $label): static
    {
        $this->minLabel = $label;

        return $this;
    }

    public function getMinLabel(): ?string
    {
        return $this->evaluate($this->minLabel);
    }

    public function maxLabel(string|Closure|null $label): static
    {
        $this->maxLabel = $label;

        return $this;
    }

    public function getMaxLabel(): ?string
    {
        return $this->evaluate($this->maxLabel);
    }

    public function colorCoded(bool|Closure $condition = true): static
    {
        $this->isColorCoded = $condition;

        return $this;
    }

    public function isColorCoded(): bool
    {
        return (bool) $this->evaluate($this->isColorCoded);
    }

    /** @param array<string, mixed>|Closure|null $colors */
    public function colors(array|Closure|null $colors): static
    {
        $this->colors = $colors;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getColors(): array
    {
        return $this->evaluate($this->colors) ?? [];
    }

    /** @param array<string, mixed>|Closure|null $textColors */
    public function textColors(array|Closure|null $textColors): static
    {
        $this->textColors = $textColors;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getTextColors(): array
    {
        return $this->evaluate($this->textColors) ?? [];
    }

    public function getOptionColor(mixed $value): ?string
    {
        $colors = $this->getColors();

        return $this->matchNpsColor($value, $colors) ?? ($colors === [] && $this->isColorCoded()
            ? match (OptionValue::tone($value)) {
                'detractor' => 'danger', 'passive' => 'warning', 'promoter' => 'success', default => null
            } : null);
    }

    public function getOptionTextColor(mixed $value): ?string
    {
        $colors = $this->getTextColors();

        return $this->matchNpsColor($value, $colors) ?? ($colors === [] && $this->isColorCoded()
            ? match (OptionValue::tone($value)) {
                'detractor', 'promoter' => '#ffffff', 'passive' => 'gray-900', default => null
            } : null);
    }

    /** @param array<string, mixed> $map */
    protected function matchNpsColor(mixed $value, array $map): ?string
    {
        $identity = OptionValue::identity($value);
        if ($identity === null) {
            return null;
        }
        foreach ($map as $color => $condition) {
            $matches = $condition instanceof Closure ? (bool) $this->evaluate($condition, ['value' => $value])
                : in_array($identity, array_map(OptionValue::identity(...), is_array($condition) ? $condition : [$condition]), true);
            if ($matches) {
                return $color;
            }
        }

        return null;
    }

    public function isDetractor(mixed $key): bool
    {
        return OptionValue::tone($key) === 'detractor';
    }

    public function isPassive(mixed $key): bool
    {
        return OptionValue::tone($key) === 'passive';
    }

    public function isPromoter(mixed $key): bool
    {
        return OptionValue::tone($key) === 'promoter';
    }

    public function getOptionTone(mixed $value): ?string
    {
        return $this->isColorCoded() ? OptionValue::tone($value) : null;
    }

    /** @param array<array-key, string>|Closure $icons */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /** @return array<array-key, string> */
    public function getIcons(): array
    {
        return $this->evaluate($this->icons);
    }

    public function getOptionIcon(mixed $value): ?string
    {
        $key = OptionValue::identity($value);
        $icon = $key === null ? null : ($this->getIcons()[$key] ?? null);

        return filled($icon) ? $icon : null;
    }

    /** @param array<array-key, string>|Closure|null $images */
    public function emojiImages(array|Closure|null $images): static
    {
        $this->emojiImages = $images;

        return $this;
    }

    /** @return array<array-key, string> */
    public function getEmojiImages(): array
    {
        return $this->evaluate($this->emojiImages) ?? [];
    }

    public function getEmojiImage(mixed $value): ?string
    {
        $key = OptionValue::identity($value);
        $images = $this->getEmojiImages();
        if ($key !== null && array_key_exists($key, $images)) {
            return $images[$key];
        }

        return $this->defaultNpsEmojiImage($key);
    }

    protected function defaultNpsEmojiImage(?string $key): ?string
    {
        return $key !== null && in_array($key, ['0', '1', '2', '3', '4'], true)
            ? PublishedResourceUrl::for('nps-field', 'resources', 'emojis/'.$key.'.webp') : null;
    }

    /** @return array<string, string> */
    public function getOptionStyleVariables(mixed $value): array
    {
        if ($this->isColorCoded()) {
            return [];
        }

        return $this->npsStyleVariables($value, $this->getColors(), $this->getTextColors());
    }

    /**
     * @param  array<string, mixed>  $backgrounds
     * @param  array<string, mixed>  $texts
     * @return array<string, string>
     */
    protected function npsStyleVariables(mixed $value, array $backgrounds, array $texts): array
    {
        $variables = [];
        foreach (['--fff-nps-selected-bg' => $backgrounds, '--fff-nps-selected-color' => $texts] as $variable => $colors) {
            $resolved = $this->resolveColorValue($this->matchNpsColor($value, $colors));
            if ($resolved !== null) {
                $variables[$variable] = $resolved;
            }
        }

        return $variables;
    }

    public function resolveColorValue(?string $color): ?string
    {
        if ($color === null || $color === '') {
            return null;
        }
        if (preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/iD', $color)
            || preg_match('/^rgba?\([\d\s.,%\/+-]+\)$/iD', $color)) {
            return $color;
        }
        if (! preg_match('/^[a-z][a-z0-9-]*$/iD', $color)) {
            throw new InvalidArgumentException('NpsField colors must be color names, hex or rgb values.');
        }
        if (in_array($color, ['white', 'black', 'transparent', 'inherit'], true)) {
            return $color;
        }
        if ($color === 'gray-900') {
            return 'var(--fi-color-gray-900, #18181b)';
        }
        $fallback = ['danger' => '#ef4444', 'warning' => '#eab308', 'success' => '#22c55e', 'primary' => '#6366f1', 'gray' => '#6b7280'][$color] ?? 'inherit';

        return "var(--fi-color-{$color}-500, {$fallback})";
    }
}
