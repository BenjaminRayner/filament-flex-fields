<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Tags;

use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

/** Tag-specific policy. Geometry and virtualization come from Select's public surface API. */
trait ConfiguresTags
{
    /** @var array<string, mixed> */
    protected array $tagPolicy = ['variant' => 'primary', 'maxTags' => null,
        'suggestionsOnly' => false, 'duplicateInsensitive' => false, 'showTagCount' => false];

    public function variant(string|Closure $variant): static
    {
        $this->tagPolicy['variant'] = $variant;

        return $this;
    }

    public function maxTags(int|Closure|null $max): static
    {
        $this->tagPolicy['maxTags'] = $max;

        return $this;
    }

    public function suggestionsOnly(bool|Closure $condition = true): static
    {
        $this->tagPolicy['suggestionsOnly'] = $condition;

        return $this;
    }

    public function duplicateInsensitive(bool|Closure $condition = true): static
    {
        $this->tagPolicy['duplicateInsensitive'] = $condition;

        return $this;
    }

    public function showTagCount(bool|Closure $condition = true): static
    {
        $this->tagPolicy['showTagCount'] = $condition;

        return $this;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->tagPolicy['variant']), ['primary', 'secondary', 'flat', 'soft'], 'variant');
    }

    public function getMaxTags(): ?int
    {
        $value = $this->evaluate($this->tagPolicy['maxTags']);
        if ($value === null) {
            return null;
        }
        $maximum = PickerConfiguration::integer($value, 'maxTags');
        if ($maximum < 0 || $maximum > 10000) {
            throw new InvalidArgumentException('Tags maxTags must be between 0 and 10000.');
        }

        return $maximum;
    }

    public function isSuggestionsOnly(): bool
    {
        return (bool) $this->evaluate($this->tagPolicy['suggestionsOnly']);
    }

    public function isDuplicateInsensitive(): bool
    {
        return (bool) $this->evaluate($this->tagPolicy['duplicateInsensitive']);
    }

    public function shouldShowTagCount(): bool
    {
        return (bool) $this->evaluate($this->tagPolicy['showTagCount']);
    }
}
