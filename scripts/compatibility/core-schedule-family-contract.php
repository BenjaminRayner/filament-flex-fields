<?php

declare(strict_types=1);

// Isolated Testbench application; no consumer database is touched.
require __DIR__.'/schema-render-contract.php';

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Infolists\Components\ScheduleEntry;
use Bjanczak\FilamentFlexFields\Filament\Tables\Columns\ScheduleColumn;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\PreparedComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleDays;
use Bjanczak\FilamentFlexFields\Support\Schedule\ScheduleValidator;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\SupportServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$app->register(InfolistsServiceProvider::class);

function familyCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function familyReject(Closure $operation): void
{
    try {
        $operation();
    } catch (LogicException) {
        return;
    }
    throw new RuntimeException('Invalid extension accepted.');
}
$registry = app(ComponentRegistry::class);
$registrar = app(ComponentRegistrar::class);
familyCheck($registry->isFrozen(), 'Application boot did not freeze the component registry');
$bootRevision = $registry->revision();
familyReject(fn () => $registrar->register(new ComponentDefinition('probe-after-boot', ComponentKind::Field)));
familyReject(fn () => $registry->register(new ComponentDefinition('probe-direct-after-boot', ComponentKind::Field)));
familyCheck($registry->revision() === $bootRevision, 'Late registration changed the boot snapshot');

// Exercise provider collection in an explicit standalone builder, not the frozen
// application's registry. The public runtime snapshot is never thawed.
$registry = ComponentRegistry::builtIn();
$registrar = new ComponentRegistrar($registry);
app()->instance(ComponentRegistry::class, $registry);
$registrar->collectFamily([
    new ComponentRegistration(new ComponentDefinition('probe-provider-a', ComponentKind::Field, uses: ['probe-provider-b'])),
]);
$registrar->collectFamily([
    new ComponentRegistration(new ComponentDefinition('probe-provider-b', ComponentKind::Display)),
]);
familyCheck(! $registry->has('probe-provider-a'), 'Collection published an incomplete provider graph');
$registrar->completeCollection();
familyCheck($registry->runtimeComponents('probe-provider-a') === ['probe-provider-b', 'probe-provider-a'], 'Provider-order-independent collection failed');
familyReject(fn () => $registrar->collectFamily([new ComponentRegistration(new ComponentDefinition('probe-sealed-collection', ComponentKind::Field))]));

$invalidRegistry = ComponentRegistry::builtIn();
$invalidRegistrar = new ComponentRegistrar($invalidRegistry);
$invalidRevision = $invalidRegistry->revision();
$invalidRegistrar->collectFamily([
    new ComponentRegistration(new ComponentDefinition('probe-pending-invalid', ComponentKind::Field, uses: ['probe-pending-missing'])),
]);
familyReject(fn () => $invalidRegistrar->completeCollection());
familyCheck($invalidRegistry->revision() === $invalidRevision && ! $invalidRegistry->has('probe-pending-invalid'), 'Invalid collection partially committed');
$invalidRegistrar->collectFamily([
    new ComponentRegistration(new ComponentDefinition('probe-pending-missing', ComponentKind::Display)),
]);
$invalidRegistrar->completeCollection();
$invalidSnapshot = $invalidRegistry->freeze();
familyReject(fn () => $invalidRegistry->registerBatch([new ComponentDefinition('probe-frozen-batch', ComponentKind::Field)]));
familyCheck($invalidRegistry->compiledGraph() === $invalidSnapshot, 'Failed mutation replaced a frozen graph');

$atomicRevision = $registry->revision();
familyReject(fn () => $registry->registerBatch([
    new ComponentDefinition('probe-atomic-first', ComponentKind::Field),
    new ComponentDefinition('probe-atomic-invalid', ComponentKind::Field, uses: ['probe-atomic-missing']),
]));
familyCheck($registry->revision() === $atomicRevision && ! $registry->has('probe-atomic-first'), 'Direct batch registration was not atomic');
$prepared = $registry->prepareBatch([new ComponentDefinition('probe-prepared', ComponentKind::Field)]);
$registry->register(new ComponentDefinition('probe-between-prepare-commit', ComponentKind::Display));
familyReject(fn () => $registry->commit($prepared));
familyCheck(! $registry->has('probe-prepared'), 'Stale prepared batch replaced a newer registry');
familyReject(fn () => (new ComponentRegistry)->commit($prepared));
$forged = new PreparedComponentRegistration(
    $registry,
    $registry->revision(),
    1,
    $registry->compiledGraph(),
);
familyReject(fn () => $registry->commit($forged));
$once = $registry->prepareBatch([new ComponentDefinition('probe-once', ComponentKind::Display)]);
$registry->commit($once);
familyReject(fn () => $registry->commit($once));

$diamond = new ComponentRegistry;
$diamond->registerBatch([
    new ComponentDefinition('probe-diamond-a', ComponentKind::Field, styles: ['probe-diamond-a'], stylesFrom: ['probe-diamond-c'], uses: ['probe-diamond-b'], capabilities: ['extra' => ['probe-diamond-optional']]),
    new ComponentDefinition('probe-diamond-b', ComponentKind::Field, styles: ['probe-diamond-b'], uses: ['probe-diamond-base']),
    new ComponentDefinition('probe-diamond-c', ComponentKind::Field, styles: ['probe-diamond-c'], uses: ['probe-diamond-base']),
    new ComponentDefinition('probe-diamond-base', ComponentKind::Display, styles: ['probe-diamond-base']),
    new ComponentDefinition('probe-diamond-optional', ComponentKind::Field, styles: ['probe-diamond-optional']),
]);
familyCheck($diamond->stylesFor('probe-diamond-a') === ['probe-diamond-base', 'probe-diamond-c', 'probe-diamond-b', 'probe-diamond-a'], 'Compiled CSS order or diamond deduplication changed');
familyCheck($diamond->runtimeComponents('probe-diamond-a') === ['probe-diamond-base', 'probe-diamond-b', 'probe-diamond-a'], 'CSS-only or optional dependency became eager runtime');
$deep = new ComponentRegistry;
for ($depth = 0; $depth < 512; $depth++) {
    $deep->register(new ComponentDefinition('probe-depth-'.$depth, ComponentKind::Field, uses: $depth === 0 ? [] : ['probe-depth-'.($depth - 1)]));
}
familyCheck(count($deep->freeze()->runtimeComponents('probe-depth-511')) === 512, 'Deep graph compilation failed');
familyReject(fn () => $diamond->registerBatch([
    new ComponentDefinition('probe-optional-cycle-a', ComponentKind::Field, capabilities: ['optional' => ['probe-optional-cycle-b']]),
    new ComponentDefinition('probe-optional-cycle-b', ComponentKind::Field, uses: ['probe-optional-cycle-a']),
]));
familyCheck(! $diamond->has('probe-optional-cycle-a'), 'Dormant capability cycle partially committed');
$directory = sys_get_temp_dir().'/fff-family-'.bin2hex(random_bytes(6));
mkdir($directory, 0700);
file_put_contents($directory.'/style.css', '.probe-family { color: red; }');
file_put_contents($directory.'/entry.js', 'export default () => ({ init() {} });');
try {
    $registrar->registerFamily([
        new ComponentRegistration(new ComponentDefinition('probe-family-field', ComponentKind::Field, stylesFrom: ['probe-family-display'], uses: ['select-field'], entry: 'probe-family-field'), entryPath: $directory.'/entry.js'),
        new ComponentRegistration(new ComponentDefinition('probe-family-column', ComponentKind::Column, stylesFrom: ['probe-family-display'])),
        new ComponentRegistration(new ComponentDefinition('probe-family-entry', ComponentKind::Entry, stylesFrom: ['probe-family-display'])),
        new ComponentRegistration(new ComponentDefinition('probe-family-display', ComponentKind::Display, styles: ['probe-family-style']), ['probe-family-style' => $directory.'/style.css']),
    ]);
    familyCheck($registry->stylesFor('probe-family-column') === ['probe-family-style'], 'Display CSS owner not shared');
    familyCheck($registry->runtimeComponents('probe-family-entry') === ['probe-family-entry'], 'Entry loaded field JS');
    familyCheck(in_array('select-field', $registry->runtimeComponents('probe-family-field'), true), 'Field composition missing');
    foreach (['field', 'column', 'entry'] as $surface) {
        $html = Blade::render('<x-filament-flex-fields::assets :component="$id" :consumer="$owner" />', ['id' => 'probe-family-'.$surface, 'owner' => 'probe-family.'.$surface]);
        familyCheck(str_contains($html, 'data-fff-asset-consumer="probe-family-'.$surface.'"'), 'Independent consumer missing');
    }
    $revision = $registry->revision();
    $package = FilamentFlexFieldsPlugin::PACKAGE_NAME;
    FilamentAsset::register([
        FlexFieldsAlpineComponent::make('probe-reserved-module', $directory.'/entry.js'),
    ], $package);
    $assetCount = count(FilamentAsset::getAlpineComponents([$package]));
    familyReject(fn () => $registrar->registerFamily([
        new ComponentRegistration(new ComponentDefinition('probe-family-first', ComponentKind::Field)),
        new ComponentRegistration(new ComponentDefinition('probe-reserved-owner', ComponentKind::Field, entry: 'probe-reserved-module'), entryPath: $directory.'/entry.js'),
    ]));
    familyReject(fn () => $registrar->register(new ComponentDefinition('probe-core-style', ComponentKind::Display, styles: ['core']), ['core' => $directory.'/style.css']));
    familyCheck($registry->revision() === $revision && count(FilamentAsset::getAlpineComponents([$package])) === $assetCount, 'Asset identity failure partially mutated state');
    foreach ([
        [new ComponentRegistration(new ComponentDefinition('probe-family-first', ComponentKind::Field)), new ComponentRegistration(new ComponentDefinition('probe-family-bad', ComponentKind::Entry, styles: ['probe-family-missing']), ['probe-family-missing' => $directory.'/missing.css'])],
        [new ComponentRegistration(new ComponentDefinition('probe-family-first', ComponentKind::Field)), new ComponentRegistration(new ComponentDefinition('probe-family-bad', ComponentKind::Entry, stylesFrom: ['nonexistent-family-dependency']))],
        [new ComponentRegistration(new ComponentDefinition('probe-family-first', ComponentKind::Field)), new ComponentRegistration(new ComponentDefinition('probe-family-bad', ComponentKind::Field, entry: 'probe-family-field'), entryPath: $directory.'/entry.js')],
        [new ComponentRegistration(new ComponentDefinition('probe-family-first', ComponentKind::Field, stylesFrom: ['probe-family-bad'])), new ComponentRegistration(new ComponentDefinition('probe-family-bad', ComponentKind::Entry, stylesFrom: ['probe-family-first']))],
    ] as $invalid) {
        familyReject(fn () => $registrar->registerFamily($invalid));
        familyCheck($registry->revision() === $revision && ! $registry->has('probe-family-first'), 'Rejected family partially registered');
    }
    familyReject(fn () => $registrar->registerFamily([]));
    familyReject(fn () => $registrar->registerFamily(['bad']));
    familyReject(fn () => $registrar->register(new ComponentDefinition('probe-file-alias', ComponentKind::Display, styles: ['probe-file-alias']), ['probe-file-alias' => $directory.'/style.css']));
    symlink($directory.'/style.css', $directory.'/alias.css');
    familyReject(fn () => $registrar->register(new ComponentDefinition('probe-symlink-alias', ComponentKind::Display, styles: ['probe-symlink-alias']), ['probe-symlink-alias' => $directory.'/alias.css']));
    unlink($directory.'/alias.css');
    familyCheck($registry->revision() === $revision, 'Repeated asset source partially mutated registry');
} finally {
    unlink($directory.'/style.css');
    unlink($directory.'/entry.js');
    rmdir($directory);
}

// Plans cache immutable identities only, remain bounded and follow registry revisions.
$cacheRegistry = new ComponentRegistry;
$capabilities = [];
for ($i = 0; $i < 9; $i++) {
    $name = 'probe-cache-'.$i;
    $cacheRegistry->register(new ComponentDefinition($name, ComponentKind::Display));
    $capabilities[$name] = [$name];
}
$cacheRegistry->register(new ComponentDefinition('probe-cache-root', ComponentKind::Field, capabilities: $capabilities));
app()->instance(ComponentRegistry::class, $cacheRegistry);
try {
    $warm = ComponentAssetPlan::for('probe-cache-root');
    familyCheck(ComponentAssetPlan::for('probe-cache-root') === $warm, 'Warm asset plan was recomputed');
    familyReject(fn () => ComponentAssetPlan::for('probe-cache-root', ['invalid']));
    for ($mask = 0; $mask < 512; $mask++) {
        $enabled = [];
        for ($i = 0; $i < 9; $i++) {
            if (($mask & (1 << $i)) !== 0) {
                $enabled[] = 'probe-cache-'.$i;
            }
        }
        ComponentAssetPlan::for('probe-cache-root', $enabled);
    }
    $cache = (new ReflectionMethod(ComponentAssetPlan::class, 'for'))->getStaticVariables()['cache'];
    familyCheck(count($cache[$cacheRegistry]['plans']) === 256, 'Asset plan cache is not bounded');
    $before = ComponentAssetPlan::for('probe-cache-root');
    $cacheRegistry->register(new ComponentDefinition('probe-cache-later', ComponentKind::Display));
    familyCheck(ComponentAssetPlan::for('probe-cache-root') !== $before, 'Registry revision retained an obsolete plan');
    $cacheRegistry->register(new ComponentDefinition('probe-cache-invalid', ComponentKind::Field, uses: ['missing-cache-target']));
    familyReject(fn () => ComponentAssetPlan::for('probe-cache-root'));
} finally {
    app()->instance(ComponentRegistry::class, $registry);
}

$valid = ScheduleField::defaultSchedule('UTC');
$validator = new ScheduleValidator;
$invalidDays = [
    ['enabled' => 'false', 'slots' => []],
    ['enabled' => true, 'slots' => ['custom' => ['from' => '09:00', 'to' => '10:00']]],
    ['enabled' => false, 'slots' => 'invalid'],
    ['enabled' => true, 'slots' => [['from' => 'tomorrow', 'to' => '17:00']]],
    ['enabled' => true, 'slots' => [['from' => '24:00', 'to' => '17:00']]],
    ['enabled' => true, 'slots' => [['from' => '09:60', 'to' => '17:00']]],
    ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'type' => 'custom']]],
    ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '17:00', 'overnight' => 'false']]],
    ['enabled' => false, 'slots' => [['from' => 'bad', 'to' => '17:00']]],
    ['enabled' => false, 'slots' => array_fill(0, 1000, ['from' => '09:00', 'to' => '17:00'])],
];
foreach ($invalidDays as $day) {
    $errors = [];
    $state = $valid;
    $state['days']['mon'] = $day;
    $validator->validate($state, ScheduleDays::ALL, function ($message) use (&$errors) {
        $errors[] = $message;
    }, 'UTC');
    familyCheck($errors !== [], 'Malformed payload accepted');
}
$host = new class extends V590SchemaHost
{
    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([ScheduleField::make('hours')->lockedDays(['sat', 'sun'])->weekStartsOn(0)]);
    }
};
$host->setId('locked-schedule-probe');
$host->form->fill();
familyCheck(array_key_first($host->data['hours']['days']) === 'sun', 'Sunday-first field order ignored');
$host->form->getState();
$required = ScheduleField::make('required')->required();
$breakOnly = $valid;
foreach ($breakOnly['days'] as &$day) {
    foreach ($day['slots'] as &$slot) {
        $slot['type'] = 'break';
    }
    unset($slot);
}
unset($day);
familyCheck($required->isEmptyState($breakOnly), 'Required availability counted breaks as working slots');
$oversized = $valid;
$oversized['days']['mon']['slots'] = array_fill(0, 1000, ['from' => 'tomorrow', 'to' => '17:00']);
familyCheck(! $required->isEmptyState($oversized), 'Required check must delegate oversized payloads to bounded validation');
foreach ($invalidDays as $day) {
    $host->form->fill();
    $host->data['hours']['days']['mon'] = $day;
    try {
        $host->form->getState();
        throw new RuntimeException('Form saved invalid schedule');
    } catch (ValidationException $exception) {
        familyCheck(isset($exception->errors()['data.hours']), 'Field validation error missing');
    }
}
$host->form->fill();
$host->data['hours']['days']['sat'] = ['enabled' => true, 'slots' => [['from' => '09:00', 'to' => '10:00']]];
try {
    $host->form->getState();
    throw new RuntimeException('Locked day modified during create');
} catch (ValidationException $exception) {
    familyCheck(isset($exception->errors()['data.hours']), 'Locked day error missing');
}
$record = ['hours' => $valid];
$record['hours']['days']['sat'] = ['enabled' => true, 'slots' => [['from' => '10:00', 'to' => '11:00']]];
$host->form->record($record)->fill($record);
$host->form->getState();
$host->data['hours']['days']['sat']['slots'][0]['to'] = '12:00';
$host->form->getState(); // Existing contract: slots stay editable on a toggle-locked day.
$host->data['hours']['days']['sat']['enabled'] = false;
try {
    $host->form->getState();
    throw new RuntimeException('Locked day modified during edit');
} catch (ValidationException $exception) {
    familyCheck(isset($exception->errors()['data.hours']), 'Locked record error missing');
}
$host->data['hours'] = [];
try {
    $host->form->getState();
    throw new RuntimeException('Clearing state bypassed locked day');
} catch (ValidationException $exception) {
    familyCheck(isset($exception->errors()['data.hours']), 'Cleared locked state error missing');
}

$column = ScheduleColumn::make('hours')->state($valid)->locale('pl')->weekStartsOn(0)->rtl();
$projection = $column->getScheduleDisplay();
familyCheck($projection['days'][0]['key'] === 'sun' && $projection['days'][1]['short'] !== 'Mon', 'Locale or week order missing');
familyCheck($column->getScheduleDirection() === 'rtl', 'RTL setting lost');
$entry = ScheduleEntry::make('hours')->state($valid)->locale('ar')->weekStartsOn(0)->rtl()->hourCycle(12);
familyCheck($entry->getScheduleDisplay()['days'][1]['label'] !== 'Monday', 'Arabic labels missing');
$localizedHtml = Schema::make($host)->record(['id' => 1, 'hours' => $valid])->components([
    ScheduleEntry::make('hours')->key('translated-entry')->locale('pl')->rtl(),
])->toHtml();
familyCheck(str_contains($localizedHtml, 'Strefa czasowa') && str_contains($localizedHtml, 'dir="rtl"'), 'Entry locale/direction markup missing');
familyReject(fn () => ScheduleField::make('bad')->weekStartsOn(7)->getDays());
familyReject(fn () => ScheduleColumn::make('bad')->state($valid)->hourCycle(16)->getScheduleDisplay());
echo "PASS: typed families, forward dependencies, independent surfaces, rejection atomicity, strict payload validation, locked create/edit, multilingual ordering and RTL.\n";

// Repeat the cross-provider contract through actual Laravel bootstrap, not only
// an explicit builder. Each harness owns a fresh container and asset manager.
final class RegistryProbeConsumerProvider extends ServiceProvider
{
    /** @var list<ComponentDefinition> */
    public static array $definitions = [];

    public static ?ComponentFamily $family = null;

    public function boot(ComponentRegistrar $components): void
    {
        $components->collectFamily([
            new ComponentRegistration(new ComponentDefinition('probe-boot-consumer', ComponentKind::Field, uses: ['probe-boot-shared'])),
        ]);
        $components->collectComponentFamily(self::$family ?? throw new RuntimeException('Missing semantic consumer fixture'), self::$definitions);
    }
}

final class RegistryProbeDependencyProvider extends ServiceProvider
{
    /** @var list<ComponentDefinition> */
    public static array $definitions = [];

    public static ?ComponentFamily $family = null;

    public function boot(ComponentRegistrar $components): void
    {
        $components->collectFamily([
            new ComponentRegistration(new ComponentDefinition('probe-boot-shared', ComponentKind::Display)),
        ]);
        $components->collectComponentFamily(self::$family ?? throw new RuntimeException('Missing semantic dependency fixture'), self::$definitions);
    }
}

$providers = [
    LivewireServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
];
$semanticRoot = sys_get_temp_dir().'/fff-family-boot-'.bin2hex(random_bytes(8));
$filesystem = new Filesystem;
$filesystem->ensureDirectoryExists($semanticRoot.'/alpha');
$filesystem->ensureDirectoryExists($semanticRoot.'/beta');
$css = '.semantic-probe { color: inherit; }';
$js = 'export default function () { return { init() {} }; }';
file_put_contents($semanticRoot.'/alpha/control.css', $css);
file_put_contents($semanticRoot.'/beta/control.js', $js);
$alphaAssets = new AssetPackage(
    new AssetManifest('example/alpha', [
        'style' => new AssetArtifact('control.css',
            ArtifactKind::Stylesheet, hash('sha256', $css), strlen($css)),
    ], styles: ['probealpha-style' => 'style']), $semanticRoot.'/alpha',
);
$betaAssets = new AssetPackage(
    new AssetManifest('example/beta', [
        'entry' => new AssetArtifact('control.js',
            ArtifactKind::Module, hash('sha256', $js), strlen($js)),
    ], entries: ['probebeta-field' => 'entry']), $semanticRoot.'/beta',
);
$alphaFamily = new ComponentFamily(
    new ComponentPackage($alphaAssets, 'probealpha', '1.0.0'),
    'probealpha-family', ['field' => 'probealpha-field'],
);
$betaFamily = new ComponentFamily(
    new ComponentPackage($betaAssets, 'probebeta', '1.0.0'),
    'probebeta-family', ['field' => 'probebeta-field'],
);
RegistryProbeDependencyProvider::$family = $alphaFamily;
RegistryProbeDependencyProvider::$definitions = [new ComponentDefinition('probealpha-field', ComponentKind::Field,
    styles: ['probealpha-style'], surface: new ComponentSurface($alphaFamily, ComponentKind::Field, ScheduleField::class))];
RegistryProbeConsumerProvider::$family = $betaFamily;
RegistryProbeConsumerProvider::$definitions = [new ComponentDefinition('probebeta-field', ComponentKind::Field,
    uses: ['probealpha-field'], entry: 'probebeta-field', surface: new ComponentSurface($betaFamily, ComponentKind::Field, ScheduleField::class))];
try {
    foreach ([
        [RegistryProbeConsumerProvider::class, RegistryProbeDependencyProvider::class],
        [RegistryProbeDependencyProvider::class, RegistryProbeConsumerProvider::class],
    ] as $order) {
        $bootApp = Application::create(null, null, ['extra' => ['providers' => [
            ...$providers, ...$order, FilamentFlexFieldsServiceProvider::class,
        ]]]);
        $bootRegistry = $bootApp->make(ComponentRegistry::class);
        familyCheck($bootRegistry->isFrozen(), 'Provider graph remained mutable after application boot');
        familyCheck($bootRegistry->runtimeComponents('probe-boot-consumer') === ['probe-boot-shared', 'probe-boot-consumer'], 'Provider boot order affected compiled composition');
        familyCheck($bootRegistry->runtimeComponents('probebeta-field') === ['probealpha-field', 'probebeta-field'], 'Semantic family boot order affected runtime composition');
        familyCheck($bootRegistry->stylesFor('probebeta-field') === ['probealpha-style'], 'Semantic dependency lost its single CSS owner');
        familyCheck($bootRegistry->family('probebeta-family')->package->assets->manifest->package === 'example/beta'
            && $bootRegistry->surface('probealpha-field')->class === ScheduleField::class, 'Semantic contract missing after real boot');
        familyCheck(count($bootRegistry->packageAssets()->packages) === 2, 'Semantic package installation duplicated or dropped');
        familyReject(fn () => $bootApp->make(ComponentRegistrar::class)->register(new ComponentDefinition('probe-boot-late', ComponentKind::Field)));
    }
} finally {
    RegistryProbeConsumerProvider::$family = RegistryProbeDependencyProvider::$family = null;
    RegistryProbeConsumerProvider::$definitions = RegistryProbeDependencyProvider::$definitions = [];
    $filesystem->deleteDirectory($semanticRoot);
}
echo "PASS: actual legacy and semantic-family providers in both orders, package/CSS/runtime composition before freeze and post-boot mutation rejection.\n";
