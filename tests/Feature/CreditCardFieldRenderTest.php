<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableCreditCardForm;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    MediaCaptureOs::resetRuntimeState();
    config(['filament-flex-fields.media_capture.pci.never_store_pan' => false, 'filament-flex-fields.media_capture.pci.require_tokenization' => false]);
    TestableCreditCardForm::$formSchema = [CreditCardField::make('card')];
});
afterEach(function () {
    TestableCreditCardForm::$formSchema = [];
    MediaCaptureOs::resetRuntimeState();
});

function creditCardPayload(): array
{
    return ['number' => '4242424242424242', 'name' => 'Test Card', 'expiry' => '12/30', 'cvv' => '123'];
}

it('renders exactly four shared input controls and one card consumer without double initialization', function () {
    $html = Livewire::test(TestableCreditCardForm::class, ['initial' => ['card' => creditCardPayload()]])->html();
    expect(substr_count($html, 'fff-flex-text-input__shell'))->toBe(4)
        ->and(substr_count($html, 'data-card-input='))->toBe(4)
        ->and($html)->toContain('data-card-preview="number"', 'data-fff-asset', 'cc-csc')
        ->not->toContain('x-init="init()"', 'fff-credit-card__input');
});

it('saves formatted valid data and never persists cvv', function () {
    $form = Livewire::test(TestableCreditCardForm::class)->set('data.card', [
        ...creditCardPayload(), 'number' => '4242 4242 4242 4242', 'expiry' => '1230',
    ])->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['card' => ['number' => '4242424242424242', 'name' => 'Test Card', 'expiry' => '12/30']]);
});

it('rejects forged or invalid payloads at actual submission', function (mixed $value) {
    Livewire::test(TestableCreditCardForm::class)->set('data.card', $value)->call('save')->assertHasErrors(['data.card']);
})->with([
    'string' => ['bad'], 'boolean' => [true], 'nested number' => [['number' => []]],
    'unknown key' => [[...creditCardPayload(), 'admin' => true]],
    'forged token' => [['token' => 'foreign', 'last4' => '4242']],
    'too long cvv' => [[...creditCardPayload(), 'cvv' => '12345']],
    'cvv letters' => [[...creditCardPayload(), 'cvv' => '1a23']],
    'number letters' => [[...creditCardPayload(), 'number' => 'a4242424242424242']],
    'number too long' => [[...creditCardPayload(), 'number' => '42424242424242424242']],
    'number checksum' => [[...creditCardPayload(), 'number' => '4242424242424241']],
    'expiry malformed' => [[...creditCardPayload(), 'expiry' => '12a30']],
    'expired' => [[...creditCardPayload(), 'expiry' => '01/20']],
    'expiry month' => [[...creditCardPayload(), 'expiry' => '13/30']],
    'name oversized' => [[...creditCardPayload(), 'name' => str_repeat('a', 201)]],
    'control bytes' => [[...creditCardPayload(), 'name' => "Test\0Card"]],
]);

it('rejects required null and every missing required subfield', function () {
    TestableCreditCardForm::$formSchema = [CreditCardField::make('card')->required()];
    foreach ([null, [], ...array_map(fn ($key) => [...creditCardPayload(), $key => ''], ['number', 'name', 'expiry', 'cvv'])] as $value) {
        Livewire::test(TestableCreditCardForm::class)->set('data.card', $value)->call('save')->assertHasErrors(['data.card']);
    }
});

it('tokenizes once across validation and dehydration and separates field instances', function () {
    config(['filament-flex-fields.media_capture.pci.never_store_pan' => true]);
    $calls = 0;
    MediaCaptureOs::registerTokenizeCreditCardCallback(function (string $pan) use (&$calls): string {
        $calls++;

        return 'tok_test';
    });
    $form = Livewire::test(TestableCreditCardForm::class)->set('data.card', creditCardPayload())->call('save')->assertHasNoErrors();
    expect($calls)->toBe(1)->and($form->get('saved'))->toBe(['card' => [
        'token' => 'tok_test', 'last4' => '4242', 'name' => 'Test Card', 'expiry' => '12/30',
    ]]);
    CreditCardField::make('other')->dehydrateStateForStorage(creditCardPayload());
    expect($calls)->toBe(2);
});

it('turns tokenizer exceptions into validation errors without raw fallback', function () {
    MediaCaptureOs::registerTokenizeCreditCardCallback(fn () => throw new RuntimeException('Provider unavailable'));
    $form = Livewire::test(TestableCreditCardForm::class)->set('data.card', creditCardPayload())->call('save')->assertHasErrors(['data.card']);
    expect($form->get('saved'))->toBeNull();
});

it('validates readonly submissions and respects native disabled dehydration', function () {
    TestableCreditCardForm::$formSchema = [CreditCardField::make('card')->readOnly()];
    Livewire::test(TestableCreditCardForm::class)->set('data.card', ['number' => 'bad'])->call('save')->assertHasErrors(['data.card']);
    TestableCreditCardForm::$formSchema = [CreditCardField::make('card')->disabled()];
    expect(Livewire::test(TestableCreditCardForm::class)->call('save')->get('saved'))->toBe([]);
});

it('rejects invalid values even through the direct storage helper', function () {
    expect(fn () => CreditCardField::make('card')->dehydrateStateForStorage([
        ...creditCardPayload(), 'number' => '4242424242424241',
    ]))->toThrow(ValidationException::class);
});

it('accepts empty optional data without requiring tokenization', function () {
    config(['filament-flex-fields.media_capture.pci.never_store_pan' => true]);
    $form = Livewire::test(TestableCreditCardForm::class)->set('data.card', null)->call('save')->assertHasNoErrors();
    expect($form->get('saved'))->toBe(['card' => ['number' => '', 'name' => '', 'expiry' => '']]);
});

it('renders localized card preview and input placeholders from the same language catalog', function () {
    app()->setLocale('pl');
    Livewire::test(TestableCreditCardForm::class)
        ->assertSee('TWOJE IMIĘ I NAZWISKO')
        ->assertSee('KARTA')
        ->assertSee('MM/RR')
        ->assertDontSee('YOUR NAME');
});
