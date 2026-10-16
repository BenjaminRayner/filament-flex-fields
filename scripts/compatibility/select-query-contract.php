<?php

declare(strict_types=1);
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect\UserSelectRecordMapper;
use Bjanczak\FilamentFlexFields\Support\Select\Data\TransportOptionTree;
use Bjanczak\FilamentFlexFields\Support\Select\Pagination\SearchPageNormalizer;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\BoundedJsonPayload;
use Bjanczak\FilamentFlexFields\Support\UserSelectQueryCache;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
function verify(bool $truth, string $message): void
{
    if (! $truth) {
        throw new RuntimeException($message);
    }
}
function rejects(Closure $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Malformed contract accepted');
}
$c = new Container;
Container::setInstance($c);
$c->instance('config', new Repository(['filament-flex-fields' => ['select' => []]]));
$c->singleton(UserSelectQueryCache::class);
$db = new Manager($c);
$db->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$db->setAsGlobal();
$db->bootEloquent();
$db->schema()->create('probe_users', function ($t) {
    $t->increments('id');
    $t->string('slug')->unique();
    $t->string('name');
    $t->string('email')->nullable();
    $t->string('avatar')->nullable();
    $t->timestamp('email_verified_at')->nullable();
    $t->integer('tenant');
    $t->string('private_note');
});
class ProbeUserModel extends Model
{
    protected $table = 'probe_users';

    public $timestamps = false;

    protected $guarded = [];
}
class ProbeOwnerModel extends Model
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(ProbeUserModel::class, 'chosen_slug', 'slug');
    }
}
class ProbeUserField extends UserSelect
{
    public mixed $selection = null;

    public ?Model $owner = null;

    public function getState(): mixed
    {
        return $this->selection;
    }

    public function getRecord(bool $withContainerRecord = true): ?Model
    {
        return $this->owner;
    }

    public function getRelationship(): ?BelongsTo
    {
        return $this->owner?->person();
    }

    public function stateObject(): object
    {
        return $this->runtimeState();
    }

    public function mapperObject(): object
    {
        return $this->recordMapper();
    }
}
foreach ([
    [
        'slug' => 'alpha',
        'name' => 'Alpha Beta',
        'tenant' => 1,
    ],
    [
        'slug' => 'other',
        'name' => 'Alpha Beta Hidden',
        'tenant' => 2,
    ],
    [
        'slug' => 'wild',
        'name' => 'Al%_! literal',
        'tenant' => 1,
    ],
    [
        'slug' => 'normal',
        'name' => 'AlXX regular',
        'tenant' => 1,
    ],
] as $row) {
    ProbeUserModel::create($row + ['email' => 'user@example.com', 'private_note' => 'full-record-edit']);
}
$field = new ProbeUserField('assignee');
$field->optionModel(ProbeUserModel::class)->query(fn (Builder $query) => $query->where('tenant', 1));
verify(array_keys($field->searchRecords('Alpha Beta')) === [1], 'Search scope');
$field->applySearchUsing(fn (Builder $query) => $query->whereRaw('1=0'));
verify($field->searchRecords('Alpha Beta') === [], 'Custom search bypassed by fallback');
$field->applySearchUsing(null)->forceSearchCaseInsensitive()->searchable(['name']);
verify(array_keys($field->searchRecords('AL%_!')) === [3], 'Literal wildcards or forced case');
$field->searchable(['email']);
verify($field->searchRecords('Alpha') === [], 'Native search columns ignored');
$field->searchable(['name']);
$field->selection = 1;
verify($field->getSelectedRecord()?->private_note === 'full-record-edit', 'Selected record projection broke edit');
$field->selection = 2;
verify($field->getSelectedRecord() === null, 'Single record scope bypass');
$field->selection = 1;
verify($field->getSelectedRecord()?->id === 1, 'Selected record state cache stale');
$before = $field->stateObject();
$mapper = $field->mapperObject();
$clone = clone $field;
verify($clone->stateObject() !== $before && $clone->mapperObject() !== $mapper, 'Field clone shares collaborators');
$clone->query(fn (Builder $query) => $query->where('tenant', 2));
verify(array_keys($clone->searchRecords('Alpha Beta')) === [2] && array_keys($field->searchRecords('Alpha Beta')) === [1], 'Clone callbacks refer to original');
$owner = new ProbeOwnerModel;
$owner->chosen_slug = 'other';
$relation = new ProbeUserField('person');
$relation->owner = $owner;
$relation->relationship('person', 'name')->query(fn (Builder $query) => $query->where('tenant', 1));
verify((new UserSelectRecordMapper($relation))->resolveModelKeyName() === 'slug', 'Alternate relationship key');
$relation->selection = 'other';
verify($relation->getSelectedRecord() === null, 'Single relationship own scope bypass');
$relation->selection = 'alpha';
verify($relation->getSelectedRecord()?->slug === 'alpha', 'Alternate key lookup');
verify($relation->resolveOptionLabelsForValues(['alpha', 'other']) === ['alpha' => 'Alpha Beta'], 'Batch relationship scope/key mismatch');
$relation->getSelectedRecordUsing(fn () => ProbeUserModel::find(2));
verify($relation->getSelectedRecord() === null, 'Custom record changed selected identity');
$relation->getSelectedRecordUsing(fn () => null);
verify($relation->getSelectedRecord() === null, 'Custom null resolver ignored');
$relation->getSelectedRecordUsing(function (ProbeOwnerModel $record, mixed $state): ?Model {
    verify($record->chosen_slug === 'other', 'Native owning-record injection changed');

    return ProbeUserModel::where('slug', $state)->first();
});
verify($relation->getSelectedRecord()?->slug === 'alpha', 'Native typed selected-record callback failed');
$field->options(['a' => ['label' => 'First', 'image' => null], 'Nested' => ['b' => 'Second']]);
verify($field->resolveOptionLabelsForValues([
    'a',
    'b',
    'a',
]) === ['a' => 'First', 'b' => 'Second'], 'Grouped static batch labels');
rejects(fn () => $field->options(['bad' => ['label' => ['oops']]])->resolveOptionLabelsForValues(['bad']));
class ProbeSelectField extends SelectField
{
    public string $disabledKey = 'a';
}
$policy = new ProbeSelectField('policy');
$policy->disabledOptions(fn (SelectField $component) => [$component->disabledKey]);
$policyClone = clone $policy;
$policyClone->disabledKey = 'b';
verify($policy->isOptionDisabled('a', 'A') && ! $policy->isOptionDisabled('b', 'B') && $policyClone->isOptionDisabled('b', 'B') && ! $policyClone->isOptionDisabled('a', 'A'), 'Cloned disabled policy bound to original');
$policy->getSearchResultsUsing(fn () => ['a' => 'First']);
verify($policy->getSearchResults('a') === ['a' => 'First'], 'Initial search cache');
$calls = 0;
$policy->getSearchResultsUsing(function () use (&$calls) {
    $calls++;

    return ['b' => 'Second'];
});
$policy->getSearchResults('a');
$policy->options(['b' => 'Second']);
$policy->getSearchResults('a');
verify($calls === 2, 'Replacing options did not invalidate provider cache');
$normalizer = new SearchPageNormalizer;
$page = $normalizer->normalize(collect([
    'items' => [
        ['id' => 'a'],
        ['value' => 'a'],
        ['label' => 'Group', 'options' => [['value' => 2]]],
    ],
    'cursor' => 'next',
    'hasMore' => true,
]), 3);
verify(count($page->items) === 2 && $page->items[1]['options'][0]['value'] === 2 && $page->cursor === 'next', 'Page alias/group/dedup contract');
foreach ([
    fn () => $normalizer->normalize(['cursor' => 'next'], 1),
    fn () => $normalizer->normalize('oops', 1),
    fn () => $normalizer->normalize([['value' => 'x', 'meta' => new stdClass]], 1),
    fn () => $normalizer->normalize([['value' => 'x', 'label' => "\xb1"]], 1),
    fn () => $normalizer->normalize([['value' => 'x', 'meta' => INF]], 1),
    fn () => $normalizer->normalize([
        'items' => [],
        'cursor' => 'same',
        'hasMore' => true,
    ], 1, 'same'),
    fn () => $normalizer->normalize([['value' => 'x', 'label' => str_repeat('x', 100)]], 1, maxBytes: 50),
    fn () => $normalizer->normalize([['label' => 'Group', 'options' => [['value' => 1], ['value' => 2]]]], 1),
] as $case) {
    rejects($case);
}
$cycle = [];
$cycle['self'] = &$cycle;
rejects(fn () => (new BoundedJsonPayload)->measure($cycle, 10000));
foreach ([
    [],
    ['label' => 'żółć/"'],
    ['items' => [
        1,
        true,
        null,
        1.25,
    ]],
    ['0' => 'zero', 'x' => 'x'],
] as $data) {
    verify((new BoundedJsonPayload)->measure($data, 10000) === strlen(json_encode($data, JSON_THROW_ON_ERROR)), 'Encoded byte measurement');
}
$tree = [['label' => 'Group', 'options' => [
    ['value' => 1],
    ['value' => 2],
    ['value' => 3],
]], ['value' => 4]];
verify(count(iterator_to_array((new TransportOptionTree)->leaves((new TransportOptionTree)->take($tree, 2)))) === 2, 'Fallback counts groups rather than leaves');
$scopeCalls = 0;
ProbeUserModel::addGlobalScope('once', function (Builder $query) use (&$scopeCalls) {
    $scopeCalls++;
    $query->where('tenant', 1);
});
$cache = new UserSelectQueryCache;
$records = $cache->remember(ProbeUserModel::query(), 'scope-probe');
verify($scopeCalls === 1 && $records->count() === 3, 'Query cache applied scopes twice');
echo "PASS: SQLite scopes, alternate keys, callback authority, full native-edit records, clone isolation, literal/case/column search, grouped batch labels, cache invalidation, bounded page protocol and scoped query execution.\n";
