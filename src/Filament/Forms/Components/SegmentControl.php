<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\Segments\HasSegmentAppearance;
use Bjanczak\FilamentFlexFields\Support\Segments\SegmentItem;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class SegmentControl extends Field
{
    use HasControlSize;
    use HasSegmentAppearance;

    protected string $view = 'filament-flex-fields::forms.components.segment-control';

    /** @var array<string|int, string|array<string, mixed>>|Closure */
    protected array|Closure $options = [];

    /** @var array<string|int, string>|Closure */
    protected array|Closure $icons = [];

    /** @var array<string|int>|Closure */
    protected array|Closure $disabledOptions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule(fn (SegmentControl $component): In => Rule::in($component->getOptionKeys()));
    }

    /** @param array<string|int, string|array<string, mixed>>|Closure $options */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @param array<string|int, string>|Closure $icons */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /** @param array<string|int>|Closure $keys */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;

        return $this;
    }

    /** @return array<string|int, string> */
    public function getIcons(): array
    {
        return $this->evaluate($this->icons);
    }

    /** @return array<string|int> */
    public function getDisabledOptions(): array
    {
        return Arr::wrap($this->evaluate($this->disabledOptions));
    }

    /** @return array<string|int, array{label: string, icon: ?string, disabled: bool, tooltip: ?string}> */
    public function getNormalizedOptions(): array
    {
        $icons = $this->getIcons();
        $disabled = array_fill_keys(array_map(strval(...), $this->getDisabledOptions()), true);
        $options = [];
        foreach ($this->evaluate($this->options) as $key => $definition) {
            if (! is_string($definition) && ! is_array($definition)) {
                continue;
            }
            $definition = is_string($definition) ? ['label' => $definition] : $definition;
            $options[$key] = [
                'label' => (string) ($definition['label'] ?? $key),
                'icon' => $definition['icon'] ?? $icons[$key] ?? null,
                'disabled' => isset($disabled[(string) $key]) || (bool) ($definition['disabled'] ?? false),
                'tooltip' => filled($definition['tooltip'] ?? null) ? (string) $definition['tooltip'] : null,
            ];
        }

        return $options;
    }

    /** @return list<string|int> */
    public function getOptionKeys(): array
    {
        return array_keys($this->getNormalizedOptions());
    }

    public function isOptionDisabled(string|int $key): bool
    {
        return $this->getNormalizedOptions()[$key]['disabled'] ?? true;
    }

    /** @return list<SegmentItem> */
    public function getSegmentItems(): array
    {
        $items = [];
        foreach ($this->getNormalizedOptions() as $key => $option) {
            $items[] = new SegmentItem(...['key' => $key, ...$option]);
        }

        return $items;
    }
}
