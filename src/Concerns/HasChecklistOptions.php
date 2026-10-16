<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Support\ChoiceLists\OptionCatalog;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\Presentation\SizeStyles;
use Closure;
use Illuminate\Support\Arr;

trait HasChecklistOptions
{
    use HasControlSize;

    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $options = [];

    /**
     * @var array<string | int, string> | Closure
     */
    protected array|Closure $icons = [];

    /**
     * @var array<string | int, string> | Closure
     */
    protected array|Closure $descriptions = [];

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $disabledOptions = [];

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  array<string | int, string> | Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * @param  array<string | int, string> | Closure  $descriptions
     */
    public function descriptions(array|Closure $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;

        return $this;
    }

    /**
     * @return array<int, string|int>
     */
    public function getOptionKeys(): array
    {
        return array_keys($this->getNormalizedOptions());
    }

    /**
     * @return array<string|int, array{
     *     label: string,
     *     description: ?string,
     *     icon: ?string,
     *     disabled: bool,
     * }>
     */
    public function getNormalizedOptions(): array
    {
        return OptionCatalog::normalize(
            $this->evaluate($this->options),
            $this->evaluate($this->icons),
            $this->evaluate($this->descriptions),
            $this->getDisabledOptions(),
        );
    }

    /** @return list<string> */
    public function getEnabledOptionKeys(): array
    {
        $keys = [];
        foreach ($this->getNormalizedOptions() as $key => $option) {
            if (! $option['disabled']) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<string | int>
     */
    public function getDisabledOptions(): array
    {
        return Arr::wrap($this->evaluate($this->disabledOptions));
    }

    public function isOptionDisabled(string|int $key): bool
    {
        return $this->getNormalizedOptions()[(string) $key]['disabled'] ?? true;
    }

    /**
     * @return array<string, string>
     */
    public function getChecklistSizeStyles(): array
    {
        return SizeStyles::for($this->getSize(), 'flex-checklist');
    }
}
