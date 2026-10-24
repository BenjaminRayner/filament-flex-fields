<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Rendering\StockFieldRenderer;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Forms\Components\Field;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;
use Livewire\Livewire;

afterEach(function (): void {
    TestableTranslatableForm::$formSchema = [];
});

function stockRendererField(Field $field, mixed $value = 'a'): Field
{
    TestableTranslatableForm::$formSchema = [$field];
    $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => $value]);
    // Direct rendering below happens outside Livewire's temporary error scope.
    view()->share('errors', new ViewErrorBag);

    return $form->instance()->getSchema('form')->getComponents()[0];
}

it('renders exactly the same Blade output through stock and complete public contexts', function (string $mode): void {
    $field = match ($mode) {
        'text' => FlexTextInput::make('value'),
        'affixes' => FlexTextInput::make('value')->prefix('https://')->suffix('.test')->prefixIcon('heroicon-o-link')->suffixIcon('heroicon-o-check'),
        'password' => FlexTextInput::make('value')->password()->revealable()->copyable(),
        'tools' => FlexTextInput::make('value')->emojiPicker()->speechDictation(),
        'readonly' => FlexTextInput::make('value')->readOnly()->datalist(['a', 'b']),
        'select' => SelectField::make('value')->options(['a' => 'Alpha', 'b' => 'Beta']),
        'multiple' => SelectField::make('value')->options(['a' => 'Alpha', 'b' => 'Beta'])->multiple()->searchable(),
        'native' => SelectField::make('value')->options(['a' => 'Alpha', 'b' => 'Beta'])->native(),
        'rich' => SelectField::make('value')->options(['a' => ['label' => 'Alpha', 'description' => 'Summary', 'icon' => 'heroicon-o-user']]),
        'async' => SelectField::make('value')->searchable()->getSearchResultsUsing(fn (string $search): array => ['a' => 'Alpha'])->getOptionLabelUsing(fn (): string => 'Alpha'),
    };
    $field = stockRendererField($field->label('Example')->required()->helperText('Help')->hint('Hint'), $mode === 'multiple' ? ['a'] : 'a');
    $fast = app(StockFieldRenderer::class)->render($field);
    expect($fast)->not->toBeNull()->and($field->render()->render())->toBe($fast);
})->with(['text', 'affixes', 'password', 'tools', 'readonly', 'select', 'multiple', 'native', 'rich', 'async']);

it('preserves the complete public view API on render', function (string $kind): void {
    $field = stockRendererField($kind === 'text' ? FlexTextInput::make('value') : SelectField::make('value')->options(['a' => 'Alpha']));
    $field->toHtml();
    $data = $field->render()->getData();
    foreach (['getName', 'label', 'viewData', 'configure', 'getExtraViewData'] as $method) {
        expect($data[$method])->toBeInstanceOf(Closure::class);
    }
    expect($data['getName']())->toBe('value')->and($data['field'])->toBe($field);
})->with(['text', 'select']);

it('preserves modifications to a view returned by the native render API', function (): void {
    $field = stockRendererField(FlexTextInput::make('value'));
    $field->render()->with('getLabel', fn (): string => 'Custom view label');
    expect($field->toHtml())->toContain('aria-label="Custom view label"');
});

it('resolves selected labels once in a stock render and again on the next render', function (bool $multiple): void {
    $calls = 0;
    $resolver = function () use (&$calls, $multiple): array|string {
        $calls++;

        return $multiple ? ['a' => 'Resolved label'] : 'Resolved label';
    };
    $field = SelectField::make('value')->options(['a' => 'Alpha'])->multiple($multiple);
    $multiple ? $field->getOptionLabelsUsing($resolver) : $field->getOptionLabelUsing($resolver);
    $field = stockRendererField($field, $multiple ? ['a'] : 'a');
    $calls = 0;
    expect($field->toHtml())->toContain('Resolved label');
    expect($calls)->toBe(1);
    $field->toHtml();
    expect($calls)->toBe(2);
})->with([false, true]);

it('keeps empty defaults placeholders and item-card presentation equivalent', function (string $mode): void {
    $field = SelectField::make('value')->options(['a' => 'Alpha'])->placeholder('Choose <safe>');
    if ($mode === 'default') {
        $field->default('a');
    } elseif ($mode === 'multiple') {
        $field->multiple();
    } elseif ($mode === 'item-card') {
        $field->variant('item-card');
    }
    $field = stockRendererField($field, $mode === 'item-card' ? 'a' : null);
    expect(app(StockFieldRenderer::class)->render($field))->toBe($field->render()->render());
})->with(['empty', 'default', 'multiple', 'item-card']);

it('falls back when a composer or creator is registered after a stock render', function (string $event): void {
    $field = stockRendererField(FlexTextInput::make('value'));
    expect(app(StockFieldRenderer::class)->render($field))->not->toBeNull();
    $seen = false;
    view()->{$event}('filament-flex-fields::components.text-input.feedback', function (View $view) use (&$seen): void {
        $seen = isset($view->getData()['viewData'], $view->getData()['label']);
    });
    expect(app(StockFieldRenderer::class)->render($field))->toBeNull();
    $field->toHtml();
    expect($seen)->toBeTrue();
})->with(['composer', 'creator']);

it('keeps view data callbacks on the native path and evaluates them once', function (): void {
    $calls = 0;
    $field = stockRendererField(FlexTextInput::make('value'));
    $field->viewData(function () use (&$calls): array {
        $calls++;

        return ['custom' => 'value'];
    });
    $field->toHtml();
    expect($calls)->toBe(1);
});

it('honors wildcard composers rather than rendering a reduced context', function (): void {
    $field = stockRendererField(SelectField::make('value')->options(['a' => 'Alpha']));
    $renderer = app(StockFieldRenderer::class);
    expect($renderer->render($field))->not->toBeNull();
    view()->composer('filament-flex-fields::*', static function (): void {});
    expect($renderer->render($field))->toBeNull();
});

it('discards stock metadata when the request scope is reset', function (): void {
    $renderer = app(StockFieldRenderer::class);
    app()->forgetScopedInstances();
    expect(app(StockFieldRenderer::class))->not->toBe($renderer);
});

it('does not project subclasses or changed root views', function (): void {
    $renderer = app(StockFieldRenderer::class);
    expect($renderer->render(new class('value') extends FlexTextInput {}))->toBeNull()
        ->and($renderer->render(SelectField::make('value')->view('application::custom')))->toBeNull();
});

it('keeps shared custom headless renderers on the complete context', function (): void {
    $renderer = app(StockFieldRenderer::class);
    view()->share('headlessOptionView', 'application::custom-row');
    expect($renderer->render(SelectField::make('value')))->toBeNull();
});

it('does not cache an owner, state or request-specific data between renders', function (): void {
    $field = stockRendererField(FlexTextInput::make('value'), 'initial');
    $renderer = app(StockFieldRenderer::class);
    expect($renderer->render($field))->toContain('value="initial"');
    $field->state('updated');
    expect($renderer->render($field))->toContain('value="updated"')->not->toContain('value="initial"');
    $clone = clone $field;
    $clone->state('cloned');
    expect($renderer->render($clone))->toContain('value="cloned"');
});

it('falls back for a published nested partial and resumes after the override is removed', function (): void {
    $field = stockRendererField(SelectField::make('value')->options(['a' => 'Alpha']));
    $renderer = app(StockFieldRenderer::class);
    expect($renderer->render($field))->not->toBeNull();
    $finder = view()->getFinder();
    $hints = $finder->getHints()['filament-flex-fields'];
    $directory = sys_get_temp_dir().'/fff-stock-override-'.bin2hex(random_bytes(8));
    $path = $directory.'/forms/components/partials/select-field/trigger-ssr.blade.php';
    mkdir(dirname($path), 0700, true);
    file_put_contents($path, '{{ $getName() }}:{{ isset($viewData) ? "complete-context" : "missing" }}');
    try {
        view()->prependNamespace('filament-flex-fields', $directory);
        $finder->flush();
        expect($renderer->render($field))->toBeNull()->and($field->toHtml())->toContain('value:complete-context');
    } finally {
        view()->replaceNamespace('filament-flex-fields', $hints);
        $finder->flush();
        unlink($path);
        for ($folder = dirname($path); str_starts_with($folder, $directory); $folder = dirname($folder)) {
            rmdir($folder);
        }
    }
    expect($renderer->render($field))->not->toBeNull();
});
