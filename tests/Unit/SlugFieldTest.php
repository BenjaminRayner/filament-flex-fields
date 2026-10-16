<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TitleSlugField;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\Playground\SlugFieldPlayground;
use Bjanczak\FilamentFlexFields\Support\Slug\SlugGenerator;
use Bjanczak\FilamentFlexFields\Support\Slug\SpatieSlugIntegration;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableSlugForm;
use Filament\Schemas\Components\FusedGroup;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

it('exposes slug field configuration api', function () {
    $field = SlugField::make('slug')
        ->size('lg')
        ->variant('primary')
        ->source('title')
        ->sourceLive(false)
        ->slugSeparator('_')
        ->maxSlugLength(80)
        ->urlHost('https://example.com')
        ->urlPath('/blog/')
        ->permalinkPreview()
        ->showVisitLink(false)
        ->showCopyButton(false)
        ->showRegenerateButton(false)
        ->autoGenerate(false)
        ->preserveSlugOnEdit(false)
        ->inlineEditing(false)
        ->allowHomepageSlug()
        ->generationDebounce(600)
        ->slugReadOnly()
        ->titleReadOnly()
        ->slugUnique(false)
        ->spatieModel('App\\Models\\Post')
        ->spatieSlugField('permalink')
        ->slugLabelPostfix('/detail/')
        ->recordSlug('existing-slug');

    expect($field->getSize())->toBe('lg')
        ->and($field->getVariant())->toBe('primary')
        ->and($field->getSourceStatePath())->toBe('title')
        ->and($field->isSourceLive())->toBeFalse()
        ->and($field->getSeparator())->toBe('_')
        ->and($field->getMaxSlugLength())->toBe(80)
        ->and($field->getUrlHost())->toBe('https://example.com')
        ->and($field->getUrlPath())->toBe('/blog/')
        ->and($field->hasPermalinkPreview())->toBeTrue()
        ->and($field->shouldShowVisitLink())->toBeFalse()
        ->and($field->shouldShowCopyButton())->toBeFalse()
        ->and($field->shouldShowRegenerateButton())->toBeFalse()
        ->and($field->shouldAutoGenerate())->toBeFalse()
        ->and($field->shouldPreserveSlugOnEdit())->toBeFalse()
        ->and($field->shouldUseInlineEditing())->toBeFalse()
        ->and($field->allowsHomepageSlug())->toBeTrue()
        ->and($field->getDebounceMilliseconds())->toBe(600)
        ->and($field->isSlugReadOnly())->toBeTrue()
        ->and($field->isTitleReadOnly())->toBeTrue()
        ->and($field->shouldEnforceSlugUnique())->toBeFalse()
        ->and($field->getSpatieModelClass())->toBe('App\\Models\\Post')
        ->and($field->getSpatieSlugField())->toBe('permalink')
        ->and($field->getSlugLabelPostfix())->toBe('/detail/')
        ->and($field->getRecordSlug())->toBe('existing_slug')
        ->and($field->usesSpatieIntegration())->toBeFalse();
});

it('resolves nested source state paths for slug field', function () {
    $field = SlugField::make('data.slug')->source('title');

    expect($field->getSourceStatePath())->toBe('data.title');
});

it('normalizes slug values and supports homepage slug', function () {
    $field = SlugField::make('slug')->slugSeparator('-')->allowHomepageSlug();

    expect($field->normalizeSlug('  Hello--World!! '))->toBe('hello-world')
        ->and($field->normalizeSlug('/'))->toBe('/')
        ->and($field->generateSlugFromSource('Luxury Yacht Charter'))->toBe('luxury-yacht-charter');
});

it('builds full permalink urls with postfix', function () {
    $field = SlugField::make('slug')
        ->urlHost('https://wyachts.test')
        ->urlPath('/charters/')
        ->slugLabelPostfix('/preview');

    expect($field->getFullPermalinkUrl('mediterranean-sailing'))
        ->toBe('https://wyachts.test/charters/mediterranean-sailing/preview')
        ->and($field->getDisplayUrlHost())->toBe('wyachts.test');
});

it('exposes alpine configuration for the field view', function () {
    $field = SlugField::make('slug')
        ->source('title')
        ->urlHost('https://wyachts.test')
        ->urlPath('/blog/')
        ->recordSlug('stored-slug');

    $config = $field->getAlpineConfiguration();

    expect($config)->toHaveKeys(['sourcePath', 'labels', 'preserveOnEdit', 'inlineEditing', 'recordSlug', 'showActionButtonLabels', 'initialAutoSyncDisabled', 'canVisitLink'])
        ->and($config['sourcePath'])->toBe('title')
        ->and($config['recordSlug'])->toBe('stored-slug')
        ->and($config['initialAutoSyncDisabled'])->toBeTrue()
        ->and($config['showActionButtonLabels'])->toBeTrue()
        ->and($config['canVisitLink'])->toBeFalse()
        ->and($config['labels']['edit'])->toBe('Edit');
});

it('allows visit link only outside create operation', function () {
    $field = SlugField::make('slug')
        ->urlHost('https://wyachts.test')
        ->urlPath('/blog/')
        ->recordSlug('existing-slug');

    expect($field->canVisitLink())->toBeFalse()
        ->and($field->getAlpineConfiguration()['visitUrl'])->toBeNull()
        ->and($field->getAlpineConfiguration()['canVisitLink'])->toBeFalse();
});

it('computes initial auto sync disabled state for edit records', function () {
    $field = SlugField::make('slug')
        ->preserveSlugOnEdit()
        ->recordSlug('existing-slug');

    expect($field->getInitialAutoSyncDisabled())->toBeTrue();

    $field = SlugField::make('slug')
        ->preserveSlugOnEdit(false)
        ->recordSlug('existing-slug');

    expect($field->getInitialAutoSyncDisabled())->toBeFalse();

    $field = SlugField::make('slug')
        ->preserveSlugOnEdit()
        ->recordSlug(null);

    expect($field->getInitialAutoSyncDisabled())->toBeFalse();
});

it('supports icon-only secondary action buttons and auto-update disabled path', function () {
    $field = SlugField::make('data.slug')
        ->source('title')
        ->actionButtonsIconOnly()
        ->autoUpdateDisabledField('slug_auto_update_disabled');

    $config = $field->getAlpineConfiguration();

    expect($config['showActionButtonLabels'])->toBeFalse()
        ->and($config['autoUpdateDisabledPath'])->toBe('data.slug_auto_update_disabled');
});

it('generates slugs via slug generator support class', function () {
    expect(SlugGenerator::fromString('Żółć & Friends', '-', 12))->toBe('zolc-friends')
        ->and(SlugGenerator::normalize('Hello--World', '-'))->toBe('hello-world')
        ->and(SlugGenerator::isHomepage('/'))->toBeTrue();
});

it('works without spatie integration configured', function () {
    $field = SlugField::make('slug')
        ->source('title')
        ->urlHost('https://example.com')
        ->urlPath('/blog/')
        ->slugSeparator('_');

    expect($field->usesSpatieIntegration())->toBeFalse()
        ->and($field->generateSlugFromSource('Hello World'))->toBe('hello_world')
        ->and($field->getFullPermalinkUrl('hello_world'))->toBe('https://example.com/blog/hello_world')
        ->and($field->getRegex())->toBe(SlugGenerator::patternForSeparator('_'))
        ->and(preg_match($field->getRegex(), 'hello_world'))->toBe(1)
        ->and(preg_match($field->getRegex(), 'hello-world'))->toBe(0);

    $config = $field->getAlpineConfiguration();

    expect($config['selfHealingPermalink'])->toBeFalse()
        ->and($config['selfHealingSeparator'])->toBe('_')
        ->and($config['separator'])->toBe('_')
        ->and($config['permalinkPreview'])->toBeTrue();
});

it('keeps custom slug pattern when explicitly configured', function () {
    $field = SlugField::make('slug')
        ->slugSeparator('_')
        ->slugPattern('/^custom$/');

    expect($field->getRegex())->toBe('/^custom$/');
});

it('derives homepage-aware slug pattern from separator by default', function () {
    $field = SlugField::make('slug')
        ->slugSeparator('_')
        ->allowHomepageSlug();

    expect($field->getRegex())->toBe(SlugGenerator::patternForSeparator('_', true))
        ->and(preg_match($field->getRegex(), '/'))->toBe(1)
        ->and(preg_match($field->getRegex(), 'hello_world'))->toBe(1);
});

it('enables server-side slug preview for translatable titles', function () {
    $field = SlugField::make('slug')
        ->translatableTitle(true)
        ->titleLocales(['pl' => 'PL', 'en' => 'EN'])
        ->slugSourceLocale('pl')
        ->source('title.pl');

    expect($field->usesTranslatableTitle())->toBeTrue()
        ->and($field->shouldUseServerSideGeneration())->toBeTrue()
        ->and($field->getAlpineConfiguration()['serverGenerate'])->toBeTrue()
        ->and($field->getAlpineConfiguration()['slugSourceLocale'])->toBe('pl');
});

it('falls back when spatie sluggable is unavailable', function (): void {
    if (SpatieSlugIntegration::isAvailable()) {
        $model = new class extends Model {};

        expect(SpatieSlugIntegration::generate('My Post Title', $model))->toBe('my-post-title');

        return;
    }

    expect(SpatieSlugIntegration::isAvailable())->toBeFalse();

    $model = new class extends Model {};

    expect(SpatieSlugIntegration::generate('My Post Title', $model))->toBe('my-post-title');
});

it('registers slug as a custom flex field component', function () {
    expect(FieldType::Slug->isCustomComponent())->toBeTrue();
});

it('builds slug field from flex field definition config', function () {
    $builder = new FlexFieldFormBuilder;

    $definition = new FlexFieldDefinition(
        slug: 'permalink',
        label: 'Permalink',
        type: FieldType::Slug,
        config: [
            'source' => 'title',
            'url_host' => 'https://example.com',
            'url_path' => '/posts/',
            'debounce' => 300,
            'slug_unique' => false,
        ],
    );

    $component = $builder->makeComponent($definition, '');

    expect($component)->toBeInstanceOf(SlugField::class)
        ->and($component->getSourceStatePath())->toBe('title')
        ->and($component->getUrlHost())->toBe('https://example.com')
        ->and($component->getUrlPath())->toBe('/posts/')
        ->and($component->getDebounceMilliseconds())->toBe(300)
        ->and($component->shouldEnforceSlugUnique())->toBeFalse();
});

it('registers slug field playground variants', function () {
    $builder = app(SlugFieldPlayground::class);
    $state = $builder->defaultState();

    expect($state)->toHaveKeys([
        'slug__one_liner_title',
        'slug__one_liner_slug',
        'slug__title',
        'slug__standalone',
        'slug__pair_title',
        'slug__pair_slug',
        'slug__permalink',
        'slug__sandwich',
        'slug__homepage',
    ]);
});

it('exposes slug field wrapper classes', function () {
    $field = SlugField::make('slug')->size('sm')->variant('secondary');

    expect($field->getWrapperClasses())->toBe([
        'fff-slug-field-field',
        'fff-flex-text-input-field',
        'fff-slug-field-field--sm',
        'fff-flex-text-input-field--sm',
        'fff-slug-field-field--secondary',
        'fff-rounding-md',
    ]);
});

it('applies slug unique validation by default', function () {
    $field = SlugField::make('slug');

    expect($field->shouldEnforceSlugUnique())->toBeTrue()
        ->and($field->shouldValidateSlugUniquenessLive())->toBeTrue()
        ->and($field->getSlugUniqueValidationMessage())
        ->toBe(__('filament-flex-fields::default.validation.slug.unique'));
});

it('exposes scoped slug unique configuration', function () {
    $scope = fn ($query) => $query->where('tenant_id', 1);

    $field = SlugField::make('slug')
        ->slugUniqueModel('App\\Models\\Post')
        ->slugUniqueScope($scope);

    expect($field->getSlugUniqueModel())->toBe('App\\Models\\Post')
        ->and($field->getSlugUniqueScope())->toBe($scope);
});

it('exposes slug rules api', function () {
    $field = SlugField::make('slug')
        ->slugRules(['required', 'string', 'max:120']);

    expect($field->isRequired())->toBeTrue();
});

it('exposes withTitle convenience schema', function () {
    $group = SlugField::withTitle(titleAutofocus: true);

    expect($group)->toBeInstanceOf(FusedGroup::class);
});

it('fires slug after state updated callback when slug changes', function (): void {
    $lastSlug = null;

    TestableSlugForm::$formSchema = [
        TitleSlugField::make(
            slugAfterStateUpdated: function (mixed $state) use (&$lastSlug): void {
                $lastSlug = $state;
            },
        ),
    ];

    Livewire::test(TestableSlugForm::class)
        ->set('data.slug', 'my-custom-slug');

    expect($lastSlug)->toBe('my-custom-slug');
});

it('builds title slug field with config defaults', function () {
    config()->set('filament-flex-fields.slug.field_title', 'name');
    config()->set('filament-flex-fields.slug.field_slug', 'permalink');

    $group = TitleSlugField::make();

    expect($group)->toBeInstanceOf(FusedGroup::class);
});

it('resolves spatie model class from explicit configuration', function () {
    $field = SlugField::make('slug')->spatieModel('App\\Models\\Post');

    expect($field->getSpatieModelClass())->toBe('App\\Models\\Post');
});

it('exposes server side slug generation toggle', function () {
    $field = SlugField::make('slug')->serverSideGeneration();

    expect($field->shouldUseServerSideGeneration())->toBeTrue();
});

it('translates slug ui strings in polish', function () {
    app()->setLocale('pl');

    expect(__('filament-flex-fields::default.slug.edit'))->toBe('Edytuj')
        ->and(__('filament-flex-fields::default.validation.slug.pattern'))
        ->toBe('Slug moze zawierac tylko male litery, cyfry i myslniki.');
});

it('does not double-stack locale tab spacing in fused title slug groups', function () {
    $css = file_get_contents(__DIR__.'/../../resources/css/components/title-slug-fused.css');

    expect($css)
        ->toContain('.fff-title-slug-field__translatable-tabs .fff-segment-overflow-shell .fff-segment-tabs__control')
        ->toContain('@apply mb-0')
        ->not->toMatch('/fff-title-slug-field__translatable-tabs\s+\.fff-segment-tabs__control\s*\{[^}]*mb-3/s');
});
