<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize as HasControlSizeConcern;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\OptionNormalizer;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\Presentation\CardSizeStyles;
use Bjanczak\FilamentFlexFields\Support\ChoiceCards\ResponsiveColumns;
use Closure;
use Illuminate\Support\Arr;

trait HasChoiceCardOptions
{
    use HasControlSizeConcern;

    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $options = [];

    /**
     * @var array<string|int, array{
     *     label: string,
     *     description: ?string,
     *     price: ?string,
     *     price_suffix: ?string,
     *     meta: ?string,
     *     icon: ?string,
     *     badge: ?string,
     *     badge_color: string,
     *     disabled: bool,
     * }>|null
     */
    protected ?array $normalizedOptionsCache = null;

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $disabledOptions = [];

    protected string|Closure $layout = 'stack';

    protected int|array|Closure $gridColumns = 1;

    protected string|Closure|null $indicator = null;

    protected string|Closure $variant = 'default';

    protected string|Closure|null $color = null;

    protected bool|Closure $isRippleEnabled = false;

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;
        $this->normalizedOptionsCache = null;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;
        $this->normalizedOptionsCache = null;

        return $this;
    }

    public function layout(string|Closure $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function gridColumns(int|array|Closure $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    public function indicator(string|Closure|null $indicator): static
    {
        $this->indicator = $indicator;

        return $this;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function ripple(bool|Closure $condition = true): static
    {
        $this->isRippleEnabled = $condition;

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
     *     price: ?string,
     *     price_suffix: ?string,
     *     meta: ?string,
     *     icon: ?string,
     *     badge: ?string,
     *     badge_color: string,
     *     disabled: bool,
     * }>
     */
    public function getNormalizedOptions(): array
    {
        return $this->normalizedOptionsCache ??= OptionNormalizer::card(
            $this->evaluate($this->options), $this->getDisabledOptions(),
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

    public function getLayout(): string
    {
        return $this->evaluate($this->layout);
    }

    /**
     * @return array{default: int, sm: int, md: int, lg: int}
     */
    public function getGridColumnConfig(): array
    {
        /** @var array{default:int,sm:int,md:int,lg:int} $columns */
        $columns = ResponsiveColumns::resolve($this->evaluate($this->gridColumns), 4, ['default', 'sm', 'md', 'lg']);

        return $columns;
    }

    public function getVariant(): string
    {
        return $this->evaluate($this->variant);
    }

    public function getColor(): string
    {
        $color = $this->evaluate($this->color);

        if (filled($color)) {
            return (string) $color;
        }

        return 'primary';
    }

    public function isRippleEnabled(): bool
    {
        return (bool) $this->evaluate($this->isRippleEnabled);
    }

    public function isOptionDisabled(string|int $key): bool
    {
        return $this->getNormalizedOptions()[(string) $key]['disabled'] ?? true;
    }

    /**
     * @return array<string, string>
     */
    public function getChoiceCardSizeStyles(): array
    {
        return CardSizeStyles::for($this->getSize());
    }
}
