<?php

declare(strict_types=1);

require __DIR__.'/location-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SlugField;
use Filament\Schemas\Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\ValidationException;

$field = SlugField::make('slug')->source('title')->autoUpdateDisabledField('manual')->inlineEditPendingField('pending')
    ->allowHomepageSlug()->slugPattern('/^[a-z0-9]+$/')->urlHost('https://example.test')->slugUnique(false);
$schema = Schema::make($host)->key('slug-probe')->statePath('data.article')->components([
    FlexTextInput::make('title'), $field,
]);
$host->data = ['article' => ['title' => 'Hello world', 'slug' => '/', 'manual' => false, 'pending' => false]];
$schema->getFlatComponents(withHidden: true);
$config = $field->getAlpineConfiguration();
locationCheck($config['statePath'] === 'data.article.slug' && $config['sourcePath'] === 'data.article.title', 'Nested source binding');
locationCheck($config['inlineEditPendingPath'] === 'data.article.pending' && $config['autoUpdateDisabledPath'] === 'data.article.manual', 'Nested sibling binding');
$html = $field->toHtml();
$dom = new DOMDocument;
libxml_use_internal_errors(true);
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
locationCheck($xpath->query('//input')->length === 1, 'Slug must render one shared native input');
locationCheck($xpath->query('//*[@role="group"]//button[@role="radio"]')->length === 0, 'Commands must not pretend to be radios');
locationCheck(! str_contains($html, '@js('), 'Uncompiled component directive');
locationCheck($xpath->query('//button[@aria-label="Edit"]//*[local-name()="svg"]')->length === 1, 'Shared command icon');
locationCheck($field->validateSlugDraft('/')['valid'], 'Homepage must remain valid with a custom standard-slug pattern');
locationCheck($field->slugRules(['max:4'])->validateSlugDraft('longer')['valid'] === false, 'Draft and final validation must enforce configured rules');
$field->slugRules([])->regex(null);
locationCheck($field->slugSeparator('--')->validateSlugDraft('hello--world')['slug'] === 'hello--world', 'Literal separator');
$host->data['article']['pending'] = true;
try {
    $schema->getState();
    throw new RuntimeException('Pending inline edit was accepted');
} catch (ValidationException $exception) {
    locationCheck(isset($exception->errors()['data.article.slug']), 'Pending error path');
}
$host->data['article']['pending'] = false;

$app['config']->set('database.default', 'sqlite');
$app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
DatabaseSchema::create('slug_candidates', function ($table): void {
    $table->increments('id');
    $table->string('slug');
    $table->integer('tenant');
    $table->boolean('active');
});
DB::table('slug_candidates')->insert([
    ['slug' => 'shared', 'tenant' => 1, 'active' => false],
    ['slug' => 'shared', 'tenant' => 2, 'active' => true],
]);
$field->slugSeparator('-')->slugUnique()->slugUniqueScope(fn (Builder $query): Builder => $query->where('tenant', 1))
    ->slugUniqueParameters(['table' => 'slug_candidates', 'modifyRuleUsing' => fn (Unique $rule): Unique => $rule->where('active', 1)]);
locationCheck($field->isSlugValueUnique('shared'), 'Scope and native rule must intersect on the same rows');
$field->slugUniqueParameters(['table' => 'slug_candidates', 'modifyRuleUsing' => fn (Unique $rule): Unique => $rule->where('active', 0)]);
locationCheck(! $field->isSlugValueUnique('shared'), 'Scoped duplicate must be rejected');
$field->slugUniqueParameters(['table' => 'slug_candidates', 'ignorable' => 1]);
locationCheck($field->isSlugValueUnique('shared'), 'Scalar ignore and scope');
$field->slugUnique(false);
locationCheck($field->isSlugValueUnique('shared'), 'Configuration updated after mounting must be respected');
echo "PASS Slug composition: nested bindings, actual SSR, homepage, rules, pending edit, scoped uniqueness\n";
