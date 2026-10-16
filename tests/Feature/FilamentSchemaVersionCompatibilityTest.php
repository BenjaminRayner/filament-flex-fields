<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaVisibility;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexTextInputPlayground;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableInitialSchemaForm;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Mechanisms\DataStore;

afterEach(function (): void {
    TestableInitialSchemaForm::$components = [];
});

it('renders ordinary and tabbed forms and preserves initial visibility on the installed Filament version', function (): void {
    TestableInitialSchemaForm::$components = [
        FlexTextInput::make('plain')->label('Visible field'),
        FlexTextInput::make('hidden')->hidden(),
        Tabs::make('Native')->tabs([
            Tab::make('First')->schema([FlexTextInput::make('first')]),
            Tab::make('Second')->schema([FlexTextInput::make('second')]),
        ]),
        SegmentTabs::make('Segments')->tabs([
            SegmentTab::make('One')->schema([FlexTextInput::make('one')]),
            SegmentTab::make('Two')->schema([FlexTextInput::make('two')]),
        ]),
    ];

    $form = Livewire::test(TestableInitialSchemaForm::class)
        ->assertSee('Visible field')
        ->assertSeeHtml('fff-segment-tabs__panel')
        ->assertDontSeeHtml('data-fff-deferred-panel');
    [$plain, $hidden, $tabs, $segments] = $form->instance()->getSchema('form')->getComponents(withHidden: true);
    [$first, $second] = $tabs->getChildSchema()->getComponents();
    [$one, $two] = $segments->getChildSchema()->getComponents();

    expect(InitialSchemaVisibility::allows($plain))->toBeTrue()
        ->and(InitialSchemaVisibility::allows($hidden))->toBeFalse()
        ->and(InitialSchemaVisibility::allows($first->getChildSchema()->getComponents()[0]))->toBeTrue()
        ->and(InitialSchemaVisibility::allows($second->getChildSchema()->getComponents()[0]))->toBeFalse()
        ->and(InitialSchemaVisibility::allows($one->getChildSchema()->getComponents()[0]))->toBeTrue()
        ->and(InitialSchemaVisibility::allows($two->getChildSchema()->getComponents()[0]))->toBeFalse();
});

it('keeps native deferred schemas dormant when the framework supports deferred loading', function (): void {
    TestableInitialSchemaForm::$components = [
        SegmentTabs::make('Deferred')->tabs([
            SegmentTab::make('First')->schema([
                FlexTextInput::make('ready'),
            ]),
            SegmentTab::make('Later')->schema(
                Schema::make()->key('later-schema')->deferLoading()->components([
                    FlexTextInput::make('later'),
                ]),
            ),
        ]),
    ];

    Livewire::test(TestableInitialSchemaForm::class)
        ->assertSeeHtml('data-fff-deferred-panel')
        ->assertDontSeeHtml('wire:model="data.later"');
})->skip(! method_exists(Schema::class, 'deferLoading'), 'Native deferred schemas are unavailable on this Filament version');

it('renders the text input playground with the installed schema API', function (): void {
    TestableInitialSchemaForm::$components = (new FlexTextInputPlayground)->components();

    Livewire::test(TestableInitialSchemaForm::class)
        ->assertSee('Flex Text Input')
        ->assertSeeHtml('fff-segment-tabs__panel');
});

it('preserves native Livewire storage and validation across requests', function (): void {
    expect(app(DataStore::class))->toBe(app(DataStore::class));
    TestableInitialSchemaForm::$components = [FlexTextInput::make('title')->required()];

    Livewire::test(TestableInitialSchemaForm::class)
        ->call('save')->assertHasErrors(['data.title' => 'required'])
        ->set('data.title', 'Valid title')
        ->call('save')->assertHasNoErrors();
});

it('hydrates disk uploads and rejects forged paths before file callbacks', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('owned.txt', 'Owned file');
    Storage::disk('public')->put('private.txt', 'Another record');
    $resolvedPaths = [];
    TestableInitialSchemaForm::$components = [
        FlexFileUpload::make('files')->disk('public')->multiple()
            ->default(['owned.txt'])
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === 'owned.txt')
            ->getUploadedFileUsing(function (string $file) use (&$resolvedPaths): array {
                $resolvedPaths[] = $file;

                return ['name' => $file, 'size' => 10, 'type' => 'text/plain', 'url' => '/files/'.$file];
            }),
    ];
    $test = Livewire::test(TestableInitialSchemaForm::class);
    $field = $test->instance()->getSchema('form')->getComponents()[0];
    expect(array_values($field->getRawState()))->toBe(['owned.txt']);
    $test->set('data.files', ['forged' => 'private.txt']);
    $field = $test->instance()->getSchema('form')->getComponents()[0];
    expect(array_filter($field->getUploadedFiles() ?? []))->toBeEmpty();
    $test->call('save')->assertHasErrors('data.files');
    expect($test->get('savedState'))->toBeNull();
    expect($resolvedPaths)->not->toContain('private.txt');
    Storage::disk('public')->assertExists('private.txt');
});
