<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexVerificationCode;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableVerificationCodeForm;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableVerificationCodeForm::$formSchema = [];
});

it('renders independent owners, server digits and accessible native inputs', function (): void {
    TestableVerificationCodeForm::$formSchema = [
        FlexVerificationCode::make('code')->default('012345')->groups([3, 3])->groupSeparator('-')->heading('Verify')->loading(),
        FlexVerificationCode::make('locked')->length(4)->default('1234')->disabled(),
    ];
    $html = Livewire::test(TestableVerificationCodeForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-fff-asset-consumer="flex-verification-code"]')->length)->toBe(2)
        ->and($xpath->query('//input[@data-verification-index]')->length)->toBe(10)
        ->and($xpath->query('//input[@disabled]')->length)->toBe(4)
        ->and($xpath->query('//input[@autocomplete="one-time-code"]')->length)->toBe(2)
        ->and($xpath->query('//input[@data-verification-index="0"]')->item(0)->getAttribute('value'))->toBe('0')
        ->and($xpath->query("//*[@*[name()='wire:ignore']][@dir='ltr']")->length)->toBe(2)
        ->and($html)->not->toContain('x-init="init()"')
        ->and($xpath->query("//*[@role='group'][@*[name()='wire:ignore']]")->length)->toBe(0);
});

it('stores strings including leading zeroes and uppercase alphanumeric codes', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexVerificationCode::make('code')->required(), FlexVerificationCode::make('backup')->allowedCharacters('alphanumeric')];
    $component = Livewire::test(TestableVerificationCodeForm::class)
        ->set('data.code', '012345')->set('data.backup', 'a1b2c3')->call('save')->assertHasNoErrors();
    expect($component->get('saved'))->toBe(['code' => '012345', 'backup' => 'A1B2C3']);
});

it('validates raw untrusted state before dehydration', function (mixed $value): void {
    TestableVerificationCodeForm::$formSchema = [FlexVerificationCode::make('code')->required()];
    $component = Livewire::test(TestableVerificationCodeForm::class)->set('data.code', $value)->call('save')->assertHasErrors(['data.code']);
    expect($component->get('saved'))->toBeNull();
})->with(['overlong' => ['1234567'], 'invalid suffix' => ['123456x'], 'newline' => ["123456\n"], 'array' => [['123456']], 'numeric' => [123456], 'empty' => [null]]);

it('runs server callbacks only for complete valid state changes and never on hydration', function (): void {
    $codes = [];
    TestableVerificationCodeForm::$formSchema = [FlexVerificationCode::make('code')->default('123456')->submitUsing(function (string $code) use (&$codes): void {
        $codes[] = $code;
    })];
    $component = Livewire::test(TestableVerificationCodeForm::class);
    expect($codes)->toBe([]);
    foreach (['1234567', '123456x', ['123456'], '12345'] as $value) {
        $component->set('data.code', $value);
    }
    expect($codes)->toBe([]);
    $component->set('data.code', '012345');
    expect($codes)->toBe(['012345']);
    $component->set('data.code', '012345')->call('$refresh');
    expect($codes)->toBe(['012345']);
});

it('keeps identity for state reset but replaces it for a changed disabled policy', function (): void {
    TestableVerificationCodeForm::$formSchema = [FlexVerificationCode::make('code')->length(4)->live()->disabled(fn (Get $get): bool => strlen((string) $get('code')) === 4)];
    $component = Livewire::test(TestableVerificationCodeForm::class);
    $owner = static function (string $html): string {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return (new DOMXPath($document))->query('//*[@x-fff-load]')->item(0)->getAttribute('wire:key');
    };
    $initial = $owner($component->html(false));
    $component->set('data.code', '123');
    expect($owner($component->html(false)))->toBe($initial);
    $component->set('data.code', '1234');
    expect($owner($component->html(false)))->not->toBe($initial)
        ->and($component->html(false))->toContain('disabled');
    $component->set('data.code', '');
    expect($owner($component->html(false)))->toBe($initial);
});
