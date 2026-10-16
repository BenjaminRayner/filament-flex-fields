<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexFileUploadPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('renders a balanced html tree for the file upload playground section', function () {
    TestableTranslatableForm::$formSchema = app(FlexFileUploadPlayground::class)->components();

    $html = Livewire::test(TestableTranslatableForm::class)->html(false);

    expect(substr_count($html, '<div'))
        ->toBe(substr_count($html, '</div>'))
        ->and($html)->toContain('Avatar · small', 'Attachment grid')
        ->and($html)->not->toContain('Asset injector — skeleton demo', 'Open skeleton demo', 'Quick modal CSS test', 'FffSkeletonDemo');
});

it('renders every avatar before Alpine without a conditional mount template', function (): void {
    TestableTranslatableForm::$formSchema = app(FlexFileUploadPlayground::class)->components();
    $html = Livewire::test(TestableTranslatableForm::class)->html(false);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $avatars = $xpath->query('//div[contains(@class, "fff-flex-file-upload__avatar-shell")]');
        expect($avatars)->not->toBeFalse()->and($avatars->length)->toBe(3);
        foreach ($avatars as $avatar) {
            expect($xpath->query('ancestor::template', $avatar)->length)->toBe(0)
                ->and($avatar->hasAttribute('x-cloak'))->toBeFalse()
                ->and($xpath->query('.//svg[contains(@class, "fff-flex-file-upload__avatar-frame")]/circle', $avatar)->length)->toBe(1);
            expect($xpath->query('ancestor::div[span[@data-fff-asset-batch and @data-fff-assets-ready]]', $avatar)->length)->toBeGreaterThan(0);
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});

it('renders authorized grid attachments with filenames before browser initialization', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/payroll.pdf', '%PDF-1.4 test');
    TestableTranslatableForm::$formSchema = [
        FlexFileUpload::make('attachments')
            ->disk('local')->multiple()->uploadLayout('grid')->default(['documents/payroll.pdf']),
    ];
    $html = Livewire::test(TestableTranslatableForm::class)
        ->call('fillPlaygroundState', ['attachments' => ['documents/payroll.pdf']])->html(false);
    expect($html)->toContain('fff-flex-file-upload__tile-face', 'payroll.pdf', 'pathLength="100"')
        ->and(substr_count($html, '<div'))->toBe(substr_count($html, '</div>'));
});

it('clears only transient errors owned by this upload field and preserves other validation', function (): void {
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()];
    $form = Livewire::test(TestableTranslatableForm::class);
    $component = $form->instance();
    $component->setErrorBag([
        'data.attachments.55fe6fd4-0139-4569-bc3b-e7d9a21c9035' => 'Transfer failed',
        'data.attachments' => 'At least one file is required',
        'data.attachments.metadata' => 'Invalid metadata',
        'data.other.55fe6fd4-0139-4569-bc3b-e7d9a21c9035' => 'Other transfer failed',
    ]);
    $field = $component->getSchema('form')->getComponent('attachments');
    $component->callSchemaComponentMethod($field->getKey(), 'clearTemporaryUploadErrors', []);
    expect(array_keys($component->getErrorBag()->getMessages()))->toBe([
        'data.attachments', 'data.attachments.metadata', 'data.other.55fe6fd4-0139-4569-bc3b-e7d9a21c9035',
    ]);
});

it('keeps upload readiness local and gives retained presentation factories a stable morph identity', function (): void {
    TestableTranslatableForm::$formSchema = [FlexFileUpload::make('attachments')->multiple()->uploadLayout('grid')];
    $form = Livewire::test(TestableTranslatableForm::class);
    $keys = [];
    foreach ([$form->html(false), $form->call('fillPlaygroundState', [])->html(false)] as $html) {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
            $xpath = new DOMXPath($document);
            $presentation = $xpath->query('//div[contains(@class, "fff-flex-file-upload--grid") and @x-data]')->item(0);
            expect($presentation)->not->toBeNull();
            expect($xpath->query('ancestor::*[@data-fff-local-paint]', $presentation)->length)->toBe(1);
            $keys[] = $presentation->getAttribute('wire:key');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
    expect($keys[0])->not->toBe('')->toEndWith('.presentation')->and($keys[1])->toBe($keys[0]);
});

it('keeps inactive tab CSS and Livewire update readiness under the browser gate', function (): void {
    TestableTranslatableForm::$formSchema = [
        Tabs::make('sources')->tabs([
            Tab::make('Active')->schema([
                FlexImageUpload::make('active_avatar')->avatar()->label('Active avatar'),
            ]),
            Tab::make('Inactive')->schema([
                FlexImageUpload::make('inactive_avatar')->avatar()->label('Inactive avatar'),
            ]),
        ]),
    ];
    $form = Livewire::test(TestableTranslatableForm::class);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($form->html(false));
        $xpath = new DOMXPath($document);
        foreach (['Active avatar' => true, 'Inactive avatar' => false] as $label => $ready) {
            $avatar = $xpath->query('//div[@aria-label="'.$label.'" and contains(@class, "avatar-shell")]')->item(0);
            expect($avatar)->not->toBeNull();
            expect($xpath->query('ancestor::div[span[@data-fff-asset-batch and @data-fff-assets-ready]]', $avatar)->length > 0)->toBe($ready);
        }
        $document->loadHTML($form->call('fillPlaygroundState', [])->html(false));
        $xpath = new DOMXPath($document);
        expect($xpath->query('//span[@data-fff-asset-batch and @data-fff-assets-ready]')->length)->toBe(0);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});

it('retains the presentation owner when files change but replaces it when disabled configuration changes', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('first.pdf', '%PDF-1.4 first');
    Storage::disk('local')->put('second.pdf', '%PDF-1.4 second');
    TestableTranslatableForm::$formSchema = [
        Checkbox::make('locked'),
        FlexFileUpload::make('attachments')->disk('local')->multiple()->uploadLayout('grid')
            ->disabled(fn (Get $get): bool => (bool) $get('locked')),
    ];
    $form = Livewire::test(TestableTranslatableForm::class);
    $snapshots = [];
    $previous = libxml_use_internal_errors(true);
    try {
        foreach ([['first.pdf', false], ['second.pdf', false], ['second.pdf', true]] as [$file, $disabled]) {
            $document = new DOMDocument;
            $document->loadHTML($form->call('fillPlaygroundState', ['attachments' => [$file], 'locked' => $disabled])->html(false));
            $xpath = new DOMXPath($document);
            $owner = $xpath->query('//div[contains(@class, "fff-flex-file-upload--grid") and @x-data]')->item(0);
            $snapshots[] = [
                'key' => $owner->getAttribute('wire:key'),
                'factory' => $owner->getAttribute('x-data'),
                'config' => json_decode($owner->getAttribute('data-fff-upload-config'), true, flags: JSON_THROW_ON_ERROR),
            ];
            expect($xpath->query('.//div[contains(@class, "fff-flex-file-upload__files") and @*[name()="wire:ignore"]]', $owner)->length)->toBe(1);
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    expect($snapshots[0]['key'])->toBe($snapshots[1]['key'])
        ->and($snapshots[0]['factory'])->toBe($snapshots[1]['factory'])
        ->and($snapshots[0]['config']['initialFiles'][0]['name'])->toBe('first.pdf')
        ->and($snapshots[1]['config']['initialFiles'][0]['name'])->toBe('second.pdf')
        ->and($snapshots[2]['key'])->not->toBe($snapshots[1]['key'])
        ->and($snapshots[2]['config']['isDisabled'])->toBeTrue();
});
