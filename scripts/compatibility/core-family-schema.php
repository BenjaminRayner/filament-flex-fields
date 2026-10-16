<?php

declare(strict_types=1);

try {
    require __DIR__.'/schema-render-contract.php';
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildComponentContract;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildFactory;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildRenderContext;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildRenderer;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildSlot;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationOption as Option;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema as Options;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationType as Type;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaAdapter;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaAvailability;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaCodec;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaContract;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaMigration;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaRenderer;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaSubmission;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueMigration;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshot;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\ResolvedFamilySchema;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldInfolistBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldTableBuilder;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueSubmission;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

function schemaProof(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function schemaReject(Closure $operation): void
{
    try {
        $operation();
    } catch (LogicException|AuthorizationException $error) {
        return;
    }
    throw new RuntimeException('Invalid schema/composition accepted.');
}
final class SchemaTextFactory implements ChildFactory
{
    public function make(ChildRenderContext $context): Component
    {
        return FlexTextInput::make($context->statePath)->label($context->configuration['locale'])
            ->required()->maxLength($context->configuration['maxLength']);
    }
}
final class SchemaWrongFactory implements ChildFactory
{
    public static bool $wrong = true;

    public function make(ChildRenderContext $context): Component
    {
        return self::$wrong ? CompositeField::make($context->statePath) : FlexTextInput::make($context->statePath);
    }
}
final class SchemaFamilyAdapter implements FamilySchemaAdapter
{
    public static array $calls = [];

    public static int $migrations = 0;

    public function authorize(FamilySchemaDefinition $definition, FamilyRenderContext $context): bool
    {
        return $context->userKey === 'permitted' && $context->tenantId === 'tenant-one';
    }

    public function configure(Field|Column|Entry $surface, FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): void
    {
        self::$calls[] = $configuration;
        if ($surface instanceof CompositeField) {
            $children = new ChildRenderer(app(ComponentRegistry::class), 'author-field',
                new FamilyRenderContext($context->identity('author-field', $definition->slug), locale: $context->locale),
                $configuration, disabled: $definition->isDisabled, readOnly: $definition->isReadOnly);
            $surface->assetComponent('author-field')->schema([
                $children->render('text', 'first', ['locale' => 'en']),
                $children->render('text', 'second', ['locale' => 'pl']),
            ]);
        }
    }

    public function rules(FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): array
    {
        return ['array'];
    }

    public function migrateConfiguration(array $configuration, int $fromVersion, int $toVersion): array
    {
        self::$migrations++;
        if ($fromVersion !== 1 || $toVersion !== 2) {
            throw new InvalidArgumentException('No migration for this version.');
        }

        return ['limit' => $configuration['oldLimit'] ?? 7];
    }
}

final class LifecycleFamilyValueRule implements ValidationRule
{
    public function __construct(private readonly int $limit) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || ! is_string($value['text'] ?? null) || mb_strlen($value['text']) > $this->limit) {
            $fail('The family text must be bounded text.');
        }
    }
}

final class LifecycleFamilyRecord extends Model
{
    protected $connection = 'fff_family_lifecycle_probe';

    protected $table = 'family_records';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['family_values' => 'array', 'revision' => 'integer'];
    }
}

final class LifecycleFamilyAdapter implements FamilySchemaAdapter, FamilyValueMigration
{
    public static string $failure = '';

    public static int $constructions = 0;

    public static int $valueMigrations = 0;

    public static int $configurationMigrations = 0;

    public static bool $crossFieldRules = false;

    public function __construct()
    {
        self::$constructions++;
    }

    public function authorize(FamilySchemaDefinition $definition, FamilyRenderContext $context): bool
    {
        return $context->tenantId === 'tenant-one' && $context->userKey === 'permitted';
    }

    public function configure(Field|Column|Entry $surface, FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): void {}

    public function rules(FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): array
    {
        if (self::$failure === 'empty-rules') {
            return [];
        }
        if (self::$failure === 'native-rule') {
            return ['string', Rule::in(['allowed'])];
        }
        if (self::$failure === 'exclude-rule') {
            return ['exclude'];
        }
        $rules = $definition->valueVersion === 1 ? ['string', 'max:'.$configuration['maxLength']]
            : ['array:text', new LifecycleFamilyValueRule($configuration['limit'])];

        if (self::$crossFieldRules && $definition->slug === 'other') {
            $rules[] = 'same:values.contact';
        }

        return array_merge($definition->isRequired ? [] : ['nullable'], $rules);
    }

    public function migrateConfiguration(array $configuration, int $fromVersion, int $toVersion): array
    {
        self::$configurationMigrations++;
        if (self::$failure === 'config-throw') {
            throw new RuntimeException('Controlled configuration migration failure.');
        }
        if (self::$failure === 'config-unknown') {
            return ['undeclared' => true];
        }

        return match ([$fromVersion, $toVersion]) {
            [1, 2] => ['limit' => $configuration['maxLength'] ?? 20],
            [2, 1] => ['maxLength' => $configuration['limit'] ?? 20],
            default => throw new InvalidArgumentException('Unsupported configuration migration.'),
        };
    }

    public function supportsValueMigration(int $fromVersion, int $toVersion): bool
    {
        return self::$failure !== 'unsupported' && in_array([$fromVersion, $toVersion], [[1, 2], [2, 1]], true);
    }

    public function migrateStoredValue(mixed $value, FamilySchemaDefinition $from, FamilySchemaDefinition $to, FamilyRenderContext $context): mixed
    {
        self::$valueMigrations++;
        if (self::$failure === 'value-throw') {
            throw new RuntimeException('Controlled value migration failure.');
        }
        if (self::$failure === 'value-object') {
            return new stdClass;
        }
        if (self::$failure === 'value-invalid') {
            return ['text' => str_repeat('x', 100)];
        }
        if ($value === null) {
            return null;
        }

        return $to->valueVersion === 2 ? ['text' => $value] : $value['text'];
    }
}

function lifecycleRegistry(ComponentPackage $package, int $configurationVersion, int $valueVersion): ComponentRegistry
{
    $name = $configurationVersion === 1 ? 'maxLength' : 'limit';
    $schema = new FamilySchemaContract(LifecycleFamilyAdapter::class, new Options([
        $name => new Option(Type::Integer, default: 20, min: 1, max: 100),
    ]));
    $family = new ComponentFamily($package, 'author-contact', ['field' => 'author-field', 'column' => 'author-column', 'entry' => 'author-entry'],
        configurationVersion: $configurationVersion, valueVersion: $valueVersion, schema: $schema);
    $registry = ComponentRegistry::builtIn();
    $registrar = new ComponentRegistrar($registry);
    $registrar->collectComponentFamily($family, [
        new ComponentDefinition('author-style', ComponentKind::Primitive, styles: ['author-style']),
        new ComponentDefinition('author-field', ComponentKind::Field, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Field, FlexTextInput::class)),
        new ComponentDefinition('author-column', ComponentKind::Column, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Column, TextColumn::class)),
        new ComponentDefinition('author-entry', ComponentKind::Entry, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Entry, TextEntry::class)),
    ]);
    $registrar->completeCollection();
    $registry->freeze();

    return $registry;
}

function lifecycleReject(Closure $operation, string $expected): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        if ($error instanceof $expected) {
            return;
        }
        throw $error;
    }
    throw new RuntimeException('Family lifecycle operation unexpectedly accepted: '.$expected.' at line '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line']);
}
$proofs = [];
$options = new Options([
    'limit' => new Option(Type::Integer, default: 5, min: 1, max: 20),
    'ratio' => new Option(Type::Number, default: 1.0),
    'enabled' => new Option(Type::Boolean, default: true),
    'label' => new Option(Type::String, nullable: true, max: 100),
    'languages' => new Option(Type::List, default: ['en'], items: Type::String, max: 5),
    'guard' => new Option(Type::Boolean, default: true, locked: true),
    'payload' => new Option(Type::List, default: [], items: Type::String, max: 1024),
]);
$resolved = $options->resolve([['limit' => 6], ['limit' => 8], ['limit' => 9], ['limit' => 10, 'enabled' => false, 'label' => null, 'languages' => []]]);
schemaProof($resolved['limit'] === 10 && $resolved['enabled'] === false && $resolved['label'] === null && $resolved['languages'] === [], 'Layer/false/null/list replacement broken.');
foreach ([['guard' => false], ['limit' => '9'], ['limit' => 21], ['languages' => [1]], ['unknown' => true], ['limit' => null]] as $invalid) {
    schemaReject(fn () => $options->resolve([$invalid]));
}
schemaReject(fn () => Options::assertData(new stdClass));
schemaReject(fn () => Options::assertData(str_repeat('x', 65537)));
schemaReject(fn () => Options::assertData(INF));
$nested = null;
for ($i = 0; $i < 18; $i++) {
    $nested = [$nested];
}
schemaReject(fn () => Options::assertData($nested));
schemaReject(fn () => (new Options(['must' => new Option(Type::String, required: true)]))->resolve());
schemaReject(fn () => new Option(Type::Integer, choices: ['one']));
schemaReject(fn () => new Option(Type::List, choices: ['one']));
schemaReject(fn () => new Option(Type::String, choices: [null]));
schemaReject(fn () => new Option(Type::Boolean, min: 1));
$proofs[] = 'typed bounded layers, explicit null/false/empty list, unknown/invalid data and locked option rejection';
$lockedMap = new Options(['policy' => new Option(Type::Map, default: ['second' => false, 'first' => true], locked: true, items: Type::Boolean)]);
schemaProof($lockedMap->resolve([['policy' => ['first' => true, 'second' => false]]])['policy']['first'] === true, 'Equivalent map order rejected a locked policy.');
schemaReject(fn () => $lockedMap->resolve([['policy' => ['first' => false, 'second' => false]]]));
schemaReject(fn () => Options::assertData(["\xff" => true]));
$proofs[] = 'canonical locked map policies accept equivalent ordering and reject changed values or invalid UTF-8 keys';

// Quotes, escaped control characters, separators and scalar bytes count toward JSON size.
foreach ([str_repeat("\0", 12000), str_repeat('"', 33000), array_fill(0, 4095, str_repeat('x', 14))] as $oversized) {
    schemaReject(fn () => Options::assertData($oversized));
}
Options::assertData(str_repeat('x', Options::MAX_BYTES - 2));
schemaReject(fn () => Options::assertData(str_repeat('x', Options::MAX_BYTES - 1)));
Options::assertData(array_fill(0, Options::MAX_NODES - 1, null));
schemaReject(fn () => Options::assertData(array_fill(0, Options::MAX_NODES, null)));
$depthBoundary = null;
for ($i = 0; $i < Options::MAX_DEPTH; $i++) {
    $depthBoundary = [$depthBoundary];
}
Options::assertData($depthBoundary);
schemaReject(fn () => Options::assertData([$depthBoundary]));
$proofs[] = 'shared JSON budgets include escaping and punctuation; exact byte, node and depth limits admit and reject correctly';

$sourceText = 'original';
$sourceChoice = 'original';
$option = new Option(Type::Map, default: ['nested' => ['value' => &$sourceText]], locked: true);
$choiceOption = new Option(Type::String, default: 'original', choices: [&$sourceChoice]);
$optionCell = $option;
$frozen = new Options(['policy' => &$optionCell]);
$sourceText = 'changed';
$sourceChoice = 'changed';
$optionCell = new Option(Type::Map, default: ['injected' => true]);
schemaProof($frozen->options['policy'] === $option && $option->default === ['nested' => ['value' => 'original']]
    && $choiceOption->choices === ['original'], 'Caller references changed frozen option declarations.');
schemaReject(fn () => $frozen->resolve([['policy' => ['nested' => ['value' => 'changed']]]]));
$layerText = 'before';
$layer = ['policy' => ['nested' => ['value' => &$layerText]]];
$open = new Options(['policy' => new Option(Type::Map)]);
$result = $open->resolve([$layer]);
$layerText = 'after';
schemaProof($result['policy']['nested']['value'] === 'before', 'Resolved configuration retained layer references.');
$result['policy']['nested']['value'] = 'edited';
schemaProof($layerText === 'after', 'Resolved configuration wrote back into the caller layer.');
$proofs[] = 'frozen defaults, choices, option maps and resolved layers detach PHP aliases without weakening locked policies';

$configLimit = 6;
$config = ['limit' => &$configLimit];
$immutableDefinition = new FamilySchemaDefinition('author-contact', 'immutable', 'Immutable', $config, 2);
$immutableContract = new FamilySchemaContract(SchemaFamilyAdapter::class, $options, $config);
$immutableContext = new FamilyRenderContext('immutable-owner', applicationDefaults: $config, panelDefaults: $config);
$bindingName = 'limit';
$childDefaults = ['limit' => &$configLimit];
$immutableChild = new ChildComponentContract(ComponentKind::Field, FlexTextInput::class,
    new Options(['limit' => new Option(Type::Integer, default: 6)]), bindings: ['maxLength' => [&$bindingName]], packageDefaults: $childDefaults);
$mapTarget = 'limit';
$capability = 'editing';
$immutableSlot = new ChildSlot('text', 'flex-text-input', ComponentKind::Field,
    configurationMap: ['limit' => &$mapTarget], capabilities: [&$capability]);
$configLimit = 99;
$bindingName = 'injected';
$mapTarget = 'injected';
$capability = 'injected';
schemaProof($immutableDefinition->config === ['limit' => 6] && $immutableContract->packageDefaults === ['limit' => 6]
    && $immutableContext->applicationDefaults === ['limit' => 6] && $immutableContext->panelDefaults === ['limit' => 6]
    && $immutableChild->packageDefaults === ['limit' => 6] && $immutableChild->bindings === ['maxLength' => ['limit']]
    && $immutableSlot->configurationMap === ['limit' => 'limit'] && $immutableSlot->capabilities === ['editing'],
    'Configuration references escaped the family, context or child admission boundary.');
$contextText = 'before';
$contextCapability = 'editing';
$contextSlot = false;
$immutableChildContext = new ChildRenderContext($immutableContext, 'flex-text-input', 'context-one', 'text',
    ['nested' => ['value' => &$contextText]], [&$contextCapability], stateSlots: ['text' => &$contextSlot]);
$contextText = 'after';
$contextCapability = 'injected';
$contextSlot = true;
schemaProof($immutableChildContext->configuration['nested']['value'] === 'before'
    && $immutableChildContext->capabilities === ['editing'] && $immutableChildContext->stateSlots === ['text' => false],
    'Child render context retained mutable caller references.');
schemaReject(fn () => new ChildRenderContext($immutableContext, 'flex-text-input', 'context-one', 'text', [], [], stateSlots: ['text' => 'false']));
$proofs[] = 'family definitions, package defaults, request layers, child bindings, mappings and capabilities retain independent admitted data';

$directory = sys_get_temp_dir().'/fff-schema-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
$css = '.author-family { color: inherit; }';
file_put_contents($directory.'/shared.css', $css);
try {
    $manifest = new AssetManifest('example/author-schema', ['shared' => new AssetArtifact('shared.css', ArtifactKind::Stylesheet, hash('sha256', $css), strlen($css))], styles: ['author-style' => 'shared']);
    $package = new ComponentPackage(new AssetPackage($manifest, $directory), 'author', '1.0.0');
    $contract = new FamilySchemaContract(SchemaFamilyAdapter::class, $options, ['limit' => 6]);
    $family = new ComponentFamily($package, 'author-contact', ['field' => 'author-field', 'column' => 'author-column', 'entry' => 'author-entry'], configurationVersion: 2, schema: $contract);
    $childOptions = new Options(['locale' => new Option(Type::String, default: 'en', choices: ['en', 'pl']), 'maxLength' => new Option(Type::Integer, default: 3, min: 1, max: 20)]);
    $slot = new ChildSlot('text', 'flex-text-input', ComponentKind::Field, FlexTextInput::class, SchemaTextFactory::class, $childOptions, ['limit' => 'maxLength']);
    $definitions = [
        new ComponentDefinition('author-style', ComponentKind::Primitive, styles: ['author-style']),
        new ComponentDefinition('author-field', ComponentKind::Field, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Field, CompositeField::class), children: ['text' => $slot]),
        new ComponentDefinition('author-column', ComponentKind::Column, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Column, TextColumn::class)),
        new ComponentDefinition('author-entry', ComponentKind::Entry, stylesFrom: ['author-style'], surface: new ComponentSurface($family, ComponentKind::Entry, TextEntry::class)),
    ];
    $registry = ComponentRegistry::builtIn();
    $registrar = new ComponentRegistrar($registry);
    $registrar->collectComponentFamily($family, $definitions);
    schemaProof(! $registry->has('author-field'), 'Provider collection leaked an incomplete family.');
    $registrar->completeCollection();
    $registry->freeze();
    app()->instance(ComponentRegistry::class, $registry);
    schemaProof($registry->runtimeComponents('author-field') === ['author-field'], 'Child slot became eager runtime composition.');
    schemaProof($registry->stylesFor('author-column') === ['author-style'] && $registry->runtimeComponents('author-entry') === ['author-entry'], 'Read-only surfaces loaded editor assets.');
    $proofs[] = 'semantic family commits atomically; child render edges validate without eager child assets; one read-only CSS owner';

    $renderer = new FamilySchemaRenderer($registry);
    $context = new FamilyRenderContext('form:one', 'values', 'pl', applicationDefaults: ['limit' => 8], panelDefaults: ['limit' => 9], userKey: 'permitted', tenantId: 'tenant-one');
    $definition = new FamilySchemaDefinition('author-contact', 'contact', 'Contact', ['limit' => 10], 2, isRequired: true);
    $field = (new FlexFieldFormBuilder)->makeComponent($definition, familyContext: $context);
    $column = (new FlexFieldTableBuilder)->makeColumn($definition, 'values', $context);
    $entry = (new FlexFieldInfolistBuilder)->makeEntry($definition, 'values', $context);
    schemaProof($field instanceof CompositeField && $column instanceof TextColumn && $entry instanceof TextEntry, 'Dynamic builder family dispatch failed.');
    schemaProof(array_slice(array_column(SchemaFamilyAdapter::$calls, 'limit'), -3) === [10, 10, 10], 'Family surfaces used different layer precedence.');
    schemaReject(fn () => $renderer->make(ComponentKind::Field, $definition, new FamilyRenderContext('denied')));
    schemaReject(fn () => $renderer->make(ComponentKind::Field, $definition, new FamilyRenderContext('cross-tenant', userKey: 'permitted', tenantId: 'other')));
    schemaReject(fn () => (new FlexFieldFormBuilder)->build([$definition, $definition], familyContext: $context));
    schemaProof($registry->family('author-contact') === $family && $contract->packageDefaults === ['limit' => 6], 'Request layers leaked into the frozen contract.');
    $proofs[] = 'form/table/infolist use installed family renderers and exact layers; explicit user/tenant authorization rejects denied contexts; duplicate form keys reject';

    $host = new V590SchemaHost;
    $host->setId('family-schema-host');
    $schema = Schema::make($host)->statePath('data')->components([$field]);
    $schema->fill();
    $children = $field->getChildSchema()->getFlatFields();
    schemaProof(count($children) === 2 && array_values($children)[0]->getLabel() === 'en' && array_values($children)[1]->getLabel() === 'pl', 'Children share configuration or locale.');
    $ids = array_map(fn ($child) => $child->getId(), $children);
    schemaProof(count(array_unique($ids)) === 2 && array_map(fn ($child) => $child->getId(), $children) === $ids, 'Child IDs unstable or collided.');
    $paths = array_map(fn ($child) => $child->getStatePath(), $children);
    $host->data = ['values' => ['contact' => ['text' => ['k_'.hash('sha256', 'first') => 'valid', 'k_'.hash('sha256', 'second') => 'valid']]]];
    $state = $schema->getState();
    schemaProof(count($state['values']['contact']['text']) === 2, 'Independent children did not dehydrate through the real native schema.');
    $host->data['values']['contact']['text']['k_'.hash('sha256', 'second')] = str_repeat('x', 11);
    try {
        $schema->getState();
        throw new RuntimeException('Forged oversized child value accepted.');
    } catch (ValidationException $error) {
        schemaProof(array_key_exists(array_values($paths)[1], $error->errors()), 'Child error mapped to the wrong state path.');
    }
    $host->data['values']['contact']['text']['k_'.hash('sha256', 'second')] = 'valid';
    $host->data['values']['contact']['injected'] = 'forged';
    try {
        $schema->getState();
        throw new RuntimeException('Unknown container child key accepted.');
    } catch (ValidationException $error) {
        schemaProof(array_key_exists('data.values.contact', $error->errors()), 'Unknown child key escaped the container rule.');
    }
    unset($host->data['values']['contact']['injected']);
    $html = $schema->toHtml();
    schemaProof(substr_count($html, 'data-fff-asset-consumer="flex-text-input"') === 2 && str_contains($html, 'data-fff-asset-consumer="author-field"'), 'Actual child/parent owner boundaries absent.');
    $proofs[] = 'real Filament schema renders existing FLEX input twice with distinct locale/IDs/state/owners and validates/dehydrates independent child data';
    $record = new class extends Model {};
    $record->setRawAttributes(['id' => 1, 'values' => ['contact' => 'record-value']]);
    $tableHost = new class extends V590SchemaHost implements HasTable
    {
        use InteractsWithTable;

        public function mountFixture(Column $column): void
        {
            $this->table = Table::make($this)->columns([$column]);
        }
    };
    $tableHost->mountFixture($column);
    $column->record($record);
    $entrySchema = Schema::make($host)->record($record)->components([$entry]);
    $entrySchema->getComponents();
    schemaProof($column->getState() === 'record-value' && $entry->getState() === 'record-value', 'Read-only family surfaces did not read the configured record path.');
    $proofs[] = 'actual table column and infolist entry resolve the same request-local record path without database writes';

    $parentLimit = 4;
    $immutableSession = new ChildRenderer($registry, 'author-field', new FamilyRenderContext('immutable-parent'), ['limit' => &$parentLimit]);
    $parentLimit = 19;
    schemaProof($immutableSession->render('text', 'immutable-child')->getMaxLength() === 4, 'Caller changed parent configuration after session admission.');
    $proofs[] = 'child session retains admitted parent configuration independently of subsequent PHP reference mutations';
    $session = new ChildRenderer($registry, 'author-field', new FamilyRenderContext('stable-parent'), ['limit' => 4]);
    $first = $session->render('text', 'stable-key', ['locale' => 'pl']);
    schemaReject(fn () => $session->render('text', 'stable-key'));
    $reordered = (new ChildRenderer($registry, 'author-field', new FamilyRenderContext('stable-parent'), ['limit' => 4]))->render('text', 'stable-key', ['locale' => 'en']);
    Schema::make($host)->key('stable')->statePath('data')->components([$first])->getComponents();
    Schema::make($host)->key('stable')->statePath('data')->components([$reordered])->getComponents();
    schemaProof($first->getId() === $reordered->getId(), 'Reorder/locale change changed stable child identity.');
    $disabled = (new ChildRenderer($registry, 'author-field', new FamilyRenderContext('disabled-parent'), ['limit' => 4], readOnly: true))->render('text', 'one');
    schemaProof($disabled->isDisabled() && ! $disabled->isDehydrated(), 'Child relaxed parent readonly/write restriction.');
    $maximumKey = $session->render('text', str_repeat('k', 512));
    $maximumAgain = (new ChildRenderer($registry, 'author-field', new FamilyRenderContext('stable-parent'), ['limit' => 4]))->render('text', str_repeat('k', 512));
    Schema::make($host)->key('stable')->statePath('data')->components([$maximumKey])->getComponents();
    Schema::make($host)->key('stable')->statePath('data')->components([$maximumAgain])->getComponents();
    schemaProof($maximumKey->getId() === $maximumAgain->getId(), 'Maximum supported child key changed identity.');
    $proofs[] = 'stable explicit child keys survive reconstruction/reorder and locale changes; collision and parent readonly enforcement';

    LifecycleFamilyAdapter::$failure = 'native-rule';
    $nativeAdapter = new LifecycleFamilyAdapter;
    $nativeResolved = new ResolvedFamilySchema(
        $registry->family('author-contact'), $definition, $nativeAdapter, new FamilyRenderContext('native-rule-owner'), []);
    $nativeRules = $nativeResolved->rules(true);
    schemaProof(app('validator')->make(['value' => 'allowed'], ['value' => $nativeRules])->passes(), 'Native Rule::in was rejected.');
    schemaProof(app('validator')->make(['value' => 'injected'], ['value' => $nativeRules])->fails(), 'Native Rule::in accepted a forged value.');
    LifecycleFamilyAdapter::$failure = '';
    $proofs[] = 'family rendering/submission admits native Stringable Rule::in and rejects unauthorized values through the actual Laravel validator';

    $codec = new FamilySchemaCodec($renderer);
    $encoded = $codec->export([$definition]);
    schemaProof($codec->export($codec->import($encoded)) === $encoded, 'Family configuration roundtrip changed data.');
    $floatDefinition = new FamilySchemaDefinition('author-contact', 'float_config', 'Float config', ['ratio' => 1.0], 2);
    $floatRoundtrip = $codec->import($codec->export([$floatDefinition]));
    schemaProof($floatRoundtrip[0]->config['ratio'] === 1.0, 'Exchange changed floating-point configuration into an integer.');
    $proofs[] = 'family configuration exchange preserves fractional-zero numbers through actual normalization and import';
    $unicodeDefinitions = [];
    for ($index = 0; $index < 32; $index++) {
        $unicodeDefinitions[] = new FamilySchemaDefinition('author-contact', 'unicode_'.$index, 'Unicode',
            ['payload' => array_fill(0, 80, str_repeat('ż', 128))], 2);
    }
    $unicodeExchange = $codec->export($unicodeDefinitions);
    schemaProof(strlen($unicodeExchange) < 1048576 && count($codec->import($unicodeExchange)) === 32,
        'Compact Unicode payload was rejected by a different intermediate JSON encoding.');
    $proofs[] = 'family exchange measures the same Unicode representation as its export, with the final pretty envelope independently bounded';
    $legacy = new FamilySchemaDefinition('author-contact', 'old', 'Old', ['oldLimit' => 7]);
    $before = SchemaFamilyAdapter::$migrations;
    $imported = $codec->import(json_encode(['format' => 1, 'families' => [$legacy->export()]], JSON_THROW_ON_ERROR));
    schemaProof($imported[0]->configurationVersion === 2 && $imported[0]->config === ['limit' => 7] && SchemaFamilyAdapter::$migrations === $before + 1, 'Migration omitted or ran twice.');
    schemaReject(fn () => $codec->export([$definition, $definition]));
    schemaReject(fn () => $renderer->normalize(new FamilySchemaDefinition('missing-family', 'missing', 'Missing')));
    schemaReject(fn () => $renderer->normalize(new FamilySchemaDefinition('author-contact', 'future', 'Future', configurationVersion: 3)));
    schemaReject(fn () => $renderer->normalize(new FamilySchemaDefinition('author-contact', 'future', 'Future', configurationVersion: 2, valueVersion: 2)));
    schemaReject(fn () => FamilySchemaDefinition::fromArray(['family' => 'author-contact', 'slug' => 'unsafe.path', 'label' => 'Unsafe']));
    schemaReject(fn () => FamilySchemaDefinition::fromArray(['family' => 'author-contact', 'slug' => 'unsafe', 'label' => 'Unsafe', 'class' => stdClass::class]));
    $proofs[] = 'portable exact roundtrip, single explicit configuration migration; unknown family/keys, future config/value version and path injection reject';
    $largeDefinitions = [];
    for ($i = 0; $i < 100; $i++) {
        $largeDefinitions[] = new FamilySchemaDefinition('author-contact', 'large_'.$i, 'Large', ['payload' => array_fill(0, 900, 'x')], 2);
    }
    schemaReject(fn () => $codec->export($largeDefinitions));
    schemaReject(fn () => $codec->import(str_repeat('x', 1048577)));
    $proofs[] = 'exchange byte budget includes pretty-printed envelope; oversized export/import both reject';

    $invalid = ComponentRegistry::builtIn();
    $revision = $invalid->revision();
    schemaReject(fn () => $invalid->registerBatch([new ComponentDefinition('cycle-parent', ComponentKind::Field, children: [
        'self' => new ChildSlot('self', 'cycle-parent', ComponentKind::Field, FlexTextInput::class, SchemaTextFactory::class),
    ])]));
    schemaReject(fn () => $invalid->registerBatch([new ComponentDefinition('bad-child-parent', ComponentKind::Field, children: [
        'text' => new ChildSlot('text', 'flex-text-input', ComponentKind::Field, FlexTextInput::class, SchemaTextFactory::class, capabilities: ['not-defined']),
    ])]));
    schemaProof($invalid->revision() === $revision && ! $invalid->has('cycle-parent'), 'Invalid slot graph partially committed.');
    $proofs[] = 'recursive/dormant child graph and undeclared child capability reject atomically before registration';
    $invalid->registerBatch([new ComponentDefinition('retry-parent', ComponentKind::Field, children: [
        'text' => new ChildSlot('text', 'flex-text-input', ComponentKind::Field, FlexTextInput::class, SchemaWrongFactory::class),
    ]), new ComponentDefinition('shared-parent', ComponentKind::Field, children: [
        'text' => new ChildSlot('text', 'flex-text-input', ComponentKind::Field, FlexTextInput::class, SchemaWrongFactory::class, shareState: true),
    ])]);
    $invalid->freeze();
    $retry = new ChildRenderer($invalid, 'retry-parent', new FamilyRenderContext('retry'));
    schemaReject(fn () => $retry->render('text', 'same-key'));
    SchemaWrongFactory::$wrong = false;
    schemaProof($retry->render('text', 'same-key') instanceof FlexTextInput, 'Failed factory retained its key claim or could not retry.');
    $proofs[] = 'wrong child renderer output rejects and rolls back its claim; the same stable key can retry successfully';
    $shared = (new ChildRenderer($invalid, 'shared-parent', new FamilyRenderContext('shared')))->render('text', 'shared-key');
    $sharedHost = new V590SchemaHost;
    $sharedHost->setId('shared-schema');
    $sharedSchema = Schema::make($sharedHost)->statePath('data')->components([$shared]);
    $sharedSchema->getComponents();
    schemaProof($shared->getStatePath() === 'data', 'Explicit shared state did not inherit the native container path.');
    $readonlyDefinition = new FamilySchemaDefinition('author-contact', 'readonly', 'Readonly', configurationVersion: 2, isReadOnly: true);
    $readonlyHost = new V590SchemaHost;
    $readonlyHost->setId('readonly-schema');
    $readonlyHost->data = ['values' => ['readonly' => ['text' => ['k_'.hash('sha256', 'first') => 'forged']]]];
    $readonlyField = $renderer->make(ComponentKind::Field, $readonlyDefinition, $context);
    $readonlySchema = Schema::make($readonlyHost)->statePath('data')->components([$readonlyField]);
    schemaProof(! array_key_exists('readonly', $readonlySchema->getState()['values'] ?? []), 'Readonly family value reached native dehydration.');
    $proofs[] = 'explicit shared child path uses its real native container; readonly family ignores injected data during dehydration';

    $lifecycleContext = new FamilyRenderContext('lifecycle:one', 'values', 'pl', userKey: 'permitted', tenantId: 'tenant-one');
    $v1 = lifecycleRegistry($package, 1, 1);
    $v2 = lifecycleRegistry(new ComponentPackage($package->assets, 'author', '2.0.0'), 2, 2);
    $renderer1 = new FamilySchemaRenderer($v1);
    $renderer2 = new FamilySchemaRenderer($v2);
    $storedDefinition = new FamilySchemaDefinition('author-contact', 'contact', 'Contact', ['maxLength' => 20], isRequired: true);
    $stored = new FamilyValueSnapshot($storedDefinition, 'Original text');
    $storedBytes = FamilyValueSnapshotCodec::encode($stored);
    $migration2 = new FamilySchemaMigration($v2, $renderer2);
    $policy = static fn (FamilySchemaDefinition $definition, FamilyRenderContext $lifecycleContext): bool => $lifecycleContext->tenantId === 'tenant-one';
    $result = $migration2->prepare($stored, $lifecycleContext, $policy);
    schemaProof($result->before === $stored && $result->changed() && $result->after->definition->configurationVersion === 2
        && $result->after->definition->valueVersion === 2 && $result->after->definition->config === ['limit' => 20]
        && $result->after->value === ['text' => 'Original text'] && FamilyValueSnapshotCodec::encode($stored) === $storedBytes, 'Versioned migration changed source or lost value/configuration.');
    lifecycleReject(fn () => $renderer2->normalize($storedDefinition), InvalidArgumentException::class);
    $rolledBack = (new FamilySchemaMigration($v1, $renderer1))->prepare($result->after, $lifecycleContext, $policy);
    schemaProof(FamilyValueSnapshotCodec::encode($rolledBack->after) === $storedBytes, 'Explicit reverse migration failed exact v1 restoration.');
    $count = LifecycleFamilyAdapter::$valueMigrations;
    $unchanged = $migration2->prepare($result->after, $lifecycleContext, $policy);
    schemaProof(! $unchanged->changed() && LifecycleFamilyAdapter::$valueMigrations === $count, 'Same-version retry migrated the value again.');
    $proofs[] = 'explicit config/value v1→v2 and declared v2→v1 rollback preserve exact source; normal rendering refuses old value version; same-version retry performs no migration';

    $otherDefinition = FamilySchemaDefinition::fromArray(array_replace($storedDefinition->export(), ['slug' => 'other']));
    $other = new FamilyValueSnapshot($otherDefinition, 'Original text');
    LifecycleFamilyAdapter::$crossFieldRules = true;
    $batch = $migration2->prepareMany([$stored, $other], $lifecycleContext, $policy);
    schemaProof(count($batch) === 2 && $batch[1]->after->value === $batch[0]->after->value, 'Batch migration did not validate cross-field rules on real paths.');
    try {
        $migration2->prepareMany([$stored, new FamilyValueSnapshot($otherDefinition, 'different')], $lifecycleContext, $policy);
        throw new RuntimeException('Invalid dependent migration batch accepted.');
    } catch (ValidationException $error) {
        schemaProof(array_key_exists('values.other', $error->errors()), 'Migration errors did not use the actual field state path.');
    }
    LifecycleFamilyAdapter::$crossFieldRules = false;
    $count = LifecycleFamilyAdapter::$valueMigrations;
    lifecycleReject(fn () => $migration2->prepareMany([$stored, $stored], $lifecycleContext, $policy), InvalidArgumentException::class);
    lifecycleReject(fn () => $migration2->prepareMany([$stored, $other], $lifecycleContext, static fn (FamilySchemaDefinition $definition): bool => $definition->slug !== 'other'), AuthorizationException::class);
    schemaProof(LifecycleFamilyAdapter::$valueMigrations === $count && FamilyValueSnapshotCodec::encode($stored) === $storedBytes, 'Duplicate/denied batch ran value conversion or changed its source.');
    lifecycleReject(fn () => $migration2->prepareMany(array_fill(0, 257, $stored), $lifecycleContext, $policy), InvalidArgumentException::class);
    schemaProof($migration2->prepareMany([], $lifecycleContext, $policy) === [], 'Empty batch is not a no-op.');
    $proofs[] = 'bounded all-or-nothing migration batches validate dependent fields on actual paths; duplicate/denied metadata rejects before value conversions; originals survive validation failure';

    $submission = new FamilySchemaSubmission($renderer2, new FieldValueSubmission);
    $targetDefinition = $result->after->definition;
    $valid = ['values' => ['contact' => ['text' => 'Accepted']]];
    schemaProof($submission->prepare($valid, [$targetDefinition], $lifecycleContext, $policy) === $valid, 'Public family save did not use configured target rules.');
    lifecycleReject(fn () => $submission->prepare(['values' => ['contact' => ['text' => str_repeat('x', 21)]]], [$targetDefinition], $lifecycleContext, $policy), ValidationException::class);
    lifecycleReject(fn () => $submission->prepare(['values' => ['contact' => ['text' => 'ok', 'admin' => true]]], [$targetDefinition], $lifecycleContext, $policy), ValidationException::class);
    lifecycleReject(fn () => $submission->prepare(['values' => ['unknown' => 'forged']], [$targetDefinition], $lifecycleContext, $policy), InvalidArgumentException::class);
    lifecycleReject(fn () => $submission->prepare([], [$targetDefinition], $lifecycleContext, $policy), ValidationException::class);
    lifecycleReject(fn () => $submission->prepare(['values' => ['contact' => null]], [$targetDefinition], $lifecycleContext, $policy), ValidationException::class);
    lifecycleReject(fn () => $submission->prepare($valid, [$targetDefinition, $targetDefinition], $lifecycleContext, $policy), InvalidArgumentException::class);
    lifecycleReject(fn () => $submission->prepare($valid, [$storedDefinition], $lifecycleContext, $policy), InvalidArgumentException::class);
    $proofs[] = 'public save without UI reuses family config/rules/required; rejects oversized text, unknown nested and root fields, duplicates, old versions and missing/null required values';

    foreach (['isDisabled', 'isReadOnly', 'isActive'] as $flag) {
        $restricted = FamilySchemaDefinition::fromArray(array_replace($targetDefinition->export(), [$flag => $flag !== 'isActive']));
        lifecycleReject(fn () => $submission->prepare($valid, [$restricted], $lifecycleContext, $policy), AuthorizationException::class);
        schemaProof($submission->prepare([], [$restricted], $lifecycleContext, $policy) === [], 'Omitted restricted field demanded a value.');
    }
    $hidden = FamilySchemaDefinition::fromArray(array_replace($targetDefinition->export(), ['isVisible' => false]));
    schemaProof($submission->prepare($valid, [$hidden], $lifecycleContext, $policy) === $valid, 'Visibility incorrectly granted or removed application write permission.');
    lifecycleReject(fn () => $submission->prepare($valid, [$targetDefinition], $lifecycleContext, static fn (): bool => false), AuthorizationException::class);
    schemaProof($submission->prepare([], [$targetDefinition], $lifecycleContext, static fn (): bool => false) === [], 'Denied omitted field did not preserve existing data.');
    lifecycleReject(fn () => $submission->prepare($valid, [$targetDefinition], new FamilyRenderContext('other', 'values', userKey: 'permitted', tenantId: 'other'), $policy), AuthorizationException::class);
    lifecycleReject(fn () => $submission->prepare($valid, [$targetDefinition], $lifecycleContext, static fn () => 1), InvalidArgumentException::class);
    $count = LifecycleFamilyAdapter::$configurationMigrations;
    lifecycleReject(fn () => $migration2->prepare($stored, $lifecycleContext, static fn (): bool => false), AuthorizationException::class);
    lifecycleReject(fn () => $migration2->prepare($stored, $lifecycleContext, static fn () => 1), InvalidArgumentException::class);
    schemaProof(LifecycleFamilyAdapter::$configurationMigrations === $count, 'Denied source ran a migration callback.');
    lifecycleReject(fn () => $migration2->prepare($stored, $lifecycleContext, static fn (FamilySchemaDefinition $definition): bool => $definition->configurationVersion === 1), AuthorizationException::class);
    $proofs[] = 'separate application edit/migration and adapter tenant authorization; inactive/readonly/disabled never writable, visibility never an authorization grant, denied omission preserved and truthy policies rejected';

    foreach (['config-throw' => RuntimeException::class, 'config-unknown' => InvalidArgumentException::class,
        'unsupported' => InvalidArgumentException::class, 'value-throw' => RuntimeException::class,
        'value-object' => UnexpectedValueException::class, 'value-invalid' => ValidationException::class,
        'empty-rules' => InvalidArgumentException::class, 'exclude-rule' => InvalidArgumentException::class] as $failure => $exception) {
        LifecycleFamilyAdapter::$failure = $failure;
        lifecycleReject(fn () => $migration2->prepare($stored, $lifecycleContext, $policy), $exception);
        schemaProof(FamilyValueSnapshotCodec::encode($stored) === $storedBytes, 'Failed migration changed original data: '.$failure);
    }
    LifecycleFamilyAdapter::$failure = '';
    schemaProof($migration2->prepare($stored, $lifecycleContext, $policy)->after->value === ['text' => 'Original text'], 'Retry after failed migration did not recover.');
    $proofs[] = 'configuration/value throw, undeclared config, absent migration, object/invalid result and missing/discarding rules reject without source changes; retry succeeds';

    foreach ([null, false, 0, 0.0, [], ['text' => 'Żółć 🧭']] as $value) {
        $snapshot = new FamilyValueSnapshot($storedDefinition, $value);
        schemaProof(FamilyValueSnapshotCodec::decode(FamilyValueSnapshotCodec::encode($snapshot))->value === $value, 'Snapshot roundtrip lost a JSON type.');
    }
    $optional = FamilySchemaDefinition::fromArray(array_replace($storedDefinition->export(), ['isRequired' => false]));
    schemaProof($migration2->prepare(new FamilyValueSnapshot($optional, null), $lifecycleContext, $policy)->after->value === null, 'Optional stored null became a default or non-null value.');
    $data = json_decode($storedBytes, true, 64, JSON_THROW_ON_ERROR);
    foreach (['format' => 2, 'class' => stdClass::class] as $key => $value) {
        lifecycleReject(fn () => FamilyValueSnapshotCodec::decode(json_encode(array_replace($data, [$key => $value]), JSON_THROW_ON_ERROR)), InvalidArgumentException::class);
    }
    foreach (FamilySchemaDefinition::DATA_KEYS as $key) {
        $missingMetadata = $data;
        unset($missingMetadata['definition'][$key]);
        lifecycleReject(fn () => FamilyValueSnapshotCodec::decode(json_encode($missingMetadata, JSON_THROW_ON_ERROR)), InvalidArgumentException::class);
    }
    lifecycleReject(fn () => FamilyValueSnapshotCodec::decode(str_repeat(' ', 8388609)), UnexpectedValueException::class);
    lifecycleReject(fn () => new FamilySchemaDefinition('author-contact', 'list', 'List', ['not-a-map']), InvalidArgumentException::class);
    $proofs[] = 'independent snapshot codec preserves null/false/zero/float/list/Unicode and explicit versions; future envelope, unknown callback keys, absent versions and excessive bytes reject';

    $referenceText = 'before';
    $referenceLimit = 20;
    $referenceConfig = ['maxLength' => &$referenceLimit];
    $referenceValue = ['text' => &$referenceText];
    $detached = new FamilyValueSnapshot(new FamilySchemaDefinition('author-contact', 'reference', 'Reference', $referenceConfig), $referenceValue);
    $referenceText = 'after';
    $referenceLimit = 1;
    schemaProof($detached->value === ['text' => 'before'] && $detached->definition->config === ['maxLength' => 20], 'Readonly snapshot retained mutable PHP reference aliases.');
    $proofs[] = 'stored snapshots break nested PHP reference aliases in both configuration and values without changing JSON types';

    $absent = ComponentRegistry::builtIn();
    $absent->freeze();
    $count = LifecycleFamilyAdapter::$constructions;
    $availability = (new FamilySchemaAvailability($absent))->inspect([$storedDefinition]);
    schemaProof(! $availability['ready'] && $availability['issues'][0]['code'] === 'family-unavailable'
        && LifecycleFamilyAdapter::$constructions === $count && FamilyValueSnapshotCodec::encode(FamilyValueSnapshotCodec::decode($storedBytes)) === $storedBytes,
        'Missing package initialized an adapter or changed stored data.');
    lifecycleReject(fn () => (new FamilySchemaSubmission(new FamilySchemaRenderer($absent), new FieldValueSubmission))->prepare(['values' => ['contact' => 'new']], [$storedDefinition], $lifecycleContext, $policy), InvalidArgumentException::class);
    lifecycleReject(fn () => (new FamilySchemaMigration($absent, new FamilySchemaRenderer($absent)))->prepare($stored, $lifecycleContext, $policy), InvalidArgumentException::class);
    schemaProof((new FamilySchemaAvailability($v1))->inspect([$storedDefinition])['ready']
        && ! (new FamilySchemaAvailability($v2))->inspect([$storedDefinition])['ready']
        && (new FamilySchemaAvailability($v2))->inspect([$targetDefinition])['ready'], 'Availability version diagnostics ignored required migration.');
    $invalidConfig = FamilySchemaDefinition::fromArray(array_replace($targetDefinition->export(), ['config' => ['limit' => 'forged']]));
    schemaProof((new FamilySchemaAvailability($v2))->inspect([$invalidConfig])['issues'][0]['code'] === 'configuration-invalid', 'Availability accepted malformed current-version configuration.');
    $safeReport = json_encode($availability, JSON_THROW_ON_ERROR);
    schemaProof(! str_contains($safeReport, 'Original text') && ! str_contains($safeReport, 'maxLength') && ! str_contains($safeReport, LifecycleFamilyAdapter::class), 'Availability report disclosed values/configuration/classes.');
    $proofs[] = 'missing-package read/preflight preserves exact snapshot without adapter construction; save/migration refuse unavailable families, version diagnostics are value/config/class free';

    $app['config']->set('database.connections.fff_family_lifecycle_probe', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
    $familyDb = $app['db']->connection('fff_family_lifecycle_probe');
    $familyDb->getSchemaBuilder()->create('family_records', static function (Blueprint $table): void {
        $table->id();
        $table->string('tenant_id');
        $table->text('family_values');
        $table->text('snapshot')->nullable();
        $table->integer('revision')->default(1);
    });
    try {
        $create = static function (array $values) use ($familyDb, $submission, $targetDefinition, $lifecycleContext, $policy): LifecycleFamilyRecord {
            return $familyDb->transaction(static function () use ($values, $submission, $targetDefinition, $lifecycleContext, $policy): LifecycleFamilyRecord {
                $prepared = $submission->prepare($values, [$targetDefinition], $lifecycleContext, $policy);

                return LifecycleFamilyRecord::create(['tenant_id' => 'tenant-one', 'family_values' => $prepared['values']]);
            });
        };
        $saved = $create($valid);
        schemaProof($saved->fresh()->family_values === $valid['values'], 'Public server-only family create did not persist accepted data.');
        $recordCount = LifecycleFamilyRecord::count();
        lifecycleReject(fn () => $create(['values' => ['contact' => ['text' => str_repeat('x', 21)]]]), ValidationException::class);
        schemaProof(LifecycleFamilyRecord::count() === $recordCount, 'Failed public create inserted a partial record.');
        $saved->family_values = array_replace($saved->family_values, ['unavailable_extension' => ['retained' => true]]);
        $saved->snapshot = $storedBytes;
        $saved->save();
        $edit = static function (int $id, array $values, int $expectedRevision) use ($familyDb, $submission, $targetDefinition, $lifecycleContext, $policy): LifecycleFamilyRecord {
            return $familyDb->transaction(static function () use ($id, $values, $expectedRevision, $submission, $targetDefinition, $lifecycleContext, $policy): LifecycleFamilyRecord {
                $record = LifecycleFamilyRecord::query()->where('tenant_id', $lifecycleContext->tenantId)->whereKey($id)->lockForUpdate()->firstOrFail();
                if ($record->revision !== $expectedRevision) {
                    throw new LogicException('Application record revision conflict.');
                }
                $prepared = $submission->prepare($values, [$targetDefinition], $lifecycleContext, $policy);
                $record->family_values = array_replace($record->family_values, $prepared['values'] ?? []);
                $record->revision++;
                $record->save();

                return $record;
            });
        };
        $saved = $edit($saved->getKey(), ['values' => ['contact' => ['text' => 'Updated']]], 1);
        schemaProof($saved->family_values['contact'] === ['text' => 'Updated'] && $saved->family_values['unavailable_extension'] === ['retained' => true], 'Public edit lost accepted value or omitted extension data.');
        lifecycleReject(fn () => $edit($saved->getKey(), $valid, 1), LogicException::class);
        $persisted = $saved->getRawOriginal();
        lifecycleReject(fn () => $edit($saved->getKey(), ['values' => ['contact' => ['text' => 'ok', 'admin' => true]]], 2), ValidationException::class);
        schemaProof($saved->fresh()->getRawOriginal() === $persisted, 'Rejected public edit changed values, snapshot or revision.');
        $foreign = LifecycleFamilyRecord::create(['tenant_id' => 'tenant-two', 'family_values' => ['contact' => ['text' => 'Private']]]);
        lifecycleReject(fn () => $edit($foreign->getKey(), $valid, 1), ModelNotFoundException::class);
        $proofs[] = 'real isolated SQLite public create/edit without UI rejects forged data before persistence, preserves absent extension values, and enforces application tenant query/revision without changing host data';

        $familyDb->transaction(static function () use ($saved, $migration2, $lifecycleContext, $policy): void {
            $record = LifecycleFamilyRecord::query()->whereKey($saved->getKey())->lockForUpdate()->firstOrFail();
            $result = $migration2->prepare(FamilyValueSnapshotCodec::decode($record->snapshot), $lifecycleContext, $policy);
            $record->snapshot = FamilyValueSnapshotCodec::encode($result->after);
            $record->save();
        });
        $persisted = $saved->fresh()->getRawOriginal();
        lifecycleReject(static function () use ($familyDb, $saved, $storedBytes, $migration2, $lifecycleContext, $policy): void {
            $familyDb->transaction(static function () use ($saved, $storedBytes, $migration2, $lifecycleContext, $policy): void {
                $record = LifecycleFamilyRecord::query()->whereKey($saved->getKey())->firstOrFail();
                $record->snapshot = $storedBytes;
                $record->save();
                LifecycleFamilyAdapter::$failure = 'value-invalid';
                $migration2->prepare(FamilyValueSnapshotCodec::decode($record->snapshot), $lifecycleContext, $policy);
            });
        }, ValidationException::class);
        LifecycleFamilyAdapter::$failure = '';
        schemaProof($saved->fresh()->getRawOriginal() === $persisted, 'Application transaction did not restore the previous row after migration failure.');
        $proofs[] = 'actual stored snapshot v1→v2 persistence and application transaction rollback restore the exact previous database row after a conversion failure';
    } finally {
        $app['db']->purge('fff_family_lifecycle_probe');
        LifecycleFamilyAdapter::$failure = '';
    }

    if (($output = getenv('FFF_SCHEMA_PROBE_HTML')) !== false && $output !== '') {
        file_put_contents($output, $html);
    }
    echo json_encode(['groups' => count($proofs), 'proofs' => $proofs, 'testsModified' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    unlink($directory.'/shared.css');
    rmdir($directory);
}
