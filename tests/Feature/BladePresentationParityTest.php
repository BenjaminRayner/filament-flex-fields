<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Actions\Action;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;

function presentationDom(string $html): array
{
    $document = new DOMDocument;
    $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $normalize = function (DOMNode $node) use (&$normalize): mixed {
        if ($node instanceof DOMText) {
            return trim($node->textContent) === '' ? null : ['text' => $node->textContent];
        }
        if (! $node instanceof DOMElement) {
            return null;
        }
        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
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

    return $normalize($document->documentElement);
}

it('keeps the exact control DOM when replacing anonymous tag plumbing', function (string $mode): void {
    $select = str_starts_with($mode, 'select');
    $field = $select ? SelectField::make('value')->options(['a' => 'Alpha', 'b' => 'Beta']) : FlexTextInput::make('value');
    match ($mode) {
        'text-affixes' => $field->prefix('https://')->suffix('.test')->prefixIcon('heroicon-o-link')->suffixIcon('heroicon-o-check'),
        'text-tools' => $field->emojiPicker()->speechDictation()->iconPicker('icon'),
        'text-password' => $field->password()->revealable()->copyable(),
        'text-disabled' => $field->disabled()->helperText('Helper'),
        'text-action' => $field->prefixAction(Action::make('inspect')->icon('heroicon-o-eye')),
        'select-multiple' => $field->multiple()->searchable(),
        'select-native' => $field->native(),
        'select-affixes' => $field->prefix('From')->suffix('To')->prefixIcon('heroicon-o-user'),
        'select-disabled' => $field->disabled(),
        default => $field,
    };
    TestableTranslatableForm::$formSchema = [$field];
    try {
        $form = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => $mode === 'select-multiple' ? ['a'] : 'a']);
        $field = $form->instance()->getSchema('form')->getComponents()[0];
        view()->share('errors', new ViewErrorBag);
        $after = $field->render()->render();
        view()->addNamespace('presentation-regression', __DIR__.'/../fixtures/rendering');
        $before = $field->view('presentation-regression::'.($select ? 'select-tags' : 'text-input-tags'))->render()->render();
        expect(presentationDom($after))->toEqual(presentationDom($before));
    } finally {
        TestableTranslatableForm::$formSchema = [];
    }
})->with(['text', 'text-affixes', 'text-tools', 'text-password', 'text-disabled', 'text-action',
    'select', 'select-multiple', 'select-native', 'select-affixes', 'select-disabled']);
