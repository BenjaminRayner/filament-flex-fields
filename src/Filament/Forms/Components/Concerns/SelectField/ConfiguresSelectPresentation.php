<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Closure;
use InvalidArgumentException;

/**
 * Select appearance configuration; rendering mode and wrapper have separate owners.
 *
 * @mixin SelectField
 */
trait ConfiguresSelectPresentation
{
    use PresentsSelectWrapper;
    use ResolvesSelectRenderingMode;

    protected string|Closure $variant = 'bordered';

    protected string|Closure|null $color = null;

    protected string|Closure $chipColor = 'neutral';

    protected bool|Closure $keepSelectedOptionsInDropdown = false;

    protected bool|Closure|null $usesRichOptions = null;

    protected string|Closure $optionLayout = 'list';

    protected string|Closure $optionDescriptionPosition = 'below';

    protected bool|Closure $inlineFieldLabel = false;

    protected bool|Closure $inlineSearch = false;

    protected bool|Closure|null $richListTriggerDisplay = null;

    protected string|Closure|null $dropdownAlign = null;

    protected bool|Closure $selectionIndicator = true;

    protected bool|Closure $plainTrigger = false;

    public function selectionIndicator(bool|Closure $condition = true): static
    {
        $this->selectionIndicator = $condition;

        return $this;
    }

    public function showsSelectionIndicator(): bool
    {
        return (bool) $this->evaluate($this->selectionIndicator);
    }

    /** Render selected content and prefix without a chip or affix divider. */
    public function plainTrigger(bool|Closure $condition = true): static
    {
        $this->plainTrigger = $condition;

        return $this;
    }

    public function hasPlainTrigger(): bool
    {
        return (bool) $this->evaluate($this->plainTrigger);
    }

    public function dropdownAlign(string|Closure $align): static
    {
        $this->dropdownAlign = $align;

        return $this;
    }

    public function getDropdownAlign(): string
    {
        if ($this->dropdownAlign !== null) {
            $align = PickerConfiguration::text($this->evaluate($this->dropdownAlign), 'dropdownAlign');

            if (! in_array($align, ['start', 'end'], true)) {
                throw new InvalidArgumentException("Select dropdown align [{$align}] is not supported.");
            }

            return $align;
        }

        return $this->getVariant() === 'item-card' ? 'end' : 'start';
    }

    public function shouldUseRichListDropdownLayout(): bool
    {
        return $this->usesRichOptionHtml() && $this->getOptionLayout() === 'list';
    }

    /**
     * When true, the closed trigger keeps the full list HTML (icon + title + description).
     * SelectField defaults to compact trigger (icon + title) so field height matches size().
     * UserSelect overrides this to keep email/subtitle visible in the closed control.
     */
    public function shouldUseRichListTriggerDisplay(): bool
    {
        if ($this->richListTriggerDisplay !== null) {
            return (bool) $this->evaluate($this->richListTriggerDisplay);
        }

        return false;
    }

    /**
     * Keep the full list row HTML in the closed trigger (avatar + name + email).
     */
    public function richListTriggerDisplay(bool|Closure $condition = true): static
    {
        $this->richListTriggerDisplay = $condition;

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

    public function chipColor(string|Closure $chipColor): static
    {
        $this->chipColor = $chipColor;

        return $this;
    }

    /**
     * Keep selected values visible in the multi-select dropdown (checklist style)
     * instead of removing them from the option list after selection.
     */
    public function keepSelectedOptionsInDropdown(bool|Closure $condition = true): static
    {
        $this->keepSelectedOptionsInDropdown = $condition;

        return $this;
    }

    public function shouldKeepSelectedOptionsInDropdown(): bool
    {
        return $this->isMultiple() && (bool) $this->evaluate($this->keepSelectedOptionsInDropdown);
    }

    public function richOptions(bool|Closure $condition = true): static
    {
        $this->usesRichOptions = $condition;
        $this->forgetUsesRichOptionHtmlCache();

        return $this;
    }

    public function optionLayout(string|Closure $layout): static
    {
        $this->optionLayout = $layout;
        $this->forgetUsesRichOptionHtmlCache();

        return $this;
    }

    /** Place secondary text beneath the name or at the logical end of a single row. */
    public function optionDescriptionPosition(string|Closure $position): static
    {
        $this->optionDescriptionPosition = $position;

        return $this;
    }

    public function getOptionDescriptionPosition(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->optionDescriptionPosition), ['below', 'trailing'], 'optionDescriptionPosition');
    }

    public function inlineFieldLabel(bool|Closure $condition = true): static
    {
        $this->inlineFieldLabel = $condition;

        return $this;
    }

    public function hasInlineFieldLabel(): bool
    {
        return (bool) $this->evaluate($this->inlineFieldLabel);
    }

    public function inlineSearch(bool|Closure $condition = true): static
    {
        $this->inlineSearch = $condition;

        return $this;
    }

    public function hasInlineSearch(): bool
    {
        return $this->isSearchable()
            && ! $this->isMultiple()
            && (bool) $this->evaluate($this->inlineSearch);
    }

    public function getOptionLayout(): string
    {
        $layout = PickerConfiguration::text($this->evaluate($this->optionLayout), 'optionLayout');

        if (! in_array($layout, ['list', 'grid'], true)) {
            throw new InvalidArgumentException("Select option layout [{$layout}] is not supported.");
        }

        return $layout;
    }

    public function getVariant(): string
    {
        return PickerConfiguration::choice($this->evaluate($this->variant), ['bordered', 'secondary', 'flat', 'faded', 'soft', 'underlined', 'item-card'], 'variant');
    }

    public function getColor(): ?string
    {
        $color = PickerConfiguration::optionalText($this->evaluate($this->color), 'color');

        return filled($color) ? (string) $color : null;
    }

    public function getChipColor(): string
    {
        return PickerConfiguration::text($this->evaluate($this->chipColor), 'chipColor');
    }
}
