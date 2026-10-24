<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CalculatorField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CurrencyField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NumberStepper;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Filament\Forms\Components\Field;
use Filament\Support\View\ComponentAttributeBag;
use Illuminate\View\Factory;

/** Request-local stock view metadata only; no component, callback or form data cache. */
final class StockFieldRenderer
{
    private const PREFIX = 'filament-flex-fields::';

    private const COMMON = ['components.field-wrapper', 'partials.load-stylesheet', 'partials.emit-assets'];

    private const MENU_VIEWS = [
        'components.selection-indicator',
        'forms.components.partials.select-field.menu-shell', 'forms.components.partials.select-field.option-list',
        'forms.components.partials.select-field.option-label', 'forms.components.partials.select-field.empty-create',
        'forms.components.partials.select-field.load-more', 'forms.components.partials.select-field-headless-option',
        'forms.components.partials.picker-request-error', 'forms.components.partials.location.country-groups',
    ];

    private const SELECT_VIEWS = [
        'forms.components.select-field', 'forms.components.partials.select-field-headless',
        'forms.components.partials.select-field.trigger', 'forms.components.partials.select-field.trigger-ssr',
        'forms.components.partials.select-field.menu-shell', 'forms.components.partials.select-field.option-list', 'forms.components.partials.select-field.option-label',
        'forms.components.partials.select-field.empty-create', 'forms.components.partials.select-field.load-more',
        'forms.components.partials.select-field-headless-option', 'forms.components.partials.picker-request-error',
        'forms.components.partials.location.country-groups', 'forms.components.partials.tag-pill-remove-icon',
        'components.lazy-alpine-mount', 'components.selection-indicator',
    ];

    private const VIEWS = [
        NumberStepper::class => [
            'forms.components.number-stepper',
            'forms.components.partials.number-stepper-button',
            'forms.components.partials.number-stepper.display',
        ],
        CalculatorField::class => [
            'forms.components.calculator-field', 'forms.components.calculator.panel',
            'forms.components.flex-text-input-field', 'components.text-input.shell', 'components.text-input.control',
            'components.text-input.actions', 'components.text-input.feedback', 'components.text-input.password-requirements',
            'components.text-input.tools.actions', 'components.text-input.tools.clear', 'components.text-input.tools.dictation',
            'forms.components.partials.flex-text-input-emoji-picker',
        ],
        FlexTextareaField::class => [
            'forms.components.flex-textarea-field',
            'forms.components.partials.flex-textarea-emoji-picker',
            'forms.components.partials.flex-textarea-toolbar-select',
            'components.select-control', 'components.select-menu', 'components.selection-indicator',
            'forms.components.partials.select-field.menu-shell', 'forms.components.partials.select-field.option-list',
            'forms.components.partials.select-field.option-label', 'forms.components.partials.select-field.empty-create',
            'forms.components.partials.select-field.load-more', 'forms.components.partials.select-field-headless-option',
            'forms.components.partials.picker-request-error', 'forms.components.partials.location.country-groups',
        ],
        FlexTextInput::class => [
            'forms.components.flex-text-input-field', 'components.text-input.shell', 'components.text-input.control',
            'components.text-input.actions', 'components.text-input.feedback', 'components.text-input.password-requirements',
            'components.text-input.tools.actions', 'components.text-input.tools.clear', 'components.text-input.tools.dictation',
            'forms.components.partials.flex-text-input-emoji-picker',
        ],
        SelectField::class => self::SELECT_VIEWS,
        CountryField::class => self::SELECT_VIEWS,
        TimezoneField::class => self::SELECT_VIEWS,
        PhoneField::class => ['forms.components.phone-field', 'components.select-menu', ...self::MENU_VIEWS],
        CurrencyField::class => ['forms.components.currency-field', 'forms.components.currency.amount-editor',
            'forms.components.currency.option', 'components.select-menu', ...self::MENU_VIEWS],
        FontSelect::class => ['forms.components.font-select', 'forms.components.partials.font-select.option',
            'components.select-menu', ...self::MENU_VIEWS],
        IconPickerField::class => ['forms.components.icon-picker-field', 'components.icon-picker.control',
            'forms.components.partials.icon-select.sets', 'forms.components.partials.icon-select.option',
            'forms.components.partials.icon-select.loading', 'forms.components.partials.icon-select.loading-more',
            'components.select-menu', ...self::MENU_VIEWS],
    ];

    /** @var array<class-string, list<string>> */
    private array $methods = [];

    public function render(Field $field): ?string
    {
        $views = self::VIEWS[$field::class] ?? null;
        if ($views === null || ! class_exists(ComponentAttributeBag::class) || $field->getView() !== self::PREFIX.$views[0]) {
            return null;
        }

        /** @var Factory $factory */
        $factory = view();
        $events = app('events');
        // Dynamic headless slots can also be supplied as shared Blade data.
        // They may use any public method, so keep the complete native context.
        if ($field instanceof SelectField || $field instanceof FlexTextareaField
            || in_array($field::class, [PhoneField::class, CurrencyField::class, IconPickerField::class], true)) {
            foreach (['headlessOptionView', 'headlessLoadingView', 'headlessLoadMoreView', 'headlessRendererData'] as $key) {
                if (! empty($factory->getShared()[$key])) {
                    return null;
                }
            }
        }
        $variables = [];
        foreach ([...$views, ...self::COMMON] as $partial) {
            $view = self::PREFIX.$partial;
            // Check on every render: an application may register a composer or
            // replace namespace hints after an earlier field has rendered.
            if (ViewCustomization::hasHooks($view, $events)) {
                return null;
            }
            $path = dirname(__DIR__, 3).'/resources/views/'.str_replace('.', '/', $partial).'.blade.php';
            if (realpath($factory->getFinder()->find($view)) !== realpath($path)) {
                return null;
            }
            if (! isset($this->methods[$field::class])) {
                $source = file_get_contents($path);
                if ($source === false) {
                    return null;
                }
                // New literal dependencies must be audited before they can use
                // the reduced context. Unknown dependencies fail back safely.
                preg_match_all('/@include(?:If|When|Unless|First)?\(\s*([^\r\n]*)/', $source, $includes);
                $known = array_fill_keys([...$views, ...self::COMMON], true);
                foreach ($includes[1] as $include) {
                    if (preg_match('/^[\'"]([^\'"]+)[\'"]/', $include, $literal)) {
                        if (! str_starts_with($literal[1], self::PREFIX) || ! isset($known[substr($literal[1], strlen(self::PREFIX))])) {
                            return null;
                        }
                    } elseif (! preg_match('/^\$(?:headlessOptionView|headlessLoadingView|headlessLoadMoreView)\b/', $include)) {
                        return null;
                    }
                    preg_match_all('/[\'"]filament-flex-fields::([^\'"]+)[\'"]/', $include, $dependencies);
                    foreach ($dependencies[1] as $dependency) {
                        if (! isset($known[$dependency])) {
                            return null;
                        }
                    }
                }
                preg_match_all('/<x-filament-flex-fields::([a-zA-Z0-9_.-]+)/', $source, $components);
                foreach ($components[1] as $component) {
                    if (! isset($known['components.'.$component])) {
                        return null;
                    }
                }
                preg_match_all('/\$fffPresentations->view\(\s*[\'"]([^\'"]+)[\'"]/', $source, $presentations);
                foreach ($presentations[1] as $presentation) {
                    if ($presentation !== 'filament::input.wrapper'
                        && (! str_starts_with($presentation, self::PREFIX)
                            || ! isset($known['components.'.substr($presentation, strlen(self::PREFIX))]))) {
                        return null;
                    }
                }
                // A lexical superset intentionally retains names in strings too.
                // Appearance and required methods come from the same Blade source.
                preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $matches);
                foreach ($matches[1] as $variable) {
                    $variables[$variable] = true;
                }
            }
        }
        $this->methods[$field::class] ??= array_values(array_intersect(get_class_methods($field), array_keys($variables)));
        $data = ['field' => $field, 'attributes' => new ComponentAttributeBag, 'fffStockViewContext' => true];
        foreach ($this->methods[$field::class] as $method) {
            $data[$method] = $field->{$method}(...);
        }

        return $factory->make(self::PREFIX.$views[0], [...$data, ...$field->getExtraViewData()])->render();
    }
}
