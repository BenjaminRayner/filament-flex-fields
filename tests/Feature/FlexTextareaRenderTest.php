<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableVerificationCodeForm;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableVerificationCodeForm::$formSchema = [];
});

it('renders native wire model and separate owners with optional shared Select', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        FlexTextareaField::make('message')->default('Hello 😀')->characterCounter()->maxLength(100)->emojiPicker()->toolbarSelect('model', ['a' => 'Model A', 'b' => 'Model B']),
        FlexTextareaField::make('plain')->default('Plain')->autosize(false)->readOnly(),
    ];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-fff-asset-consumer="flex-textarea"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-fff-asset-consumer="select-field"]')->length)->toBe(1)
        ->and($xpath->query('//textarea[@*[name()="wire:model"]]')->length)->toBe(2)
        ->and($xpath->query('//textarea[@readonly]')->length)->toBe(1)
        ->and($html)->toContain('Hello 😀', '7/100', 'role="combobox"', 'fffHeadlessSelectField')
        ->not->toContain('x-model="state"', '$entangle(\'data.message\'', 'x-init="init()"');
});

it('validates raw textarea data through actual form submission', function (mixed $value): void {
    TestableVerificationCodeForm::$formSchema = [FlexTextareaField::make('message')->required()->minLength(3)->maxLength(10)];
    Livewire::test(TestableVerificationCodeForm::class)->set('data.message', $value)->call('save')->assertHasErrors(['data.message']);
})->with(['array' => [['Hello']], 'integer' => [1234], 'boolean' => [true], 'empty' => [''], 'too short' => ['ab'], 'too long' => ['abcdefghijk']]);

it('preserves native whitespace and line breaks with a stable editing owner', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexTextareaField::make('message')->maxLength(100)->characterCounter()];
    $component = Livewire::test(TestableVerificationCodeForm::class);
    $owner = static function (string $html): string {
        $dom = new DOMDocument;
        @$dom->loadHTML($html);

        return (new DOMXPath($dom))->query('//*[@x-fff-load][contains(@class, "fff-flex-textarea")]')->item(0)->getAttribute('wire:key');
    };
    $original = $owner($component->html(false));
    $component->set('data.message', " Hello\n😀 ")->call('save')->assertHasNoErrors();
    expect($component->get('saved.message'))->toBe(" Hello\n😀 ")
        ->and($owner($component->html(false)))->toBe($original);
    $component->set('data.message', '')->call('$refresh');
    expect($owner($component->html(false)))->toBe($original);
});

it('replaces an ignored input only when its native policy changes', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexTextareaField::make('message')->disabled(fn (Get $get): bool => (bool) $get('locked'))];
    $component = Livewire::test(TestableVerificationCodeForm::class);
    $html = $component->html(false);
    $component->set('data.locked', true);
    expect($component->html(false))->toContain('is-disabled')->not->toBe($html);
});

it('keeps explicit action disable policy and unique server loading targets', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        FlexTextareaField::make('one')->default('Hello')->submitAction(Action::make('send')->disabled()->action(fn () => null)),
        FlexTextareaField::make('two')->default('World')->submitAction(Action::make('send')->action(fn () => null)),
    ];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    expect($html)->toContain('!canSubmit || true', 'one', 'two');
});

it('renders the dictation button before JavaScript without hiding or shifting the toolbar', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexTextareaField::make('message')->speechDictation()];
    $dom = new DOMDocument;
    @$dom->loadHTML(Livewire::test(TestableVerificationCodeForm::class)->html(false));
    $button = (new DOMXPath($dom))->query('//button[contains(@class, "fff-flex-textarea__dictation-btn")]')->item(0);
    expect($button)->not->toBeNull()
        ->and($button->hasAttribute('x-cloak'))->toBeFalse()
        ->and($button->hasAttribute('x-show'))->toBeFalse()
        ->and($button->hasAttribute('disabled'))->toBeTrue()
        ->and($button->getAttribute('x-bind:disabled'))->toBe('!speechSupported');
});

it('supports native opt-in trimming and nullable empty values', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexTextareaField::make('message')->trim()];
    $component = Livewire::test(TestableVerificationCodeForm::class);
    $component->set('data.message', '  Hello  ')->call('save')->assertHasNoErrors();
    expect($component->get('saved.message'))->toBe('Hello');
    $component->set('data.message', null)->call('save')->assertHasNoErrors();
    expect($component->get('saved.message'))->toBeNull();
});
