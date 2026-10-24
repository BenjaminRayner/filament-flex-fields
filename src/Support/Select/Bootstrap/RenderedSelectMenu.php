<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select\Bootstrap;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewirePlanProjection;
use Bjanczak\FilamentFlexFields\Support\Rendering\PortalTemplateDictionary;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedPortalTemplate;
use Bjanczak\FilamentFlexFields\Support\Rendering\ViewCustomization;
use Filament\Forms\Components\Field;
use Filament\Support\SupportServiceProvider;
use Illuminate\View\Factory;
use ReflectionClass;

/** Request-local stock markup only; never stores a field, selection or option list. */
final class RenderedSelectMenu
{
    private const VIEW = 'filament-flex-fields::forms.components.partials.select-field.menu-shell';

    private const PARTIALS = [
        self::VIEW,
        'filament-flex-fields::forms.components.partials.select-field.option-list',
        'filament-flex-fields::forms.components.partials.select-field.option-label',
        'filament-flex-fields::forms.components.partials.select-field.empty-create',
        'filament-flex-fields::forms.components.partials.select-field.load-more',
        'filament-flex-fields::forms.components.partials.select-field-headless-option',
        'filament-flex-fields::forms.components.partials.picker-request-error',
        'filament::components.loading-indicator',
    ];

    /** @var array<string, string> */
    private array $markup = [];

    /** @param array<string, mixed> $data
     * @return array{id: ?string, html: string}
     */
    public function transport(SelectField $field, array $data): array
    {
        $html = $this->render($field, $data);
        if (LivewirePlanProjection::dictionaryForRender() === null || ! in_array($html, $this->markup, true)) {
            return ['id' => null, 'html' => $html];
        }
        $id = app(PortalTemplateDictionary::class)->tryRegister(new RenderedPortalTemplate($html));

        return ['id' => $id, 'html' => $id === null ? $html : ''];
    }

    /** @param array<string, mixed> $data */
    public function render(SelectField $field, array $data): string
    {
        $data['field'] = $field;
        if (! in_array($field::class, [SelectField::class, CountryField::class, TimezoneField::class], true) || ! $this->usesStockViews()) {
            return view(self::VIEW, $data)->render();
        }
        // Any custom slot/renderer keeps the ordinary, complete Blade scope.
        foreach (['toolbar', 'panelStyle', 'panelClasses', 'headlessOptionView', 'headlessLoadingView',
            'headlessLoadMoreView', 'headlessRendererData', 'headlessEmptyIconHtml', 'headlessRecycleRows'] as $key) {
            if (! empty($data[$key])) {
                return view(self::VIEW, $data)->render();
            }
        }

        $shape = array_intersect_key($data, array_flip([
            'isSearchable', 'isInlineSearch', 'isGridLayout', 'isHtmlAllowed', 'canOptionLabelsWrap',
            'useRichListDropdownLayout', 'headlessSearchClearIconHtml', 'headlessSmartCreateIconHtml',
        ]));
        $shape['countrySize'] = $field instanceof CountryField ? $field->getSize() : null;
        $shape['showSelectionIndicator'] = $field->showsSelectionIndicator();
        $shape['optionDescriptionPosition'] = $field->getOptionDescriptionPosition();
        $key = hash('sha256', serialize([$shape, app()->getLocale(),
            __('filament-flex-fields::default.select_field.load_error'),
            __('filament-flex-fields::default.select_field.rate_limited'),
            __('filament-flex-fields::default.select_field.retry'),
            __('filament-flex-fields::default.select_field.clear_search'),
        ]));
        if (isset($this->markup[$key])) {
            return $this->markup[$key];
        }

        // Only known immutable presentation enters the shared render. Identity,
        // labels and ARIA targets bind from the individual Alpine owner on mount.
        $html = view(self::VIEW, [...$shape, 'field' => $field,
            'isUserSelectField' => false, 'sharedMenuMarkup' => true])->render();
        // Stock menu markup contains no preformatted content or option values.
        // Keep newline whitespace (including inline spacing), omit only source
        // indentation. Never run this over custom renderers or user HTML.
        $html = preg_replace('/\r?\n[ \t]+/', "\n", $html) ?? $html;
        if (count($this->markup) < 64) {
            $this->markup[$key] = $html;
        }

        return $html;
    }

    /** @param array<string, mixed> $data
     * @return array{id: ?string, html: string}
     */
    public function composedTransport(?Field $owner, array $data): array
    {
        $families = [
            PhoneField::class => ['forms.components.phone-field'],
            CurrencyField::class => ['forms.components.currency-field', 'forms.components.currency.option'],
            FontSelect::class => ['forms.components.font-select', 'forms.components.partials.font-select.option'],
            IconPickerField::class => ['forms.components.icon-picker-field', 'components.icon-picker.control',
                'forms.components.partials.icon-select.option', 'forms.components.partials.icon-select.sets',
                'forms.components.partials.icon-select.loading', 'forms.components.partials.icon-select.loading-more'],
        ];
        $views = $owner === null ? null : ($families[$owner::class] ?? null);
        $safe = $views !== null && $this->usesStockViews();
        if ($safe) {
            /** @var Factory $factory */
            $factory = app('view');
            foreach (['components.select-menu', ...$views] as $view) {
                $name = 'filament-flex-fields::'.$view;
                $expected = dirname(__DIR__, 4).'/resources/views/'.str_replace('.', '/', $view).'.blade.php';
                if (ViewCustomization::hasHooks($name, app('events')) || realpath($factory->getFinder()->find($name)) !== realpath($expected)) {
                    $safe = false;
                    break;
                }
            }
        }
        if (! $safe || LivewirePlanProjection::dictionaryForRender() === null) {
            return ['id' => null, 'html' => view(self::VIEW, $data)->render()];
        }
        $data['sharedMenuMarkup'] = true;
        $data['showSelectionIndicator'] = ($data['field'] ?? null)?->showsSelectionIndicator() ?? true;
        $data['optionDescriptionPosition'] = ($data['field'] ?? null)?->getOptionDescriptionPosition();
        $shape = array_intersect_key($data, array_flip(['isSearchable', 'isInlineSearch', 'isGridLayout', 'isUserSelectField',
            'useRichListDropdownLayout', 'headlessOptionView', 'headlessRecycleRows', 'headlessLoadingView',
            'headlessLoadMoreView', 'headlessRendererData', 'headlessEmptyIconHtml', 'canOptionLabelsWrap',
            'headlessSearchClearIconHtml', 'headlessSmartCreateIconHtml', 'panelClasses', 'panelStyle',
            'showSelectionIndicator', 'optionDescriptionPosition']));
        $shape['toolbar'] = (string) ($data['toolbar'] ?? '');
        $key = hash('sha256', serialize([$owner::class, $shape, app()->getLocale()]));
        $html = $this->markup[$key] ?? view(self::VIEW, $data)->render();
        // Large/custom presentation stays owner-rendered rather than exhausting
        // the concurrent fragment transport budget.
        if (strlen($html) > 65536 || count($this->markup) >= 64) {
            return ['id' => null, 'html' => $html];
        }
        $this->markup[$key] = $html;
        $id = app(PortalTemplateDictionary::class)->tryRegister(new RenderedPortalTemplate($html));

        return ['id' => $id, 'html' => $id === null ? $html : ''];
    }

    private function usesStockViews(): bool
    {
        /** @var Factory $factory */
        $factory = view();
        $finder = $factory->getFinder();
        $supportProvider = (new ReflectionClass(SupportServiceProvider::class))->getFileName();
        if ($supportProvider === false) {
            return false;
        }
        foreach (self::PARTIALS as $view) {
            if (ViewCustomization::hasHooks($view, app('events'))) {
                return false;
            }
            $path = str_replace('\\', '/', $finder->find($view));
            $root = str_starts_with($view, 'filament-flex-fields::')
                ? str_replace('\\', '/', dirname(__DIR__, 4).'/resources/views/')
                : str_replace('\\', '/', dirname($supportProvider, 2).'/resources/views/');
            // realpath handles Composer path repositories without trusting an
            // application override merely because its namespace is unchanged.
            $realRoot = realpath($root);
            $realPath = realpath($path);
            if ($realRoot === false || $realPath === false || ! str_starts_with($realPath, $realRoot.'/')) {
                return false;
            }
        }

        return true;
    }
}
