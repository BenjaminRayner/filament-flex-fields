<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    TestableTranslatableForm::$formSchema = [];
});

it('round trips a canonical family id through a database and a fresh native form', function () {
    Schema::create('fff_font_selection_probe', function (Blueprint $table) {
        $table->id();
        $table->string('font');
    });
    try {
        TestableTranslatableForm::$formSchema = [FontSelect::make('font')->fonts(['google:inter', 'google:lora'])->required()];
        $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['font' => 'google:inter']);
        $state = $test->instance()->getSchema('form')->getState();
        expect($state)->toBe(['font' => 'google:inter']);
        $id = DB::table('fff_font_selection_probe')->insertGetId($state);
        $record = DB::table('fff_font_selection_probe')->find($id);
        $fresh = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['font' => $record->font]);
        expect($fresh->instance()->getSchema('form')->getState())->toBe($state)
            ->and(FontCatalog::find($record->font)->family)->toBe('Inter');
    } finally {
        Schema::dropIfExists('fff_font_selection_probe');
    }
});

it('rejects forged payload shapes and out of policy font ids at native validation', function (mixed $value) {
    TestableTranslatableForm::$formSchema = [FontSelect::make('font')->fonts(['google:inter'])];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['font' => 'google:inter'])->set('data.font', $value);
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
})->with([
    'label' => ['Inter'], 'out of policy' => ['google:lora'], 'unknown' => ['google:missing'],
    'descriptor' => [['value' => 'google:inter', 'label' => 'Inter']], 'empty array' => [[]],
    'number' => [123], 'boolean' => [false], 'url' => ['https://fonts.googleapis.com/css2?family=Inter'],
]);

it('dehydrates cleared and whitespace only optional selections to null', function (mixed $value) {
    TestableTranslatableForm::$formSchema = [FontSelect::make('font')];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['font' => 'google:inter'])->set('data.font', $value);
    expect($test->instance()->getSchema('form')->getState())->toBe(['font' => null]);
})->with(['null' => [null], 'empty' => [''], 'whitespace' => ['   ']]);
