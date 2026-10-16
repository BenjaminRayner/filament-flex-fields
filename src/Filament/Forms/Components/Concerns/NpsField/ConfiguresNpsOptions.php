<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\NpsField;

use Bjanczak\FilamentFlexFields\Support\Nps\OptionValue;
use Closure;
use InvalidArgumentException;

trait ConfiguresNpsOptions
{
    /** @var array<array-key, string|int>|Closure|null */
    protected array|Closure|null $options = null;

    /** @var array<array-key, string|int>|Closure */
    protected array|Closure $disabledOptions = [];

    /** @param array<array-key, string|int>|Closure $options */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** @return array<array-key, string|int> */
    public function getOptions(): array
    {
        $options = $this->evaluate($this->options) ?? array_combine(range(0, 10), range(0, 10));
        OptionValue::assertOptions($options);

        return $options;
    }

    /** @return list<string|int> */
    public function getOptionKeys(): array
    {
        return array_keys($this->getOptions());
    }

    /** @param array<array-key, string|int>|Closure $keys */
    public function disabledOptions(array|Closure $keys): static
    {
        $this->disabledOptions = $keys;

        return $this;
    }

    /** @return list<string|int> */
    public function getDisabledOptions(): array
    {
        $keys = array_values($this->evaluate($this->disabledOptions));
        foreach ($keys as $key) {
            if (OptionValue::identity($key) === null) {
                throw new InvalidArgumentException('NpsField disabled options require valid option keys.');
            }
        }

        return $keys;
    }

    public function isOptionDisabled(string|int $key): bool
    {
        return in_array((string) $key, array_map(strval(...), $this->getDisabledOptions()), true);
    }

    /** @return list<string|int> */
    public function getEnabledOptionKeys(): array
    {
        $disabled = array_fill_keys(array_map(strval(...), $this->getDisabledOptions()), true);

        return array_values(array_filter($this->getOptionKeys(), static fn (string|int $key): bool => ! isset($disabled[(string) $key])));
    }
}
