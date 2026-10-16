<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    TestableTranslatableForm::$formSchema = [];
});

it('renders phone country selection without an icon while retaining accessible selection state', function () {
    TestableTranslatableForm::$formSchema = [PhoneField::make('phone')->countries(['PL', 'DE'])];

    Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', ['phone' => ['country' => 'PL', 'national' => '512345678', 'e164' => '']])
        ->assertSeeHtml('fff-phone-field__country-menu')
        ->assertSeeHtml('fff-select-dropdown-panel--no-selection-indicator')
        ->assertSeeHtml('x-bind:aria-selected=')
        ->assertDontSeeHtml('fff-select-option-selected-check');
});

it('persists only the country code and canonical phone keys through native form dehydration', function () {
    Schema::create('fff_country_phone_probe', function (Blueprint $table) {
        $table->id();
        $table->string('country', 2);
        $table->json('phone');
    });
    try {
        TestableTranslatableForm::$formSchema = [
            CountryField::make('country')->countries(['PL', 'DE'])->required(),
            PhoneField::make('phone')->countries(['PL', 'DE'])->required(),
        ];
        $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
            'country' => 'PL',
            'phone' => ['country' => 'PL', 'national' => '512345678', 'e164' => '+12025550123', 'label' => 'Forged label', 'flag_url' => 'https://evil.test/x.svg'],
        ]);
        $state = $test->instance()->getSchema('form')->getState();
        expect($state)->toBe([
            'country' => 'PL', 'phone' => ['country' => 'PL', 'national' => '512 345 678', 'e164' => '+48512345678'],
        ]);
        $id = DB::table('fff_country_phone_probe')->insertGetId(['country' => $state['country'], 'phone' => json_encode($state['phone'], JSON_THROW_ON_ERROR)]);
        $record = DB::table('fff_country_phone_probe')->find($id);
        expect($record->country)->toBe('PL');
        $fresh = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', [
            'country' => $record->country, 'phone' => json_decode($record->phone, true, flags: JSON_THROW_ON_ERROR),
        ]);
        expect($fresh->instance()->getSchema('form')->getState())->toBe($state);
    } finally {
        Schema::dropIfExists('fff_country_phone_probe');
    }
});

it('rejects forged country payloads at the native validation boundary', function (mixed $value) {
    TestableTranslatableForm::$formSchema = [CountryField::make('country')->countries(['PL', 'DE'])->defaultCountry('PL')];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['country' => 'PL'])->set('data.country', $value);
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
})->with([
    'label' => ['Poland'], 'unknown code' => ['ZZ'], 'excluded market' => ['US'],
    'structured label' => [['code' => 'PL', 'label' => 'Poland']], 'empty array' => [[]],
    'number' => [123],
]);

it('rejects forged phone region and national shapes before normalization', function (array $value) {
    TestableTranslatableForm::$formSchema = [PhoneField::make('phone')->countries(['PL'])->defaultCountry('PL')];
    $test = Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', ['phone' => ['country' => 'PL', 'national' => '512345678', 'e164' => '']])
        ->set('data.phone', $value);
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
})->with([
    'label country' => [['country' => 'Poland', 'national' => '512345678']],
    'unknown region' => [['country' => 'ZZ', 'national' => '512345678']],
    'excluded region' => [['country' => 'US', 'national' => '512345678']],
    'country array' => [['country' => ['PL'], 'national' => '512345678']],
    'national array' => [['country' => 'PL', 'national' => ['512345678']]],
    'letters hidden around valid digits' => [['country' => 'PL', 'national' => 'malicious512345678']],
    'missing country' => [['national' => '512345678']],
    'empty wrapper' => [[]],
    'boolean national' => [['country' => 'PL', 'national' => true]],
    'excess input' => [['country' => 'PL', 'national' => str_repeat('1', 257)]],
]);

it('recomputes forged E164 and strips unrequested phone metadata during dehydration', function () {
    TestableTranslatableForm::$formSchema = [PhoneField::make('phone')->countries(['PL'])];
    $test = Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', ['phone' => ['country' => 'PL', 'national' => '512345678', 'e164' => '']])
        ->set('data.phone.e164', '+12025550123')->set('data.phone.label', 'Not stored')
        ->set('data.phone.flag_url', 'https://evil.test/flag.svg')->set('data.phone.type', 'VOIP');
    expect($test->instance()->getSchema('form')->getState()['phone'])->toBe([
        'country' => 'PL', 'national' => '512 345 678', 'e164' => '+48512345678',
    ]);
});

it('retains only explicitly enabled server computed phone enrichment', function () {
    TestableTranslatableForm::$formSchema = [PhoneField::make('phone')->countries(['PL'])->includeFormats(['international'])->includeMetadata(['type'])];
    $test = Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', ['phone' => ['country' => 'PL', 'national' => '512345678', 'e164' => '']])
        ->set('data.phone.international', 'forged')->set('data.phone.type', 'VOIP');
    expect($test->instance()->getSchema('form')->getState()['phone'])->toBe([
        'country' => 'PL', 'national' => '512 345 678', 'e164' => '+48512345678',
        'international' => '+48 512 345 678', 'type' => 'MOBILE',
    ]);
});
