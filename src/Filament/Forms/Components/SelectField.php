<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\InteractsWithRestrictedModelQueries;
use Bjanczak\FilamentFlexFields\Concerns\RendersStockFieldView;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\HasSelectFieldIcons;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectDependencies;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectPresentation;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectSmartSuggest;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectStatePolicy;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ConfiguresSelectSurface;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\InteractsWithSelectAsyncSearch;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\InteractsWithSelectHeadlessRuntime;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\InteractsWithSelectOptionTransport;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\InteractsWithSelectTriggerPresentation;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\RendersSelectOptionViews;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\TransformsSelectRichOptions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SelectField\ValidatesSelectValues;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\FieldMounted;
use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;

/** Public fluent API. Transport, state policy, validation and presentation have explicit owners. */
class SelectField extends Select
{
    use ConfiguresSelectDependencies;
    use ConfiguresSelectPresentation;
    use ConfiguresSelectSmartSuggest;
    use ConfiguresSelectStatePolicy;
    use ConfiguresSelectSurface;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasSelectFieldIcons;
    use InteractsWithRestrictedModelQueries;
    use InteractsWithSelectAsyncSearch;
    use InteractsWithSelectHeadlessRuntime;
    use InteractsWithSelectOptionTransport;
    use InteractsWithSelectTriggerPresentation;
    use RendersSelectOptionViews;
    use RendersStockFieldView;
    use TransformsSelectRichOptions;
    use ValidatesSelectValues;

    protected string $view = 'filament-flex-fields::forms.components.select-field';

    public function getAssetComponent(): string
    {
        return 'select-field';
    }

    public function isSelectControlDisabled(): bool
    {
        return $this->isDisabled();
    }

    public function getSelectCatalogMarkup(): HtmlString
    {
        return new HtmlString('');
    }

    /** Cloned schema fields own their caches and cannot inherit an in-progress render. */
    public function __clone(): void
    {
        $this->selectSearchCache = null;
        $this->richOptionJsTransformer = null;
        $this->transformingRichOptionHtml = null;
        $this->cachedSelectedRecord = null;
        if (FilamentApi::hasProperty(static::class, 'cachedRelationshipRecords')) {
            $this->cachedRelationshipRecords = null;
        }
        if (FilamentApi::hasProperty(static::class, 'cachedRelationshipOptions')) {
            $this->cachedRelationshipOptions = null;
        }
        $this->allowDeferredOptionResolution = false;
        $this->forgetUsesRichOptionHtmlCache();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->native(false);

        $this->transformOptionsForJsUsing(function (SelectField $component, array $options): array {
            return $component->transformRichOptionsForJs($options);
        });

        parent::selectablePlaceholder(function (SelectField $component): bool {
            return $component->isClearable();
        });

        $this->afterStateHydrated(function (SelectField $component): void {
            app(ActivityDispatcher::class)->dispatch(new FieldMounted($component->getName(), 'select'));
        });

        $this->configureSelectValueValidation();
    }
}
