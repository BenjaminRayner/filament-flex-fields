<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Closure;

/**
 * Current Select rendering and provider-loading policy.
 *
 * @mixin SelectField
 */
trait ResolvesSelectRenderingMode
{
    protected ?bool $usesRichOptionHtmlResolved = null;

    public function usesRichOptionHtml(): bool
    {
        if ($this->transformingRichOptionHtml !== null) {
            return $this->transformingRichOptionHtml;
        }
        // Evaluate runtime policies first: closures may depend on state, tenant or schema clone.
        if ($this->getOptionLayout() === 'grid' || $this->isHtmlAllowed() || $this->hasOptionView()) {
            return true;
        }
        if ($this->usesRichOptions !== null) {
            return (bool) $this->evaluate($this->usesRichOptions);
        }
        // Deferred/dynamic providers are authoritative; their shape is not a persistent field cache.
        if ($this->options instanceof Closure || $this->hasRelationship()) {
            return $this->optionsContainRichShape($this->getOptions());
        }

        return $this->usesRichOptionHtmlResolved ??= $this->optionsContainRichShape($this->getOptions());
    }

    protected function forgetUsesRichOptionHtmlCache(): void
    {
        $this->usesRichOptionHtmlResolved = null;
    }

    public function hasClientSideOptionList(): bool
    {
        if ($this->hasRelationship()) {
            return false;
        }

        if ($this->isPreloaded()) {
            return false;
        }

        if ($this->options instanceof Closure) {
            return false;
        }

        return true;
    }

    public function hasDynamicOptions(): bool
    {
        if ($this->hasClientSideOptionList()) {
            return false;
        }

        return parent::hasDynamicOptions();
    }

    public function hasInitialNoOptionsMessage(): bool
    {
        if ($this->hasClientSideOptionList()) {
            return false;
        }

        if ($this->options instanceof Closure) {
            return true;
        }

        return parent::hasInitialNoOptionsMessage();
    }

    public function getSearchableOptionFields(): array
    {
        $fields = parent::getSearchableOptionFields();

        if ($this->usesRichOptionHtml()) {
            $fields = array_values(array_unique([...$fields, 'description']));
        }

        return $fields;
    }
}
