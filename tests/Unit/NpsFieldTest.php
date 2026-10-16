<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NpsField;
use Bjanczak\FilamentFlexFields\StateCasts\NpsStateCast;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableNpsForm;
use Illuminate\Validation\Rules\In;
use Livewire\Livewire;

it('exposes nps configuration via fluent api', function () {
    $field = NpsField::make('score')
        ->variant('segments')
        ->rounding('full')
        ->colorCoded(true)
        ->minLabel('Low')
        ->maxLabel('High')
        ->options([0 => 'Zero', 1 => 'One']);

    expect($field->getVariant())->toBe('segments')
        ->and($field->getRounding())->toBe('full')
        ->and($field->isColorCoded())->toBeTrue()
        ->and($field->getMinLabel())->toBe('Low')
        ->and($field->getMaxLabel())->toBe('High')
        ->and($field->getOptions())->toBe([0 => 'Zero', 1 => 'One']);
});

it('defaults to a zero through ten scale', function () {
    expect(NpsField::make('score')->getOptions())->toHaveCount(11)
        ->and(NpsField::make('score')->getOptions()[0])->toBe(0)
        ->and(NpsField::make('score')->getOptions()[10])->toBe(10);
});

it('maps color coded tones for detractors passives and promoters', function () {
    $field = NpsField::make('score')->colorCoded(true);

    expect($field->getOptionTone(0))->toBe('detractor')
        ->and($field->getOptionTone(6))->toBe('detractor')
        ->and($field->getOptionTone(7))->toBe('passive')
        ->and($field->getOptionTone(8))->toBe('passive')
        ->and($field->getOptionTone(9))->toBe('promoter')
        ->and($field->getOptionTone(10))->toBe('promoter')
        ->and($field->getOptionTone('a'))->toBeNull();
});

it('resolves all five bundled emoji images from the sealed release with the configured asset prefix', function (string $prefix) {
    config(['filament.assets_path' => $prefix]);
    $field = NpsField::make('mood')
        ->variant('emojis')
        ->options([
            0 => 'Awful',
            1 => 'Poor',
            2 => 'Neutral',
            3 => 'Good',
            4 => 'Excellent',
        ]);

    $seal = app(AssetVersionManifest::class);
    $directory = ($prefix === '' ? '' : $prefix.'/').'filament-flex-fields-assets/core/'.$seal->release();
    foreach (range(0, 4) as $key) {
        $file = 'assets/nps-field/emojis/'.$key.'.webp';
        $url = asset($directory.'/'.$file);
        expect($field->getEmojiImage($key))->toBe($url)
            ->and($seal->digestFor($file))->toBe(hash_file('sha256', $seal->root().'/resources/dist/'.$file));
    }
    expect($field->getEmojiImage(9))->toBeNull();
})->with(['default publication' => '', 'prefixed publication' => 'vendor/flex']);

it('preserves custom emoji urls while resolving remaining bundled images from the release', function () {
    $custom = 'https://example.com/mood.webp?version=custom';
    $field = NpsField::make('mood')->variant('emojis')->options([0 => 'Awful', 1 => 'Poor'])->emojiImages([0 => $custom]);

    expect($field->getEmojiImage(0))->toBe($custom)
        ->and($field->getEmojiImage(1))->toContain('/filament-flex-fields-assets/core/')
        ->and($field->getEmojiImage(1))->toEndWith('/assets/nps-field/emojis/1.webp');
});

it('does not emit inline style variables when color coded', function () {
    $field = NpsField::make('score')->colorCoded(true);

    expect($field->getOptionStyleVariables(0))->toBe([])
        ->and($field->getOptionStyleVariables(9))->toBe([]);
});

it('validates state against configured option keys', function () {
    $field = NpsField::make('score')
        ->options([1 => 'One', 2 => 'Two']);

    expect($field->getOptionKeys())->toBe([1, 2]);

    $rules = $field->getValidationRules();
    $inRule = collect($rules)->first(fn (mixed $rule): bool => $rule instanceof In);

    expect($rules)->toContain('nullable')
        ->and($inRule)->not->toBeNull()
        ->and((string) $inRule)->toBe('in:"1","2"');
});

it('supports custom icons for emoji variant options', function () {
    $field = NpsField::make('mood')
        ->variant('emojis')
        ->options([0 => 'Awful', 1 => 'Poor'])
        ->icons([0 => 'gravityui-circle-xmark', 1 => 'heroicon-o-hand-raised']);

    expect($field->getOptionIcon(0))->toBe('gravityui-circle-xmark')
        ->and($field->getOptionIcon(1))->toBe('heroicon-o-hand-raised')
        ->and($field->getOptionIcon(2))->toBeNull();
});

it('defaults to a null state', function () {
    expect(NpsField::make('score')->getDefaultState())->toBeNull();
});

it('supports required validation from filament field api', function () {
    expect(NpsField::make('score')->required()->getValidationRules())->toContain('required');
});

it('supports disabled options across variants', function () {
    $field = NpsField::make('score')
        ->options([0 => 'Zero', 1 => 'One', 2 => 'Two'])
        ->disabledOptions([1]);

    expect($field->isOptionDisabled(1))->toBeTrue()
        ->and($field->isOptionDisabled(2))->toBeFalse();
});

it('registers nps field assets with segment control dependency', function () {
    expect(FlexFieldAssets::stylesheetsFor('nps-field'))->toBe(['segment-control', 'nps-field']);
});

it('rejects forged values and disabled choices before saving', function (mixed $value): void {
    TestableNpsForm::$formSchema = [
        NpsField::make('score')->options([0 => 'Zero', 1 => 'One', 2 => 'Two'])->disabledOptions([2])->required(),
    ];
    try {
        Livewire::test(TestableNpsForm::class)
            ->set('data.score', $value)->call('save')->assertHasErrors(['data.score']);
    } finally {
        TestableNpsForm::$formSchema = [];
    }
})->with([[true], [false], [[1]], [['score' => 1]], ['1.0'], ['1e0'], [1.5], [11], [2], [null]]);

it('preserves canonical numeric and textual keys without coercing invalid shapes', function (): void {
    $cast = new NpsStateCast([0 => 'Zero', 9 => 'Nine', 'agree' => 'Agree']);
    expect($cast->get('0'))->toBe(0)
        ->and($cast->set('9'))->toBe(9)
        ->and($cast->set('agree'))->toBe('agree')
        ->and($cast->set(true))->toBeTrue()
        ->and($cast->set(['9']))->toBe(['9'])
        ->and($cast->set('09'))->toBe('09');
});

it('keeps color categories exact and custom colors safely scoped', function (): void {
    $field = NpsField::make('score')->colors(['#123456' => [1], 'success' => fn (mixed $value): bool => $value === 'yes'])->textColors(['white' => [1]]);
    expect($field->getOptionStyleVariables(1))->toBe(['--fff-nps-selected-bg' => '#123456', '--fff-nps-selected-color' => 'white'])
        ->and($field->getOptionColor('yes'))->toBe('success')
        ->and($field->getOptionColor(true))->toBeNull()
        ->and($field->isDetractor(6.9))->toBeFalse()
        ->and($field->isPromoter('1e1'))->toBeFalse();
    expect(fn () => $field->resolveColorValue('rgb(0,0,0); background:url(https://example.test)'))->toThrow(InvalidArgumentException::class);
});

it('rejects unusable variants and malformed option definitions', function (): void {
    expect(fn () => NpsField::make('score')->variant('unknown')->getVariant())->toThrow(InvalidArgumentException::class)
        ->and(fn () => NpsField::make('score')->options(['' => 'Empty'])->getOptions())->toThrow(InvalidArgumentException::class)
        ->and(fn () => NpsField::make('score')->options([1 => ['nested']])->getOptions())->toThrow(InvalidArgumentException::class);
});
