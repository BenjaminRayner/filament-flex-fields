<?php

declare(strict_types=1);

require __DIR__.'/core-family-schema.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildComponentContract;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildFactory;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildRenderContext;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildRenderer;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildSlot;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildStatePath;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationOption as Option;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema as Options;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationType as Type;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

final class LegacySelectChildFactory implements ChildFactory
{
    public function make(ChildRenderContext $context): Component
    {
        return SelectField::make($context->statePath)->label($context->configuration['caption'])->options(['legacy' => 'Legacy']);
    }
}

final class CoreCompositionAuthor extends Model
{
    protected $connection = 'fff_composition_probe';

    protected $table = 'authors';

    protected $guarded = [];

    public $timestamps = false;
}
final class CoreCompositionArticle extends Model
{
    protected $connection = 'fff_composition_probe';

    protected $table = 'articles';

    protected $guarded = [];

    public $timestamps = false;

    public function author(): BelongsTo
    {
        return $this->belongsTo(CoreCompositionAuthor::class, 'author_id');
    }
}

function reusableCompositionGroup(string $owner, int $pageSize = 2): CompositeField
{
    $children = new ChildRenderer(app(ComponentRegistry::class), 'reusable-parent', new FamilyRenderContext($owner), ['pageSize' => $pageSize]);

    return CompositeField::make('group')->assetComponent('reusable-parent')->schema([
        $children->render('country', 'main', ['options' => ['PL' => 'Poland', 'US' => 'United States'], 'required' => true],
            configure: static fn (SelectField $field) => $field->live()),
        $children->render('city', 'main', ['required' => true], configure: static function (SelectField $field, ChildRenderContext $context): void {
            $field->dependsOn($context->pathTo('country', 'main'), static fn ($country): array => $country === 'PL'
                ? ['warsaw' => 'Warsaw'] : ['new-york' => 'New York']);
        }),
        $children->render('remote', 'main', ['searchable' => true, 'paginatedSearchResults' => true, 'virtualizeThreshold' => 2, 'virtualRowHeight' => 28, 'virtualWindowSize' => 12],
            configure: static function (SelectField $field): void {
                $field->getOptionLabelUsing(static fn ($value): ?string => is_numeric($value) && (int) $value >= 0 && (int) $value < 6 ? 'Result '.$value : null);
                $field->getSearchResultsPageUsing(static function (string $search, ?string $cursor, int $pageSize): array {
                    $offset = $cursor === null ? 0 : (int) $cursor;
                    $items = [];
                    for ($i = $offset; $i < min(6, $offset + $pageSize); $i++) {
                        $items[] = ['value' => (string) $i, 'label' => $search.' result '.$i];
                    }
                    $end = min(6, $offset + $pageSize);

                    return ['items' => $items, 'cursor' => $end < 6 ? (string) $end : null, 'hasMore' => $end < 6];
                });
            }),
    ]);
}
function reusableCompositionState(string $country, string $city): array
{
    return ['group' => ['country' => ['k_'.hash('sha256', 'main') => $country], 'city' => ['k_'.hash('sha256', 'main') => $city]]];
}
final class ReusableCompositionLivewireHost extends V590SchemaHost
{
    public int $pageSize = 2;

    public array $saved = [];

    public function mount(): void
    {
        $this->form->fill(reusableCompositionState('PL', 'warsaw'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([reusableCompositionGroup('livewire:'.$this->getId(), $this->pageSize)]);
    }

    public function submit(): void
    {
        $this->saved = $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

$checks = [];
$registry = ComponentRegistry::builtIn();
$registry->registerBatch([new ComponentDefinition('reusable-parent', ComponentKind::Field, stylesFrom: ['composite-field'], children: [
    'country' => new ChildSlot('country', 'select-field', ComponentKind::Field),
    'city' => new ChildSlot('city', 'select-field', ComponentKind::Field),
    'remote' => new ChildSlot('remote', 'select-field', ComponentKind::Field, configurationMap: ['pageSize' => 'searchResultsPageSize']),
    'author' => new ChildSlot('author', 'select-field', ComponentKind::Field),
])]);
$registry->freeze();
app()->instance(ComponentRegistry::class, $registry);
$base = $registry->definition('select-field')->childContract;
schemaProof($registry->childSlot('reusable-parent', 'city')->contract === $base && $registry->childSlot('reusable-parent', 'remote')->contract === $base, 'Reusable child metadata copied or replaced.');
schemaProof($registry->runtimeComponents('reusable-parent') === ['reusable-parent'], 'Inherited profile eagerly imported the select runtime.');
$checks[] = 'minimal child slots reuse the same registered Select contract without copying factories/options or eager runtime assets';

$host = new V590SchemaHost;
$host->setId('reusable-select-schema');
$group = reusableCompositionGroup('plain');
$schema = Schema::make($host)->statePath('data')->components([$group]);
$schema->fill(reusableCompositionState('PL', 'warsaw'));
[$country, $city, $remote] = $group->getChildSchema()->getComponents();
schemaProof(array_column($city->getOptionsForJs(), 'value') === ['warsaw'], 'Declared sibling dependency resolved another path.');
$host->data['group']['country']['k_'.hash('sha256', 'main')] = 'US';
schemaProof(array_column($city->getOptionsForJs(), 'value') === ['new-york'], 'Sibling dependency did not reevaluate current state.');
$host->data['group']['city']['k_'.hash('sha256', 'main')] = 'new-york';
$schema->getState();
$checks[] = 'real Select options/dependency/validation resolve declared sibling keys through the native parent schema';
$firstPage = $remote->getSearchResultsPageForJs('needle');
$nextPage = $remote->getSearchResultsPageForJs('needle', $firstPage['cursor']);
schemaProof(count($firstPage['items']) === 2 && $firstPage['cursor'] === '2' && count($nextPage['items']) === 2 && $nextPage['cursor'] === '4', 'Mapped paging contract or PHP callback lost.');
schemaProof($remote->getSelectSurfaceConfiguration()['virtualRowHeight'] === 28 && $remote->getSelectSurfaceConfiguration()['virtualWindowSize'] === 12, 'Virtualization parameters not applied to actual Select.');
$checks[] = 'inherited virtualization/page size and full PHP page provider return real independent cursor pages';
$before = $registry->revision();
schemaReject(fn () => new ChildComponentContract(ComponentKind::Field, SelectField::class, new Options(['getState' => new Option(Type::String)])));
schemaReject(fn () => new ChildComponentContract(ComponentKind::Field, SelectField::class, new Options(['make' => new Option(Type::String)])));
schemaReject(fn () => new ChildComponentContract(ComponentKind::Field, SelectField::class, new Options(['relationship' => new Option(Type::String)]), bindings: ['relationship' => ['missing']]));
schemaReject(fn () => (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('bad')))->render('city', 'main', ['method' => 'delete']));
schemaReject(fn () => (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('bad-type')))->render('city', 'main', ['searchable' => 'false']));
schemaReject(fn () => (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('bad-bound')))->render('city', 'main', ['virtualRowHeight' => 0]));
$empty = (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('empty')))->render('city', 'main', ['options' => [], 'searchable' => false, 'placeholder' => null]);
schemaProof($empty->getOptions() === [] && ! $empty->isSearchable() && $empty->getPlaceholder() === null, 'Explicit empty/false/null child options lost.');
schemaProof($registry->revision() === $before, 'Invalid child options changed the frozen registry.');
$checks[] = 'only installed public fluent bindings execute; static/getter/unknown options and undeclared arguments reject';
schemaReject(fn () => (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('replacement')))->render('city', 'main', configure: static fn () => SelectField::make('replacement')));
schemaReject(fn () => (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('undeclared-path')))->render('city', 'main', configure: static fn ($field, ChildRenderContext $context) => $field->dependsOn($context->pathTo('undeclared', 'main'), static fn (): array => [])));

$attempt = new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('retry-callback'), readOnly: true);
try {
    $attempt->render('city', 'main', configure: static function (): void {
        throw new RuntimeException('Expected callback failure.');
    });
    throw new LogicException('Callback failure disappeared.');
} catch (RuntimeException $error) {
    schemaProof($error->getMessage() === 'Expected callback failure.', 'Wrong callback error.');
}
$retried = $attempt->render('city', 'main', configure: static fn (SelectField $field) => $field->disabled(false)->dehydrated()->statePath('unsafe')->id('unsafe'));
schemaProof($retried->isDisabled() && ! $retried->isDehydrated() && $retried->getStatePath(false) === ChildStatePath::for('city', 'main'), 'PHP hook bypassed owner/write restrictions.');
$checks[] = 'full PHP hook is request-local; failure releases key and cannot override final state/readonly ownership';

$repeater = Repeater::make('rows')->schema([reusableCompositionGroup('repeated-template')]);
$repeatHost = new V590SchemaHost;
$repeatHost->setId('reusable-repeat-schema');
$repeatSchema = Schema::make($repeatHost)->statePath('data')->components([$repeater]);
$repeatSchema->fill(['rows' => ['row_a' => reusableCompositionState('PL', 'warsaw'), 'row_b' => reusableCompositionState('US', 'new-york')]]);
$collect = static function (Repeater $field): array {
    $result = [];
    foreach ($field->getChildSchemas() as $item) {
        $container = $item->getComponents()[0];
        foreach ($container->getChildSchema()->getComponents() as $child) {
            $result[$child->getStatePath()] = $child->getId();
        }
    }
    ksort($result);

    return $result;
};
$ids = $collect($repeater);
schemaProof(count($ids) === 6 && count(array_unique($ids)) === 6, 'Native repeater clones share child DOM IDs.');
$repeatHtml = $repeatSchema->toHtml();
schemaProof(substr_count($repeatHtml, 'data-fff-lazy-alpine-mount="select-field"') === 6, 'Repeated actual Select views lost independent runtime boundaries.');
foreach ($ids as $id) {
    schemaProof(str_contains($repeatHtml, 'id="'.htmlspecialchars($id, ENT_QUOTES).'"'), 'Native child label/control identity not rendered.');
}
$repeatHost->data['rows'] = array_reverse($repeatHost->data['rows'], true);
schemaProof($collect($repeater) === $ids, 'Repeater reorder changed stable child DOM IDs.');
$repeatSchema->getState();
$checks[] = 'actual native repeater cloning/rendering/reorder keeps six independent Select boundaries and distinct child IDs, state, dependencies and validation';

Livewire::component('fff-reusable-composition', ReusableCompositionLivewireHost::class);
$live = Livewire::test(ReusableCompositionLivewireHost::class);
$oldFields = $live->instance()->form->getFlatFields();
$oldIds = array_map(static fn ($field) => $field->getId(), $oldFields);
$live->set('data.group.country.k_'.hash('sha256', 'main'), 'US')
    ->set('data.group.city.k_'.hash('sha256', 'main'), 'new-york')->call('submit')->assertHasNoErrors();
schemaProof(data_get($live->get('saved'), ChildStatePath::for('city', 'main')) === null && data_get($live->get('saved'), 'group.'.ChildStatePath::for('city', 'main')) === 'new-york', 'Livewire sibling state escaped its container.');
$live->set('pageSize', 3);
$newFields = $live->instance()->form->getFlatFields();
schemaProof(array_map(static fn ($field) => $field->getId(), $newFields) === $oldIds, 'Livewire configuration replacement changed child identity.');
$pageField = current(array_filter($newFields, static fn ($field) => $field->getStatePath(false) === ChildStatePath::for('remote', 'main')));
schemaProof($pageField instanceof SelectField && count($pageField->getSearchResultsPageForJs('new')['items']) === 3, 'Livewire configuration retained previous page size.');
$checks[] = 'real Livewire requests replace child configuration and dependent state while retaining stable DOM identities';
$live->set('data.group.country.k_'.hash('sha256', 'main'), 'forged')->call('submit')->assertHasErrors(['data.group.country.k_'.hash('sha256', 'main')]);
$live->set('data.group.country.k_'.hash('sha256', 'main'), 'PL')->set('data.group.city.k_'.hash('sha256', 'main'), 'new-york')->call('submit')->assertHasErrors(['data.group.city.k_'.hash('sha256', 'main')]);
$live->set('data.group.city.k_'.hash('sha256', 'main'), 'warsaw')->set('data.group.extra', 'forged')->call('submit')->assertHasErrors(['data.group']);
$checks[] = 'native Livewire save rejects unknown selection, mismatched dependent selection and injected container keys';

$authorRegistry = new ComponentRegistry;
foreach (ComponentRegistry::builtIn()->all() as $definition) {
    $authorRegistry->register($definition->id === 'flex-text-input' ? new ComponentDefinition(...array_replace(get_object_vars($definition), [
        'childContract' => new ChildComponentContract(ComponentKind::Field, FlexTextInput::class, new Options([
            'maxLength' => new Option(Type::Integer, default: 4, locked: true),
            'caption' => new Option(Type::String),
            'inlineCaption' => new Option(Type::Boolean),
        ]), bindings: ['prefix' => ['caption', 'inlineCaption']]),
    ])) : $definition);
}
$authorRegistry->registerBatch([new ComponentDefinition('locked-parent', ComponentKind::Field, children: [
    'text' => new ChildSlot('text', 'flex-text-input', ComponentKind::Field),
    'shared' => new ChildSlot('shared', 'flex-text-input', ComponentKind::Field, shareState: true),
    'legacy' => new ChildSlot('legacy', 'select-field', ComponentKind::Field, SelectField::class, LegacySelectChildFactory::class, new Options(['caption' => new Option(Type::String, default: 'Legacy label')])),
])]);
$authorRegistry->freeze();
$lockedRenderer = new ChildRenderer($authorRegistry, 'locked-parent', new FamilyRenderContext('locked'));
$locked = $lockedRenderer->render('text', 'main', ['caption' => 'Prefix', 'inlineCaption' => true], configure: static fn (FlexTextInput $field) => $field->maxLength(99)->placeholder('Full PHP API'));
schemaProof($locked->getMaxLength() === 4 && $locked->getPrefixLabel() === 'Prefix' && $locked->isPrefixInline() && $locked->getPlaceholder() === 'Full PHP API', 'Automatic/multi-argument bindings or locked policy lost.');
schemaReject(fn () => $lockedRenderer->render('text', 'invalid-policy', ['maxLength' => 99]));
schemaReject(fn () => $lockedRenderer->render('text', 'incomplete', ['caption' => 'Incomplete']));
$lockedRenderer->render('text', 'incomplete', ['caption' => 'Complete', 'inlineCaption' => false]);
$shared = $lockedRenderer->render('shared', 'main', configure: static function (FlexTextInput $field, ChildRenderContext $context): void {
    schemaProof($context->pathTo('shared', 'main') === './', 'Shared sibling did not reference its actual container root.');
});
Schema::make($host)->statePath('data')->components([$shared])->getComponents();
schemaProof($shared->getStatePath() === 'data' && $lockedRenderer->render('legacy', 'main')->getLabel() === 'Legacy label', 'Shared state or explicit legacy factory configuration changed.');
$checks[] = 'author contract auto-binds approved options once; grouped arguments, locked policy after PHP hooks, shared sibling paths and legacy explicit factories retain their contracts';

// This connection is explicitly isolated; no host database, model or migration is touched.
$app['config']->set('database.connections.fff_composition_probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
$db = $app['db']->connection('fff_composition_probe');
$db->getSchemaBuilder()->create('authors', static function ($table): void {
    $table->increments('id');
    $table->string('name');
    $table->boolean('active');
});
$db->getSchemaBuilder()->create('articles', static function ($table): void {
    $table->increments('id');
    $table->unsignedInteger('author_id')->nullable();
});
$visible = CoreCompositionAuthor::create(['name' => 'Visible', 'active' => true]);
$hidden = CoreCompositionAuthor::create(['name' => 'Hidden', 'active' => false]);
$article = CoreCompositionArticle::create(['author_id' => $visible->getKey()])->fresh();
$relationField = (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('relationship')))->render('author', 'main', ['searchable' => true],
    configure: static fn (SelectField $field) => $field->relationship('author', 'name', modifyQueryUsing: static fn (Builder $query): Builder => $query->where('active', true)));
$relationGroup = CompositeField::make('group')->schema([$relationField]);
$relationHost = new V590SchemaHost;
$relationHost->setId('relation-schema');
$relationSchema = Schema::make($relationHost)->statePath('data')->model($article)->components([$relationGroup]);
$relationSchema->fill($article->toArray());
schemaProof($relationField->getRelationshipName() === 'author' && $relationField->getSearchResults('') === [$visible->getKey() => 'Visible'], 'Relationship/query scope lost inside parent.');
schemaProof($relationField->getSelectedRecord()?->getKey() === $visible->getKey(), 'Edit hydration lost relationship selection.');
$relationHost->data['group']['author']['k_'.hash('sha256', 'main')] = $hidden->getKey();
$freshRelation = (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('relationship')))->render('author', 'main', ['searchable' => true],
    configure: static fn (SelectField $field) => $field->relationship('author', 'name', modifyQueryUsing: static fn (Builder $query): Builder => $query->where('active', true)));
$relationGroup->schema([$freshRelation]);
try {
    $relationSchema->getState();
    throw new RuntimeException('Out-of-scope relation selection accepted.');
} catch (ValidationException $error) {
    schemaProof(isset($error->errors()[$relationField->getStatePath()]), 'Relation rejection mapped to another child.');
}
$checks[] = 'real isolated SQLite relationship search/edit hydration retains query scope and rejects forged inaccessible selection';
$other = CoreCompositionAuthor::create(['name' => 'Other visible', 'active' => true]);
$relationHost->data['group']['author']['k_'.hash('sha256', 'main')] = $other->getKey();
$relationSchema->getState();
$article->save();
schemaProof($article->fresh()->author_id === $other->getKey(), 'Nested edit relationship did not persist to its actual record.');
$createField = (new ChildRenderer($registry, 'reusable-parent', new FamilyRenderContext('relationship-create')))->render('author', 'main', ['searchable' => true], configure: static fn (SelectField $field) => $field->relationship('author', 'name', modifyQueryUsing: static fn (Builder $query): Builder => $query->where('active', true)));
$createGroup = CompositeField::make('group')->schema([$createField]);
$createHost = new V590SchemaHost;
$createHost->setId('relation-create-schema');
$createSchema = Schema::make($createHost)->statePath('data')->model(CoreCompositionArticle::class)->components([$createGroup]);
$createSchema->fill(['group' => ['author' => ['k_'.hash('sha256', 'main') => $visible->getKey()]]]);
$createSchema->getState();
$created = CoreCompositionArticle::create([]);
$createSchema->model($created)->saveRelationships();
schemaProof($created->fresh()->author_id === $visible->getKey(), 'Nested create relationship did not persist after attaching the saved record.');
$checks[] = 'isolated native relationship lifecycle persists nested edit with the application model save and create after the saved record is attached';
$app['db']->purge('fff_composition_probe');

echo json_encode(['groups' => count($checks), 'proofs' => $checks, 'testsModified' => false, 'hostDatabaseTouched' => false], JSON_THROW_ON_ERROR).PHP_EOL;
