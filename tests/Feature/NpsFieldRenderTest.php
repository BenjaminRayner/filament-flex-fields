<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\NpsField;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableNpsForm;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableNpsForm::$formSchema = [];
});

it('renders nps field variants with lazy stylesheet hooks', function (): void {
    TestableNpsForm::$formSchema = [
        NpsField::make('score')->label('Score')->variant('pills'),
        NpsField::make('mood')->label('Mood')->variant('emojis')->options([
            0 => 'Awful',
            1 => 'Poor',
            2 => 'Neutral',
            3 => 'Good',
            4 => 'Excellent',
        ]),
    ];

    $html = Livewire::test(TestableNpsForm::class)->html(false);

    expect($html)
        ->toContain('fff-nps-field')
        ->toContain('fff-nps-field--variant-pills')
        ->toContain('fff-nps-field--variant-emojis')
        ->toContain('data-fff-asset-batch')
        ->toContain('data-fff-asset-consumer="nps-field"')
        ->toContain('npsFieldFormComponent({');

    $directory = 'filament-flex-fields-assets/core/'.app(AssetVersionManifest::class)->release();
    foreach (range(0, 4) as $key) {
        expect($html)->toContain('src="'.asset($directory.'/assets/nps-field/emojis/'.$key.'.webp').'"');
    }
});

it('renders one focusable radio for an empty group and preselects zero on the server', function (): void {
    TestableNpsForm::$formSchema = [
        NpsField::make('empty')->options([0 => 'Zero', 1 => 'One'])->disabledOptions([0]),
        NpsField::make('filled')->options([0 => 'Zero', 1 => 'One']),
    ];
    $component = Livewire::test(TestableNpsForm::class)->set('data.filled', 0);
    $document = new DOMDocument;
    @$document->loadHTML($component->html(false));
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@role="radiogroup"]')->length)->toBe(2)
        ->and($xpath->query('//*[@role="radio"]')->length)->toBe(4)
        ->and($xpath->query('//*[@role="radio"][@tabindex="0"]')->length)->toBe(2)
        ->and($xpath->query('//*[@role="radio"][@data-nps-value="0"][@aria-checked="true"]')->length)->toBe(1)
        ->and($component->html(false))->not->toContain('x-init="init()"');
});

it('validates optional empty state required choices and textual scales', function (): void {
    TestableNpsForm::$formSchema = [
        NpsField::make('optional'),
        NpsField::make('required')->options(['a' => 'Agree', 'd' => 'Disagree'])->required(),
    ];
    Livewire::test(TestableNpsForm::class)->set('data.required', 'a')->call('save')->assertHasNoErrors()
        ->set('data.required', null)->call('save')->assertHasErrors(['data.required']);
});

it('returns configured numeric and textual keys from authoritative form state', function (): void {
    TestableNpsForm::$formSchema = [
        NpsField::make('numeric')->required(),
        NpsField::make('text')->options(['agree' => 'Agree', 'disagree' => 'Disagree'])->required(),
    ];
    $component = Livewire::test(TestableNpsForm::class)->set('data.numeric', '0')->set('data.text', 'agree');
    $state = $component->instance()->getSchema('form')->getState();

    expect($state['numeric'])->toBe(0)->and($state['text'])->toBe('agree');
});

it('keeps owner identity on state updates and remounts on option policy changes', function (): void {
    TestableNpsForm::$formSchema = [NpsField::make('score')->disabledOptions(fn ($get): array => $get('blocked') ? [0] : [])];
    $component = Livewire::test(TestableNpsForm::class);
    $ownerKey = static function (string $html): string {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return (new DOMXPath($document))->query('//*[@role="radiogroup"]')->item(0)->getAttribute('wire:key');
    };
    $original = $ownerKey($component->html(false));
    $component->set('data.score', 0);
    expect($ownerKey($component->html(false)))->toBe($original);
    $component->set('data.blocked', true);
    expect($ownerKey($component->html(false)))->not->toBe($original);
    $component->call('save')->assertHasErrors(['data.score']);
});
