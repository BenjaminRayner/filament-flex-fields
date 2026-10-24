<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Rendering\NativeFieldWrapper;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;

function fieldWrapperTree(string $html, string $owner): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.str_replace($owner, 'local-owner', $html), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $root = $xpath->query('//*[@data-wrapper-probe="value"]')->item(0);
    expect($root)->toBeInstanceOf(DOMElement::class);
    $normalize = function (DOMNode $node) use (&$normalize): ?array {
        if ($node instanceof DOMText) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $node->textContent));

            return $text === '' ? null : ['text' => $text];
        }
        if (! $node instanceof DOMElement) {
            return null;
        }
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            if ($attribute->name === 'class') {
                $classes = preg_split('/\s+/', trim($attribute->value), flags: PREG_SPLIT_NO_EMPTY);
                sort($classes);
                $attributes['class'] = implode(' ', $classes);
            } else {
                $attributes[$attribute->name] = $attribute->value;
            }
        }
        ksort($attributes);
        $children = [];
        foreach ($node->childNodes as $child) {
            if (($value = $normalize($child)) !== null) {
                $children[] = $value;
            }
        }

        return [$node->tagName, $attributes, $children];
    };

    return $normalize($root);
}

it('keeps native embedded and Blade field wrappers structurally equivalent', function (string $kind, string $mode): void {
    $field = ($kind === 'text' ? FlexTextInput::class : SelectField::class)::make('value')
        ->label('Project status')->required()->helperText('Public helper')->hint('Review before saving')
        ->extraFieldWrapperAttributes(['data-wrapper-probe' => 'value']);
    if ($field instanceof SelectField) {
        $field->options(['a' => 'Alpha']);
    }
    match ($mode) {
        'inline' => $field->inlineLabel(),
        'disabled' => $field->disabled(),
        'hidden' => $field->hiddenLabel(),
        default => $field,
    };
    TestableTranslatableForm::$formSchema = [$field];
    try {
        $fast = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => 'a']);
        $fastTree = fieldWrapperTree($fast->html(false), $fast->instance()->getId());
        $compositions = 0;
        view()->composer('filament-forms::components.field-wrapper', function () use (&$compositions): void {
            $compositions++;
        });
        $blade = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => 'a']);
        expect($compositions)->toBeGreaterThan(0)
            ->and(fieldWrapperTree($blade->html(false), $blade->instance()->getId()))->toEqual($fastTree);
    } finally {
        TestableTranslatableForm::$formSchema = [];
    }
})->with(['text', 'select'])->with(['plain', 'inline', 'disabled', 'hidden']);

it('does not replace a custom wrapper or an inline-field-label override', function (): void {
    $renderer = app(NativeFieldWrapper::class);
    $field = SelectField::make('value');
    expect($renderer->render($field, 'app::custom-field-wrapper', 'content', new ComponentAttributeBag))->toBeNull()
        ->and($renderer->render($field, 'filament-forms::field-wrapper', 'content', new ComponentAttributeBag, labelSrOnly: true))->toBeNull();
});
