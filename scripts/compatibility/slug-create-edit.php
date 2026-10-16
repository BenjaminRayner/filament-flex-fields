<?php

declare(strict_types=1);

// Use the real Filament schema and native Spatie persistence against private SQLite.
require __DIR__.'/slug-spatie.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TitleSlugField;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Sluggable\SlugOptions;

$verify = static function (bool $condition, string $name): void {
    locationCheck($condition, $name);
    echo 'PASS create/edit: '.$name.PHP_EOL;
};
$makeSchema = static function (string|SlugSpatieProbeRecord|null $model, string $operation, SlugField $slug) use ($host): Schema {
    return Schema::make($host)->key('slug-'.$operation.'-probe')->model($model)->operation($operation)
        ->statePath('data.article')->components([FlexTextInput::make('title')->required(), $slug->required()]);
};
$before = DB::table('slug_spatie_probe')->count();
$createField = SlugField::make('slug')->source('title')->urlHost('https://example.test')->urlPath('/articles/');
$create = $makeSchema(SlugSpatieProbeRecord::class, 'create', $createField);
$create->fill(['title' => '', 'slug' => '']);
$verify($createField->getRecord() === null && $createField->getRecordSlug() === null, 'create class-only schema has no existing record/key');
$create->toHtml();
$verify(! $createField->getAlpineConfiguration()['initialAutoSyncDisabled'] && ! $createField->canVisitLink(), 'create starts with auto-sync and no Visit action');
$host->data['article']['title'] = 'Shared';
$preview = $createField->generateSlugPreview('Shared');
$verify($preview === 'shared-2', 'class-only create generates unique preview without an ID');
$verify(! $createField->validateSlugDraft('shared')['valid'], 'create does not ignore an existing colliding row');
$verify(DB::table('slug_spatie_probe')->count() === $before, 'preview/validation do not insert a record');
$host->data['article']['title'] = 'Create Harbour';
$preview = $createField->generateSlugPreview('Create Harbour');
$draft = $createField->validateSlugDraft($preview);
$verify($draft['valid'] && $draft['slug'] === 'create-harbour', 'create generated draft validates');
$createField->state($draft['slug']);
$created = new SlugSpatieProbeRecord;
$created->fill($create->getState())->save();
$id = $created->getKey();
$created->refresh();
$verify($created->exists && $created->slug === 'create-harbour' && DB::table('slug_spatie_probe')->count() === $before + 1, 'real create form state saves and reloads through native Spatie');

$editField = SlugField::make('slug')->source('title')->urlHost('https://example.test')->urlPath('/articles/');
$edit = $makeSchema($created, 'edit', $editField);
$edit->fill($created->attributesToArray());
$edit->toHtml();
$config = $editField->getAlpineConfiguration();
$verify($config['initialSlug'] === 'create-harbour' && $config['initialAutoSyncDisabled'] && $editField->canVisitLink(), 'edit hydrates existing slug, preserves it by default and permits Visit');
$verify($editField->validateSlugDraft('create-harbour')['valid'], 'edit excludes its own persisted record from uniqueness');
$verify(! $editField->validateSlugDraft('shared')['valid'], 'edit still rejects a different record collision');
$host->data['article']['title'] = 'Edited Harbour';
$verify($editField->getState() === 'create-harbour' && $created->title === 'Create Harbour', 'changing form title alone leaves preserved slug and original record intact');
$editField->preserveSlugOnEdit(false);
$verify(! $editField->getAlpineConfiguration()['initialAutoSyncDisabled'], 'edit can opt back into automatic title synchronization');
$regenerated = $editField->generateSlugPreview('Edited Harbour', force: true);
$verify($regenerated === 'edited-harbour' && $created->slug === 'create-harbour', 'explicit regeneration produces draft without mutating bound record');
$editField->state($regenerated);
$created->fill($edit->getState())->save();
$reloaded = SlugSpatieProbeRecord::findOrFail($id);
$verify($reloaded->title === 'Edited Harbour' && $reloaded->slug === 'edited-harbour' && DB::table('slug_spatie_probe')->count() === $before + 1, 'real edit form state updates same row and survives reload');

$manualField = SlugField::make('slug')->source('title');
$manual = $makeSchema(SlugSpatieProbeRecord::class, 'create', $manualField);
$manual->fill(['title' => 'Manual title', 'slug' => 'my-chosen-address']);
$manuallyCreated = new SlugSpatieProbeRecord;
$manuallyCreated->fill($manual->getState())->save();
$verify($manuallyCreated->fresh()->slug === 'my-chosen-address', 'manual create slug survives native saving hook');

$plainField = SlugField::make('slug')->source('title')->slugUnique(false);
$plain = $makeSchema(null, 'create', $plainField);
$plain->fill(['title' => 'Żółć & Friends', 'slug' => '']);
$verify($plainField->generateSlugPreview('Żółć & Friends') === 'zolc-friends', 'create without a model or Spatie configuration also generates');

$selfHealingModel = new SlugSpatieProbeRecord;
$selfHealingModel->configure = fn (SlugOptions $o): SlugOptions => $o->selfHealing('~');
$selfHealingField = SlugField::make('slug')->source('title')->urlHost('https://example.test')->urlPath('/articles/');
$selfHealing = $makeSchema($selfHealingModel, 'create', $selfHealingField);
$selfHealing->fill(['title' => 'Unpersisted', 'slug' => 'unpersisted']);
$verify($selfHealingField->getPermalinkRecordKey() === null && $selfHealingField->getFullPermalinkUrl('unpersisted') === 'https://example.test/articles/unpersisted', 'unsaved self-healing model never invents a record ID in the preview URL');
echo "PASS: 17 real Filament create/edit schema, validation, persistence and URL checks.\n";

// Exercise the convenience composition and actual native title update hook.
$app['config']->set('filament-flex-fields.slug.translatable_locales', []);
$titleGroup = SlugField::withTitle(fieldTitle: 'title', fieldSlug: 'slug');
$titleSchema = Schema::make($host)->key('slug-with-title-create')->model(SlugSpatieProbeRecord::class)
    ->operation('create')->statePath('data.article')->components([$titleGroup]);
$titleSchema->fill(['title' => '', 'slug' => '']);
$composed = $titleSchema->getFlatComponents(withHidden: true);
$titleInput = array_values(array_filter($composed, fn ($f): bool => $f instanceof FlexTextInput && $f->getName() === 'title'))[0];
$composedSlug = array_values(array_filter($composed, fn ($f): bool => $f instanceof SlugField))[0];
$titleInput->state('Łódź & Friends')->callAfterStateUpdated();
$verify($composedSlug->getState() === 'lodz-friends', 'withTitle create native title hook automatically fills slug');
$titleInput->state('Shared')->callAfterStateUpdated();
$verify($composedSlug->getState() === 'shared-2', 'withTitle create synchronization uses native unique suffix generation');
$verify($titleSchema->getState()['slug'] === 'shared-2', 'withTitle generated create slug participates in actual form dehydration');
$host->data['article'][TitleSlugField::autoUpdateDisabledFieldName('slug')] = true;
$composedSlug->state('manual-with-title');
$titleInput->state('Another Title')->callAfterStateUpdated();
$verify($composedSlug->getState() === 'manual-with-title', 'withTitle manual override stops title from replacing slug');

$titleEditGroup = SlugField::withTitle(fieldTitle: 'title', fieldSlug: 'slug');
$titleEditSchema = Schema::make($host)->key('slug-with-title-edit')->model($reloaded)
    ->operation('edit')->statePath('data.article')->components([$titleEditGroup]);
$titleEditSchema->fill($reloaded->attributesToArray());
$editComposed = $titleEditSchema->getFlatComponents(withHidden: true);
$editTitleInput = array_values(array_filter($editComposed, fn ($f): bool => $f instanceof FlexTextInput && $f->getName() === 'title'))[0];
$editComposedSlug = array_values(array_filter($editComposed, fn ($f): bool => $f instanceof SlugField))[0];
$editTitleInput->state('Do not replace persisted slug')->callAfterStateUpdated();
$verify($editComposedSlug->getState() === 'edited-harbour', 'withTitle edit title hook preserves the existing slug by default');
$plainTitleGroup = SlugField::withTitle(fieldTitle: 'title', fieldSlug: 'slug');
$plainTitleSchema = Schema::make($host)->key('slug-with-title-no-model')->operation('create')
    ->statePath('data.article')->components([$plainTitleGroup]);
$plainTitleSchema->fill(['title' => '', 'slug' => '']);
$plainComposed = $plainTitleSchema->getFlatComponents(withHidden: true);
$plainTitle = array_values(array_filter($plainComposed, fn ($f): bool => $f instanceof FlexTextInput && $f->getName() === 'title'))[0];
$plainSlug = array_values(array_filter($plainComposed, fn ($f): bool => $f instanceof SlugField))[0];
$plainTitle->state('Żółć & Friends')->callAfterStateUpdated();
$verify($plainSlug->getState() === 'zolc-friends', 'withTitle automatically generates without a model or Spatie');
$verify($plainSlug->getAlpineConfiguration()['sourcePath'] === 'data.article.title', 'withTitle resolves the title inside the actual nested form root');

$syncEditGroup = TitleSlugField::make(fieldTitle: 'title', fieldSlug: 'slug', translatableLocales: [], preserveSlugOnEdit: false);
$syncEditSchema = Schema::make($host)->key('title-slug-auto-edit')->model($reloaded)->operation('edit')
    ->statePath('data.article')->components([$syncEditGroup]);
$syncEditSchema->fill($reloaded->attributesToArray());
$syncComponents = $syncEditSchema->getFlatComponents(withHidden: true);
$syncTitle = array_values(array_filter($syncComponents, fn ($f): bool => $f instanceof FlexTextInput && $f->getName() === 'title'))[0];
$syncSlug = array_values(array_filter($syncComponents, fn ($f): bool => $f instanceof SlugField))[0];
$syncTitle->state('Automatic Edit Title')->callAfterStateUpdated();
$verify($syncSlug->getState() === 'automatic-edit-title', 'TitleSlugField explicitly enables title synchronization on edit');
echo "PASS: 8 title composition and automatic title-update checks.\n";
