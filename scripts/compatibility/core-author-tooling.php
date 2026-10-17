<?php

declare(strict_types=1);

// New author-tooling acceptance; existing tests do not cover this generator.
require __DIR__.'/schema-render-contract.php';

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

use Acceptance\Summary\Forms\SummaryField;
use Acceptance\Summary\Infolists\SummaryEntry;
use Acceptance\Summary\SummaryServiceProvider;
use Acceptance\Summary\Tables\SummaryColumn;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentDependencyExplanation;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentRegistrationExplanation;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentRemovalImpact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifestCompiler;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Authoring\ComponentPackageBlueprint;
use Bjanczak\FilamentFlexFields\Support\Components\Authoring\ComponentPackageGenerator;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildSlot;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentDeprecation;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentPackageSelection;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\ComponentRegistrationCollector;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\DefinitionOrigin;
use Bjanczak\FilamentFlexFields\Support\Components\Registration\RegistrationMetadata;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Composer\InstalledVersions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Support\ArrayRecord;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

function authorCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function authorReject(Closure $operation): void
{
    try {
        $operation();
    } catch (LogicException|RuntimeException $error) {
        return;
    }
    throw new RuntimeException('Invalid author operation was accepted.');
}
/** @return array{int, string, string} */
function authorProcess(array $command, string $directory, ?array $environment = null): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start author-tooling acceptance.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $stdout, $stderr];
}

/** Actual dist installation is opt-in because it resolves an isolated Composer application. */
function authorInstalledResult(string $output, string $phase): array
{
    try {
        return json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('Installed author '.$phase.' returned invalid JSON: '.substr($output, 0, 2048), previous: $error);
    }
}

function authorComposerInstallation(string $root, string $generated, Filesystem $filesystem): array
{
    $sources = $root.'/composer-sources';
    $application = $root.'/composer-application';
    $filesystem->makeDirectory($sources, 0700, true);
    $filesystem->makeDirectory($application, 0700, true);
    $core = $sources.'/core';
    $extension = $sources.'/extension';
    $status = $sources.'/status';
    (new ComponentPackageGenerator)->generate(new ComponentPackageBlueprint('acceptance/status', 'acceptance-status', 'Acceptance\\Status', 'Status'), $status);
    symlink(getcwd().'/node_modules', $status.'/node_modules');
    [$statusCode, , $statusError] = authorProcess(['node', 'build.mjs'], $status, [...getenv(), 'FLEX_FIELDS_ROOT' => getcwd(), 'PHP_BINARY' => PHP_BINARY]);
    authorCheck($statusCode === 0, 'Second independent family build failed: '.$statusError);
    unlink($status.'/node_modules');
    foreach (['src', 'resources', 'config', 'routes', 'database', 'stubs'] as $directory) {
        $filesystem->copyDirectory(getcwd().'/'.$directory, $core.'/'.$directory);
    }
    foreach (['composer.json', 'VERSION', '.gitattributes'] as $file) {
        $filesystem->copy(getcwd().'/'.$file, $core.'/'.$file);
    }
    $filesystem->makeDirectory($core.'/scripts/assets', 0755, true);
    foreach (['compile-package-manifest.php', 'compile-esbuild-package.mjs', 'esbuild-package-graph.mjs', 'flex-sdk-plugin.mjs'] as $file) {
        $filesystem->copy(getcwd().'/scripts/assets/'.$file, $core.'/scripts/assets/'.$file);
    }
    foreach (['src', 'resources'] as $directory) {
        $filesystem->copyDirectory($generated.'/'.$directory, $extension.'/'.$directory);
    }
    foreach (['composer.json', 'package.json', 'build.mjs', 'README.md'] as $file) {
        $filesystem->copy($generated.'/'.$file, $extension.'/'.$file);
    }
    $filesystem->makeDirectory($extension.'/src/Validation', 0755, true);
    file_put_contents($extension.'/src/Validation/SummaryValueRule.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Acceptance\Summary\Validation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
final class SummaryValueRule implements ValidationRule
{
    public function __construct(private readonly int $limit = 120) {}
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || array_diff(array_keys($value), ['text', 'format']) !== []
            || !is_string($value['text'] ?? null) || mb_strlen($value['text']) > $this->limit
            || (array_key_exists('format', $value) && $value['format'] !== 2)
            || str_contains($value['text'], 'forbidden')) $fail('The custom author rule rejected this value.');
    }
}
PHP);
    $filesystem->makeDirectory($extension.'/src/Schema', 0755, true);
    file_put_contents($extension.'/src/Schema/SummarySchemaAdapter.php', <<<'PHP'
<?php
declare(strict_types=1);
namespace Acceptance\Summary\Schema;
use Acceptance\Summary\Validation\SummaryValueRule;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaAdapter;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Filament\Forms\Components\Field;
use Filament\Tables\Columns\Column;
use Filament\Infolists\Components\Entry;
final class SummarySchemaAdapter implements FamilySchemaAdapter, \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueMigration
{
    public function authorize(FamilySchemaDefinition $definition, FamilyRenderContext $context): bool
    {
        return $context->tenantId === 'archive-tenant' && $context->userKey === 'archive-user';
    }
    public function configure(Field|Column|Entry $surface, FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): void
    {
        if ($surface instanceof Field) $surface->schema([FlexTextInput::make('text')->maxLength($configuration['limit'])]);
    }
    public function rules(FamilySchemaDefinition $definition, FamilyRenderContext $context, array $configuration): array
    {
        return ['array', new SummaryValueRule($configuration['limit'])];
    }
    public function migrateConfiguration(array $configuration, int $fromVersion, int $toVersion): array
    {
        if ($fromVersion !== 1 || $toVersion !== 2 || array_diff(array_keys($configuration), ['maxLength']) !== []) throw new \InvalidArgumentException('Unsupported author configuration migration.');
        return ['limit' => $configuration['maxLength'] ?? 8];
    }
    public function supportsValueMigration(int $fromVersion, int $toVersion): bool
    {
        return in_array([$fromVersion, $toVersion], [[1, 2], [2, 1]], true);
    }
    public function migrateStoredValue(mixed $value, FamilySchemaDefinition $from, FamilySchemaDefinition $to, FamilyRenderContext $context): mixed
    {
        if (!is_array($value) || !is_string($value['text'] ?? null)
            || array_diff(array_keys($value), ['text', 'format']) !== []
            || !$this->supportsValueMigration($from->valueVersion, $to->valueVersion)) throw new \InvalidArgumentException('Unsupported stored author value migration.');
        return $to->valueVersion === 2 ? ['text' => $value['text'], 'format' => 2] : ['text' => $value['text']];
    }
}
PHP);
    $provider = $extension.'/src/SummaryServiceProvider.php';
    $providerSource = file_get_contents($provider);
    $providerSource = str_replace("        ]);\n        \$definitions = [", <<<'PHP'
        ], configurationVersion: 2, valueVersion: 1, schema: new \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaContract(
            \Acceptance\Summary\Schema\SummarySchemaAdapter::class,
            new \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema([
                'limit' => new \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationOption(
                    \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationType::Integer,
                    default: 8, min: 1, max: 120,
                ),
            ]),
        ));
        $definitions = [
PHP, $providerSource, $providerChanges);
    authorCheck($providerChanges === 1, 'Author fixture failed to add its own family schema declaration.');
    $providerSource = str_replace('        $metadata = ', <<<'PHP'
        $definitions[] = new ComponentDefinition(
            id: 'acceptance-summary-composition', kind: ComponentKind::Field, stylesFrom: ['composite-field'],
            children: [
                'text' => new ChildSlot('text', 'flex-text-input', ComponentKind::Field, renderer: FlexTextInput::class,
                    configuration: new \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema([
                        'maxLength' => new \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationOption(
                            \Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationType::Integer, min: 1, max: 120,
                        ),
                    ]), configurationMap: ['limit' => 'maxLength']),
                'extra' => new ChildSlot('extra', 'flex-text-input', ComponentKind::Field, renderer: FlexTextInput::class),
                'dialog' => new ChildSlot('dialog', 'acceptance-summary-field', ComponentKind::Field, renderer: SummaryField::class),
            ],
        );
        $metadata =
PHP, $providerSource, $compositionChanges);
    authorCheck($compositionChanges === 1, 'Author fixture failed to declare public composition slots.');
    file_put_contents($provider, $providerSource);
    $field = $extension.'/src/Forms/SummaryField.php';
    file_put_contents($field, str_replace('parent::setUp();', 'parent::setUp();'.PHP_EOL.'        $this->rule(new \\Acceptance\\Summary\\Validation\\SummaryValueRule);', file_get_contents($field)));
    $archives = [];
    foreach (['core' => $core, 'extension' => $extension, 'status' => $status] as $name => $directory) {
        $archives[$name] = $root.'/'.$name.'-install.zip';
        foreach ([['git', 'init', '--quiet'], ['git', 'add', '.'],
            ['git', '-c', 'user.name=FLEX Acceptance', '-c', 'user.email=acceptance@example.invalid', 'commit', '--quiet', '-m', 'Owned Composer archive fixture'],
            ['git', 'archive', '--format=zip', '--output='.$archives[$name], 'HEAD']] as $command) {
            [$code, , $error] = authorProcess($command, $directory);
            authorCheck($code === 0, 'Composer archive creation failed: '.$error);
        }
    }
    $package = static fn (string $name, string $version, string $archive): array => [
        'type' => 'package', 'package' => [...json_decode(file_get_contents((match ($name) {
            'acceptance/summary' => $extension, 'acceptance/status' => $status, default => $core
        }).'/composer.json'), true, 512, JSON_THROW_ON_ERROR),
            'version' => $version, 'dist' => ['type' => 'zip', 'url' => 'file://'.$archive]],
    ];
    file_put_contents($application.'/composer.json', json_encode([
        'name' => 'acceptance/isolated-application', 'type' => 'project',
        'require' => ['janczakb/filament-flex-fields' => '4.0.0', 'acceptance/summary' => '1.0.0', 'acceptance/status' => '1.0.0',
            'filament/filament' => in_array('--filament-floor', $_SERVER['argv'] ?? [], true)
                ? '5.9.0' : InstalledVersions::getPrettyVersion('filament/filament'),
            'orchestra/testbench' => InstalledVersions::getPrettyVersion('orchestra/testbench')],
        'repositories' => [$package('janczakb/filament-flex-fields', '4.0.0', $archives['core']), $package('acceptance/summary', '1.0.0', $archives['extension']), $package('acceptance/status', '1.0.0', $archives['status'])],
        'config' => ['allow-plugins' => false, 'platform' => ['php' => '8.3.33']], 'minimum-stability' => 'stable', 'prefer-stable' => true,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    [$code, $output, $error] = authorProcess(['composer', 'install', '--quiet', '--no-interaction', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress'], $application);
    if ($code !== 0) {
        [, $details, $diagnostic] = authorProcess(['composer', 'update', '--dry-run', '--no-interaction', '--no-scripts', '--no-plugins', '--no-progress', '--no-ansi'], $application);
        $error .= $diagnostic.$details;
    }
    authorCheck($code === 0, 'Independent Composer installation failed: '.$error.$output);
    $environment = [...getenv()];
    unset($environment['FLEX_FIELDS_ROOT'], $environment['FLEX_FIELDS_AUTOLOAD']);
    // No package/host autoload forwarding and no vendor/source symlink is used.
    file_put_contents($application.'/verify.php', <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
use Acceptance\Summary\Forms\SummaryField;
use Acceptance\Summary\Tables\SummaryColumn;
use Acceptance\Summary\Infolists\SummaryEntry;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPublisher;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentDependencyExplanation;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaCodec;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaSubmission;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldTableBuilder;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldInfolistBuilder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Support\ArrayRecord;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Orchestra\Testbench\Foundation\Application;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$providers = [Livewire\LivewireServiceProvider::class, BladeUI\Icons\BladeIconsServiceProvider::class,
    BladeUI\Heroicons\BladeHeroiconsServiceProvider::class, Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider::class,
    Filament\FilamentServiceProvider::class, Filament\Widgets\WidgetsServiceProvider::class, Filament\Notifications\NotificationsServiceProvider::class,
    Filament\Support\SupportServiceProvider::class, Filament\Actions\ActionsServiceProvider::class,
    Filament\Schemas\SchemasServiceProvider::class, Filament\Forms\FormsServiceProvider::class,
    Filament\Tables\TablesServiceProvider::class, Filament\Infolists\InfolistsServiceProvider::class];
$withoutAuthor = in_array('--without-author', $argv, true);
foreach ($withoutAuthor ? ['janczakb/filament-flex-fields'] : ['janczakb/filament-flex-fields', 'acceptance/summary', 'acceptance/status'] as $package) {
    $installed = Composer\InstalledVersions::getInstallPath($package);
    check(!is_link($installed), 'Dist installation unexpectedly uses a source symlink.');
    $metadata = json_decode(file_get_contents($installed.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    array_push($providers, ...$metadata['extra']['laravel']['providers']);
}
$cacheRoot = __DIR__.($withoutAuthor ? '/owned-core-bootstrap' : '/owned-bootstrap');
if (!is_dir($cacheRoot)) mkdir($cacheRoot, 0755);
foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_SERVICES_CACHE' => 'services.php', 'APP_PACKAGES_CACHE' => 'packages.php'] as $name => $file) {
    $_ENV[$name] = $_SERVER[$name] = $cacheRoot.'/'.$file;
    putenv($name.'='.$cacheRoot.'/'.$file);
}
$resolve = static function ($application) use ($cacheRoot): void { $application->useBootstrapPath($cacheRoot); };
file_put_contents($cacheRoot.'/app.php', '<?php $app = \\Orchestra\\Testbench\\Foundation\\Application::create(null, static function ($app) { $app->useBootstrapPath(__DIR__); }, '.var_export(['extra' => ['dont-discover' => ['*'], 'providers' => $providers]], true).'); $app->bootstrapWith([]); return $app;');
if (is_file($cacheRoot.'/config.php')) {
    // Testbench deliberately ignores Laravel's cached configuration loader.
    // A deployment restart must instead consume the real cached repository.
    $app = \Illuminate\Foundation\Application::configure(basePath: __DIR__.'/vendor/orchestra/testbench-core/laravel')
        ->withProviders($providers, false)->create();
    $app->useBootstrapPath($cacheRoot);
    try { $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap(); }
    catch (\Throwable $error) { fwrite(STDERR, (string) $error); exit(1); }
} else {
    $app = Application::create(null, $resolve, ['extra' => ['dont-discover' => ['*'], 'providers' => $providers]]);
}
set_exception_handler(static function (\Throwable $error): void { fwrite(STDERR, (string) $error); exit(1); });
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('view.compiled', __DIR__.'/compiled-views');
if (!is_dir(__DIR__.'/compiled-views')) mkdir(__DIR__.'/compiled-views', 0755);
$app->usePublicPath(__DIR__.'/custom-public');
if (!is_dir($app->publicPath())) mkdir($app->publicPath(), 0755);
if (in_array('--cache-build', $argv, true)) {
    foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
        check(Artisan::call($command) === 0, 'Installed native cache command failed: '.$command.' '.Artisan::output());
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        \Illuminate\Container\Container::setInstance($app);
    }
    check(is_file($app->getCachedConfigPath()) && is_file($app->getCachedRoutesPath()), 'Native deployment cache files missing.');
    echo json_encode(['cacheCommands' => ['config:cache', 'route:cache', 'view:cache'], 'customPackage' => !$withoutAuthor], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if (in_array('--cache-clear', $argv, true)) {
    foreach (['config:clear', 'route:clear', 'view:clear'] as $command) check(Artisan::call($command) === 0, 'Native cache clear failed.');
    echo json_encode(['cacheCleared' => true], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if (in_array('--cached', $argv, true)) check($app->configurationIsCached() && $app->routesAreCached(), 'Fresh process did not consume the cached deployment.');
if ($withoutAuthor) {
    check(!app(ComponentRegistry::class)->has('acceptance-summary-field'), 'Core-only deployment loaded an unconfigured author provider.');
    echo json_encode(['coreOnlyCachedBoot' => true, 'compiledDefinitions' => count(app(ComponentRegistry::class)->all())], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
foreach ([SummaryField::class, ComponentRegistry::class] as $class) check(str_starts_with((new ReflectionClass($class))->getFileName(), __DIR__.'/vendor/'), 'Fresh process resolves checkout PHP.');
class InstalledForm extends Component implements HasForms, HasTable {
    use InteractsWithForms, InteractsWithTable;
    public ?array $data = [];
    public function form(Schema $schema): Schema {
        return $schema->statePath('data')->components([
            SummaryField::make('one')->schema([FlexTextInput::make('text')->required()->maxLength(8)]),
            SummaryField::make('two')->schema([FlexTextInput::make('text')->required()->maxLength(12)]),
            FlexTextInput::make('independent')->required(),
        ]);
    }
    public function table(Table $table): Table { return $table->records(fn () => []); }
}
$host = new InstalledForm; $host->setId('installed-author');
$host->form->fill(['one' => ['text' => 'Safe'], 'two' => ['text' => 'Another'], 'independent' => 'Independent']);
$html = $host->form->toHtml();
check(substr_count($html, 'acceptance-summary-field') >= 2 && str_contains($html, 'data.one.text') && str_contains($html, 'data.two.text'), 'Independent nested field identities missing.');
check($host->form->getState()['two']['text'] === 'Another', 'Valid nested values did not pass.');
foreach ([['text' => 'forbidden'], ['text' => str_repeat('a', 9)], ['text' => 'Safe', 'injected' => true], ['text' => ['forged']]] as $bad) {
    $host->data['one'] = $bad;
    try { $host->form->getState(); throw new RuntimeException('Invalid installed author value accepted.'); }
    catch (ValidationException $error) { check($error->errors() !== [], 'Invalid value failed without field errors.'); }
}
$host->data['one'] = ['text' => 'Safe']; $host->data['two'] = ['text' => 'forbidden'];
try { $host->form->getState(); throw new RuntimeException('Custom author rule was bypassed by the form.'); }
catch (ValidationException $error) { check(isset($error->errors()['data.two']), 'Custom rule did not identify the parent field.'); }
$entry = Schema::make($host)->components([SummaryEntry::make('summary')->state(['text' => '<img src=x onerror=evil>'])])->toHtml();
check(str_contains($entry, '&lt;img') && str_contains($entry, 'acceptance-summary-entry'), 'Entry escaping/ownership failed.');
$column = SummaryColumn::make('summary')->table(Table::make($host))->record([ArrayRecord::getKeyName() => 'row-one', 'summary' => ['text' => 'Safe']]);
check($column->getDisplayValue() === 'Safe' && str_contains($column->toHtml(), 'acceptance-summary-column'), 'Column state/ownership failed.');
$registry = app(ComponentRegistry::class); $explanation = new ComponentDependencyExplanation;
check(count($explanation->explain($registry, 'acceptance-summary-column')['artifacts']) === 1, 'Column eagerly loads the interactive field.');
$valueVersion = $registry->family('acceptance-summary-family')->valueVersion;
$definition = new FamilySchemaDefinition('acceptance-summary-family', 'contact', 'Contact', ['limit' => 12], 2, $valueVersion);
$context = new FamilyRenderContext('installed-schema-owner', userKey: 'archive-user', tenantId: 'archive-tenant');
$codec = app(FamilySchemaCodec::class);
$roundtrip = $codec->import($codec->export([$definition]));
check($roundtrip[0]->export() === $definition->export(), 'Installed family portable schema exchange failed.');
$old = new FamilySchemaDefinition('acceptance-summary-family', 'old_contact', 'Old contact', ['maxLength' => 6], valueVersion: $valueVersion);
$migrated = $codec->import($codec->export([$old]))[0];
check($migrated->configurationVersion === 2 && $migrated->config === ['limit' => 6], 'Installed author configuration migration failed.');
if (!file_exists(__DIR__.'/saved-family-schema.json')) file_put_contents(__DIR__.'/saved-family-schema.json', $codec->export([$definition]));
$storedFile = __DIR__.'/saved-family-value.json';
$convertedFile = __DIR__.'/converted-family-value.json';
if (!file_exists($storedFile)) file_put_contents($storedFile, \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec::encode(
    new \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshot($definition, ['text' => 'Safe']),
));
$storedBytes = file_get_contents($storedFile);
$stored = \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec::decode($storedBytes);
$migrationSource = $valueVersion === 1 && file_exists($convertedFile)
    ? \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec::decode(file_get_contents($convertedFile)) : $stored;
$conversion = app(\Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaMigration::class)->prepare($migrationSource, $context, static fn () => true);
check($conversion->after->value === ($valueVersion === 2 ? ['text' => 'Safe', 'format' => 2] : ['text' => 'Safe'])
    && $conversion->after->definition->valueVersion === $valueVersion && file_get_contents($storedFile) === $storedBytes, 'Installed value migration/rollback lost the original snapshot or exact value.');
if ($valueVersion === 2) file_put_contents($convertedFile, \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec::encode($conversion->after));
check(app(FlexFieldFormBuilder::class)->makeComponent($definition, '', $context) instanceof SummaryField, 'Dynamic form builder did not use the author family.');
check(app(FlexFieldTableBuilder::class)->makeColumn($definition, '', $context) instanceof SummaryColumn, 'Dynamic table builder did not use the author family.');
check(app(FlexFieldInfolistBuilder::class)->makeEntry($definition, '', $context) instanceof SummaryEntry, 'Dynamic infolist builder did not use the author family.');
$submission = app(FamilySchemaSubmission::class);
check($submission->prepare(['contact' => ['text' => 'Safe']], [$definition], $context, static fn () => true) === ['contact' => ['text' => 'Safe']], 'Installed public submission lost a valid author value.');
foreach ([['text' => 'forbidden'], ['text' => 'Safe', 'injected' => true], ['text' => str_repeat('a', 13)]] as $bad) {
    try { $submission->prepare(['contact' => $bad], [$definition], $context, static fn () => true); throw new RuntimeException('Invalid author value bypassed public submission.'); }
    catch (ValidationException $error) { check($error->errors() !== [], 'Invalid author submission has no field errors.'); }
}
try { $submission->prepare(['contact' => ['text' => 'Safe']], [$definition], $context, static fn () => false); throw new RuntimeException('Denied author write was accepted.'); }
catch (Illuminate\Auth\Access\AuthorizationException) {}
$first = app(ComponentAssetPublisher::class)->publish($app->publicPath());
$second = app(ComponentAssetPublisher::class)->publish($app->publicPath());
check((($argv[1] ?? '') === 'reuse' || in_array('--cached', $argv, true) ? $first['copied'] === 0 : $first['copied'] > 0)
    && $second['copied'] === 0, 'Custom public-path publication is not idempotent.');
check(Artisan::call('fff:assets:doctor', ['--published' => true, '--json' => true]) === 0, 'Fresh installed published doctor failed: '.Artisan::output());
$rule = new Acceptance\Summary\Validation\SummaryValueRule; $rejected = false;
$rule->validate('one', ['text' => 'forbidden'], static function () use (&$rejected): void { $rejected = true; });
check($rejected, 'The standalone author validation file was not invoked.');
class InstalledComposition extends Component implements HasForms {
    use InteractsWithForms;
    public ?array $data = [];
    public int $secondLimit = 12;
    public int $firstLimit = 6;
    public string $firstLocale = 'en';
    public function form(Schema $schema): Schema {
        $parents = [];
        foreach (['one' => $this->firstLimit, 'two' => $this->secondLimit] as $name => $limit) {
            $locale = $name === 'one' ? $this->firstLocale : 'pl';
            $children = new \Bjanczak\FilamentFlexFields\Support\Components\Composition\ChildRenderer(
                app(ComponentRegistry::class), 'acceptance-summary-composition',
                new FamilyRenderContext('composition-'.$name, locale: $locale), ['limit' => $limit],
            );
            $text = $children->render('text', 'first', configure: fn ($field) => $field->label($locale)->default($name)->required()->size($name === 'one' ? 'sm' : 'lg'));
            $extra = $children->render('extra', 'second', configure: fn ($field) => $field->label('Readonly '.$locale)->default('readonly')->readOnly());
            $dialog = $children->render('dialog', 'deferred', configure: fn ($field) => $field->label('Dialog '.$locale)
                ->extraAttributes(['id' => 'dialog-'.$name, 'hidden' => true, 'data-fff-asset-active' => 'false']));
            $parents[] = \Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField::make($name)
                ->assetComponent('acceptance-summary-composition')->schema([$text, $extra, $dialog])
                ->extraAttributes(['id' => 'composition-'.$name]);
        }
        $parents[] = FlexTextInput::make('independent')->label('Independent')->default('outside')
            ->extraInputAttributes(['data-browser-independent' => 'true']);
        return $schema->statePath('data')->components($parents);
    }
}
$panelOne = \Filament\Panel::make()->id('acceptance-one')->plugin(\Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::make());
\Filament\Facades\Filament::setCurrentPanel($panelOne);
$composed = new InstalledComposition; $composed->setId('installed-composition'); $composed->form->fill();
app(\Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext::class)->enableStandalone();
$composedHtml = $composed->form->toHtml();
$composedHtml .= '<aside id="installed-status">'.Schema::make($composed)->components([
    \Acceptance\Status\Infolists\StatusEntry::make('status')->state(['text' => 'Second installed family']),
])->toHtml().'</aside>';
$composedHtml .= '<aside id="installed-summary">'.Schema::make($composed)->components([
    SummaryEntry::make('summary')->state(['text' => 'First installed family']),
])->toHtml().'<div id="installed-summary-column">'.$column->toHtml().'</div></aside>';
check(substr_count($composedHtml, 'data-fff-asset-consumer="flex-text-input"') >= 7
    && str_contains($composedHtml, 'id="dialog-one"') && str_contains($composedHtml, 'id="dialog-two"'), 'Actual public A/B/C/D renderers or independent B are missing.');
$coreCss = \Filament\Support\Facades\FilamentAsset::getStyleHref(\Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::CORE_STYLESHEET_ID, \Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::PACKAGE_NAME);
file_put_contents(__DIR__.'/composition.html', '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="'.htmlspecialchars($coreCss, ENT_QUOTES).'"></head><body>'.$composedHtml.'</body></html>');
$morphed = new InstalledComposition; $morphed->setId('installed-composition'); $morphed->secondLimit = 3; $morphed->form->fill();
file_put_contents(__DIR__.'/composition-morph.html', $morphed->form->toHtml());
$panelTwo = \Filament\Panel::make()->id('acceptance-two')->plugin(\Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin::make());
\Filament\Facades\Filament::setCurrentPanel($panelTwo);
$otherPanel = new InstalledComposition; $otherPanel->setId('installed-second-panel');
$otherPanel->firstLimit = 4; $otherPanel->secondLimit = 9; $otherPanel->firstLocale = 'ar'; $otherPanel->form->fill();
file_put_contents(__DIR__.'/composition-panel.html', '<!doctype html><html dir="rtl"><head><meta charset="utf-8"><link rel="stylesheet" href="'.htmlspecialchars($coreCss, ENT_QUOTES).'"></head><body>'.$otherPanel->form->toHtml().'</body></html>');
\Filament\Facades\Filament::setCurrentPanel(null);
echo json_encode(['archiveInstalled' => true, 'php' => PHP_VERSION, 'filament' => Composer\InstalledVersions::getPrettyVersion('filament/filament'),
    'laravel' => Composer\InstalledVersions::getPrettyVersion('laravel/framework'),
    'livewire' => Composer\InstalledVersions::getPrettyVersion('livewire/livewire'),
    'groups' => 12, 'portableFamilySchema' => true, 'dynamicFieldColumnEntry' => true, 'ownRuleUsedByFormAndSubmission' => true,
    'valueVersion' => $valueVersion, 'storedValueMigration' => true, 'compositionRenderers' => true,
    'coreRelease' => app(\Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest::class)->release(),
    'extensionVersion' => Composer\InstalledVersions::getPrettyVersion('acceptance/summary'),
    'copied' => $first['copied'], 'samePublicPathRepeatCopies' => $second['copied'],
    'optionalTagsInstalled' => Composer\InstalledVersions::isInstalled('spatie/laravel-tags')], JSON_THROW_ON_ERROR).PHP_EOL;
PHP);
    [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify.php'], $application, $environment);
    authorCheck($code === 0, 'Fresh installed application failed: '.$error.$output);

    $result = authorInstalledResult($output, 'installation');
    $cacheMatrix = [];
    foreach (['--cache-build', '--cached', '--cache-clear'] as $phase) {
        [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify.php', $phase], $application, $environment);
        authorCheck($code === 0, 'Installed cache/restart matrix failed '.$phase.': '.$error.$output);
        $cacheMatrix[$phase] = authorInstalledResult($output, 'deployment '.$phase);
    }
    foreach (['--cache-build', '--cached', '--cache-clear'] as $phase) {
        [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify.php', $phase, '--without-author'], $application, $environment);
        authorCheck($code === 0, 'Core-only cache/restart matrix failed '.$phase.': '.$error.$output);
        $cacheMatrix['core-only'.$phase] = authorInstalledResult($output, 'core-only deployment '.$phase);
    }
    $browserAcceptance = static function () use ($application, $environment): ?array {
        if (! in_array('--composer-browser', $_SERVER['argv'] ?? [], true)) {
            return null;
        }
        [$code, $output, $error] = authorProcess(['node', 'scripts/compatibility/core-author-tooling-browser.mjs', '--installed-app', $application], getcwd(), $environment);
        authorCheck($code === 0, 'Installed published author browser failed: '.$error.$output);

        return authorInstalledResult($output, 'browser');
    };
    $initialBrowser = $browserAcceptance();
    $publishedFiles = static function () use ($application, $filesystem): array {
        $hashes = [];
        foreach ($filesystem->allFiles($application.'/custom-public') as $file) {
            // Mutable publication state is not an immutable artifact.
            if ($file->getFilename() === '.published.json' || str_contains($file->getFilename(), '.lock')) {
                continue;
            }
            $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);

        return $hashes;
    };
    $beforeUpgrade = $publishedFiles();
    $schemaBeforeUpgrade = file_get_contents($application.'/saved-family-schema.json');
    $valueBeforeUpgrade = file_get_contents($application.'/saved-family-value.json');
    // Build the next source generation with tools from the installed dist, then
    // archive it. Never patch a generated artifact or forward checkout PHP.
    file_put_contents($generated.'/resources/css/display.css', PHP_EOL.'.acceptance-summary-field{--acceptance-install-upgrade:1}', FILE_APPEND);
    [$code, , $error] = authorProcess(['node', 'build.mjs'], $generated, [...$environment,
        'FLEX_FIELDS_ROOT' => $application.'/vendor/janczakb/filament-flex-fields',
        'FLEX_FIELDS_AUTOLOAD' => $application.'/vendor/autoload.php', 'PHP_BINARY' => PHP_BINARY,
    ]);
    authorCheck($code === 0, 'Installed author build tools failed during the update: '.$error);
    $filesystem->copyDirectory($generated.'/resources', $extension.'/resources');
    file_put_contents($provider, str_replace(["'1.0.0'", 'valueVersion: 1'], ["'1.0.1'", 'valueVersion: 2'], file_get_contents($provider)));
    $upgradeArchive = $root.'/extension-upgrade.zip';
    foreach ([['git', 'add', '.'],
        ['git', '-c', 'user.name=FLEX Acceptance', '-c', 'user.email=acceptance@example.invalid', 'commit', '--quiet', '-m', 'Source-built author update'],
        ['git', 'archive', '--format=zip', '--output='.$upgradeArchive, 'HEAD']] as $command) {
        [$code, , $error] = authorProcess($command, $extension);
        authorCheck($code === 0, 'Updated Composer archive creation failed: '.$error);
    }
    $rootManifest = json_decode(file_get_contents($application.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $rootManifest['repositories'][1]['package'] = [
        $package('acceptance/summary', '1.0.1', $upgradeArchive)['package'],
        $package('acceptance/summary', '1.0.0', $archives['extension'])['package'],
    ];
    $switchVersion = static function (string $version) use (&$rootManifest, $application): void {
        $rootManifest['require']['acceptance/summary'] = $version;
        file_put_contents($application.'/composer.json', json_encode($rootManifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        [$code, $output, $error] = authorProcess(['composer', 'update', 'acceptance/summary', '--no-interaction', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress', '--no-ansi'], $application);
        authorCheck($code === 0, 'Installed Composer version switch failed: '.$error.$output);
    };
    $switchVersion('1.0.1');
    [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify.php'], $application, $environment);
    authorCheck($code === 0, 'Fresh upgraded application failed: '.$error.$output);
    $upgraded = authorInstalledResult($output, 'upgrade');
    authorCheck($upgraded['extensionVersion'] === '1.0.1' && $upgraded['copied'] > 0, 'Composer update did not activate a new source-built release.');
    $afterUpgrade = $publishedFiles();
    authorCheck(count($afterUpgrade) > count($beforeUpgrade) && array_diff_assoc($beforeUpgrade, $afterUpgrade) === [], 'Upgrade overwrote or removed an older immutable release.');
    authorCheck(file_get_contents($application.'/saved-family-schema.json') === $schemaBeforeUpgrade, 'Upgrade changed stored family configuration.');
    $upgradedBrowser = $browserAcceptance();
    $switchVersion('1.0.0');
    [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify.php', 'reuse'], $application, $environment);
    authorCheck($code === 0, 'Fresh rolled-back application failed: '.$error.$output);
    $rolledBack = authorInstalledResult($output, 'rollback');
    authorCheck($rolledBack['extensionVersion'] === '1.0.0' && $rolledBack['copied'] === 0 && $publishedFiles() === $afterUpgrade,
        'Rollback did not reuse its sealed release or damaged publication history.');
    unset($rootManifest['require']['acceptance/summary']);
    file_put_contents($application.'/composer.json', json_encode($rootManifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    [$code, $output, $error] = authorProcess(['composer', 'update', 'acceptance/summary', '--no-interaction', '--no-scripts', '--no-plugins', '--no-progress', '--no-ansi'], $application);
    authorCheck($code === 0, 'Composer removal failed: '.$error.$output);
    file_put_contents($application.'/verify-removal.php', <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__.'/vendor/autoload.php';
if (Composer\InstalledVersions::isInstalled('acceptance/summary') || class_exists(\Acceptance\Summary\Forms\SummaryField::class)) throw new RuntimeException('Uninstalled author package is still autoloaded.');
$schema = json_decode(file_get_contents(__DIR__.'/saved-family-schema.json'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($schema) || !str_contains(json_encode($schema), 'acceptance-summary-family')) throw new RuntimeException('Uninstalled family data disappeared.');
$snapshot = \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyValueSnapshotCodec::decode(file_get_contents(__DIR__.'/saved-family-value.json'));
if ($snapshot->value !== ['text' => 'Safe'] || $snapshot->definition->valueVersion !== 1) throw new RuntimeException('Uninstalled family value or its version changed.');
$registry = \Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry::builtIn();
$codec = new \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaCodec(new \Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilySchemaRenderer($registry));
try { $codec->import(file_get_contents(__DIR__.'/saved-family-schema.json')); throw new RuntimeException('Unknown installed family was silently accepted.'); }
catch (InvalidArgumentException|LogicException) {}
echo json_encode(['removed' => true, 'savedSchemaPreserved' => true, 'savedValuePreserved' => true], JSON_THROW_ON_ERROR);
PHP);
    [$code, $output, $error] = authorProcess([PHP_BINARY, 'verify-removal.php'], $application, $environment);
    authorCheck($code === 0, 'Fresh uninstalled application failed: '.$error.$output);
    authorCheck($publishedFiles() === $afterUpgrade && file_get_contents($application.'/saved-family-schema.json') === $schemaBeforeUpgrade
        && file_get_contents($application.'/saved-family-value.json') === $valueBeforeUpgrade,
        'Uninstall implicitly pruned assets or erased unavailable family data.');

    return [...$result, 'cacheRestartMatrix' => $cacheMatrix, 'upgradeRollbackRemoval' => true, 'installedBuildTools' => true,
        'installedBrowsers' => $initialBrowser === null ? [] : [$initialBrowser, $upgradedBrowser],
        'immutableArtifactsPreserved' => count($afterUpgrade), 'groups' => $result['groups'] + 4];
}

$generator = new ComponentPackageGenerator;
$blueprint = new ComponentPackageBlueprint('acceptance/summary', 'acceptance-summary', 'Acceptance\\Summary', 'Summary');
$root = sys_get_temp_dir().'/fff-author-'.bin2hex(random_bytes(8));
mkdir($root, 0700);
$proofs = [];
try {
    foreach ([['../evil/name', 'abc', 'Valid\\Name', 'Name'], ['a/b', 'a', 'Class\\Name', 'Name'],
        ['a/b', 'a', 'Valid\\Name', 'Match'], ['a/b', 'a', 'Valid\\Name', "Name\ncode"],
        ['a/b', str_repeat('a', 49), 'Valid\\Name', 'Name'], ['a/b', 'a', 'Valid\\Name;eval', 'Name'], ['con/rating', 'a', 'Valid\\Name', 'Name']] as $arguments) {
        authorReject(fn () => new ComponentPackageBlueprint(...$arguments));
    }
    $plan = $generator->plan($blueprint);
    authorCheck(count($plan) === 15 && $plan === $generator->plan($blueprint), 'Source plan is not complete/deterministic');
    foreach ($plan as $source) {
        authorCheck(preg_match('/\{\{[A-Z_]+\}\}/', $source) === 0, 'Unresolved starter template placeholder');
    }
    authorCheck(! file_exists($root.'/dry'), 'Plan wrote files');
    $exit = Artisan::call('make:flex-component-package', ['package' => 'acceptance/summary', 'path' => $root.'/dry', '--dry-run' => true, '--json' => true]);
    authorCheck($exit === 0 && ! file_exists($root.'/dry') && count(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['files']) === 15, 'Dry-run command wrote files or has invalid output');
    $proofs[] = 'bounded identifiers, deterministic 15-file plan and command dry-run without filesystem writes';

    $packageRoot = $root.'/package';
    authorCheck($generator->generate($blueprint, $packageRoot) === array_keys($plan), 'Incomplete generated package');
    $composer = json_decode(file_get_contents($packageRoot.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    authorCheck($composer['require']['janczakb/filament-flex-fields'] === '^4.0', 'Generated package does not target FLEX v4');
    authorCheck($composer['autoload']['psr-4']['Acceptance\\Summary\\'] === 'src/', 'Broken PSR-4 namespace');
    authorCheck($composer['extra']['laravel']['providers'] === ['Acceptance\\Summary\\SummaryServiceProvider'], 'Broken provider discovery');
    authorReject(fn () => $generator->generate($blueprint, $packageRoot));
    file_put_contents($root.'/sentinel', 'keep');
    symlink($root.'/sentinel', $root.'/link');
    authorReject(fn () => $generator->generate($blueprint, $root.'/link'));
    symlink($root.'/missing', $root.'/dangling');
    authorReject(fn () => $generator->generate($blueprint, $root.'/dangling'));
    authorReject(fn () => $generator->generate($blueprint, $root.'/absent/package'));
    authorCheck(file_get_contents($root.'/sentinel') === 'keep' && is_link($root.'/dangling'), 'Existing files were mutated');
    foreach (array_keys($plan) as $path) {
        if (str_ends_with($path, '.php') && ! str_ends_with($path, '.blade.php')) {
            [$code, , $error] = authorProcess([PHP_BINARY, '-l', $packageRoot.'/'.$path], $packageRoot);
            authorCheck($code === 0, 'Generated PHP does not parse: '.$path.' '.$error);
        }
    }
    $proofs[] = 'valid Composer metadata, PHP syntax, refusal of existing directories/files/dangling links; unrelated sentinel preserved';

    symlink(getcwd().'/node_modules', $packageRoot.'/node_modules');
    symlink(getcwd().'/vendor', $packageRoot.'/vendor');
    $environment = [...getenv(), 'FLEX_FIELDS_ROOT' => getcwd(), 'PHP_BINARY' => PHP_BINARY];
    [$code, $output, $error] = authorProcess(['node', 'build.mjs'], $packageRoot, $environment);
    authorCheck($code === 0, 'Generated SDK build failed: '.$error.$output);
    $manifestPath = $packageRoot.'/resources/asset-manifest.json';
    $manifest = (new AssetManifestCompiler)->load($manifestPath);
    authorCheck(count($manifest->artifacts) === 2 && count($manifest->entries) === 1 && count($manifest->styles) === 1, 'Unexpected duplicated starter assets');
    $originalManifest = file_get_contents($manifestPath);
    [$code, , $error] = authorProcess(['node', 'build.mjs', '--development'], $packageRoot, $environment);
    authorCheck($code === 0, 'Development author build failed: '.$error);
    $developmentManifest = (new AssetManifestCompiler)->load($manifestPath);
    authorCheck($developmentManifest->release !== $manifest->release
        && count($developmentManifest->artifacts) === count($manifest->artifacts), 'Development must preserve typed graph with its own content identity.');
    authorCheck(is_dir($packageRoot.'/resources/dist/'.$manifest->release), 'Development build removed the production release.');
    [$code, , $error] = authorProcess(['node', 'build.mjs'], $packageRoot, $environment);
    authorCheck($code === 0 && file_get_contents($manifestPath) === $originalManifest, 'Repeat build is not content stable: '.$error);
    $proofs[] = 'development/production share the public SDK and manifest contract; separate immutable releases and byte-identical production restoration';
    $entry = $packageRoot.'/resources/js/field.js';
    $source = file_get_contents($entry);
    file_put_contents($entry, 'export default broken(');
    [$code] = authorProcess(['node', 'build.mjs'], $packageRoot, $environment);
    authorCheck($code !== 0 && file_get_contents($manifestPath) === $originalManifest, 'Failed build replaced valid generation');
    authorCheck(glob($packageRoot.'/resources/.fff-build-*') === [], 'Failed build leaked staging directories');
    file_put_contents($entry, $source);
    $autoEnvironment = $environment;
    unset($autoEnvironment['FLEX_FIELDS_ROOT']);
    [$code, , $error] = authorProcess(['node', 'build.mjs'], $packageRoot, $autoEnvironment);
    authorCheck($code === 0 && file_get_contents($manifestPath) === $originalManifest, 'Composer autoload tool discovery fails: '.$error);
    file_put_contents($packageRoot.'/resources/js/optional.js', <<<'JS'
export const optional = true;
export async function capture() {
    const worker = new Worker(new URL('./capture-worker.js', import.meta.url), { type: 'module' });
    let timer;
    try {
        return await new Promise((resolve, reject) => {
            timer = setTimeout(() => reject(Error('Worker did not respond')), 10000);
            worker.onmessage = event => resolve(event.data);
            worker.onerror = () => reject(Error('Worker failed'));
            worker.postMessage('capture');
        });
    } finally { clearTimeout(timer); worker.terminate(); }
}
JS);
    file_put_contents($packageRoot.'/resources/js/capture-worker.js', <<<'JS'
import wasmUrl from './empty.wasm';
self.onmessage = async () => {
    const response = await fetch(new URL(wasmUrl, import.meta.url));
    if (!response.ok) throw Error('Missing worker binary');
    const bytes = await response.arrayBuffer();
    const module = await WebAssembly.compile(bytes);
    self.postMessage({ wasm: module instanceof WebAssembly.Module, bytes: bytes.byteLength });
};
JS);
    file_put_contents($packageRoot.'/resources/js/empty.wasm', pack('C*', 0, 97, 115, 109, 1, 0, 0, 0));
    $buildPath = $packageRoot.'/build.mjs';
    $build = str_replace("display: 'resources/css/display.css'", "display: 'resources/css/display.css', 'capture-worker': 'resources/js/capture-worker.js'", file_get_contents($buildPath), $workerEntryChanges);
    $build = str_replace('metafile: true, write: false,', "metafile: true, write: false, loader: { '.wasm': 'file' },", $build, $loaderChanges);
    $build = str_replace('types: {}, resources: {}', "types: { 'capture-worker.js': 'worker' }, resources: {}", $build, $workerTypeChanges);
    $build = str_replace('    const metadata = ', <<<'JS'
    const optionalOutput = Object.entries(result.metafile.outputs).find(([, value]) => value.entryPoint === 'resources/js/optional.js');
    if (!optionalOutput) throw Error('Lazy entry is missing from the build');
    declaration.resources[path.relative(outputRoot, path.resolve(root, optionalOutput[0]))] = ['capture-worker.js'];
    const metadata =
JS, $build, $edgeChanges);
    authorCheck($workerEntryChanges === 1 && $loaderChanges === 1 && $workerTypeChanges === 1 && $edgeChanges === 1, 'Author worker fixture did not declare its complete graph.');
    file_put_contents($buildPath, $build);
    // Faults belong to this independently built author fixture, not to core hooks.
    $instrumented = str_replace('    let scope', '    let scope, fixture, fixtureKey', $source);
    $instrumented = str_replace('        init() {', <<<'JS'
        init() {
            fixture = globalThis.__fffAuthorFixture;
            fixtureKey = this.$el.closest('.fff-acceptance-summary-field')?.id ?? this.$el.id;
            if (fixture) {
                fixture.inits[fixtureKey] = (fixture.inits[fixtureKey] ?? 0) + 1;
                if (fixture.fail.has(fixtureKey)) throw Error('Controlled author D initialization failure');
                fixture.successes[fixtureKey] = (fixture.successes[fixtureKey] ?? 0) + 1;
            }
JS, $instrumented);
    $instrumented = str_replace("{ source: 'input' }))", "{ source: 'input' }))\n            return fixture?.pending.get(fixtureKey)?.promise", $instrumented);
    $instrumented = str_replace('destroy() { scope?.dispose(); scope = undefined }',
        'destroy() { scope?.dispose(); scope = undefined; if (fixture) fixture.disposals[fixtureKey] = (fixture.disposals[fixtureKey] ?? 0) + 1 }', $instrumented);
    authorCheck(str_contains($instrumented, 'fixture?.pending.get(fixtureKey)?.promise'), 'Author lifecycle instrumentation did not reach the actual starter factory.');
    $evaluationCounter = "if (globalThis.__fffAuthorFixture) globalThis.__fffAuthorFixture.moduleEvaluations = (globalThis.__fffAuthorFixture.moduleEvaluations ?? 0) + 1;\n";
    file_put_contents($entry, $evaluationCounter.$instrumented.PHP_EOL.'export const optional = () => import("./optional.js");'.PHP_EOL);
    [$code, , $error] = authorProcess(['node', 'build.mjs'], $packageRoot, $environment);
    authorCheck($code === 0, 'Lazy extension build failed: '.$error);
    $manifest = (new AssetManifestCompiler)->load($manifestPath);
    authorCheck(count($manifest->artifacts) === 5, 'Dynamic import/worker/WASM graph not captured');
    $proofs[] = 'actual generated esbuild + public SDK + manifest compiler, stable repeat build, failed compile preserves generation and cleans staging';

    spl_autoload_register(static function (string $class) use ($packageRoot): void {
        if (str_starts_with($class, 'Acceptance\\Summary\\')) {
            require $packageRoot.'/src/'.str_replace('\\', '/', substr($class, strlen('Acceptance\\Summary\\'))).'.php';
        }
    });
    $registry = ComponentRegistry::builtIn();
    $registrar = new ComponentRegistrar($registry);
    (new SummaryServiceProvider($app))->boot($registrar);
    $registrar->completeCollection();
    $registry->freeze();
    $app->instance(ComponentRegistry::class, $registry);
    $app->register(InfolistsServiceProvider::class);
    authorCheck($registry->family('acceptance-summary-family')->surfaces['entry'] === 'acceptance-summary-entry', 'Family surfaces not registered');
    $explanation = new ComponentDependencyExplanation;
    $report = $explanation->explain($registry, 'acceptance-summary-field');
    authorCheck($report['styles']['acceptance-summary-display'] === 'acceptance-summary-display', 'Shared CSS owner missing');
    authorCheck(! in_array('flex-text-input', $report['runtimeComponents'], true), 'Child slot eagerly imports child JS');
    authorCheck(count($report['artifacts']) === 5, 'Package assets were omitted/duplicated');
    authorCheck(count(array_filter($report['artifacts'], static fn (array $artifact): bool => $artifact['eager'] === false)) === 3, 'Lazy module/worker/WASM incorrectly becomes eager');
    authorCheck($report['packages']['acceptance/summary']['phpAbi'] === 1 && $report['packages']['acceptance/summary']['version'] === '1.0.0', 'Semantic package metadata missing');
    $columnReport = $explanation->explain($registry, 'acceptance-summary-column');
    authorCheck(count($columnReport['artifacts']) === 1 && count($columnReport['runtimeComponents']) === 1, 'Read-only column imports field JS');
    authorCheck(! str_contains(json_encode($report), $root) && $report['scope'] === 'installation', 'Report leaks installation paths or misrepresents live state');
    authorReject(fn () => $explanation->explain($registry, 'missing-component'));
    authorReject(fn () => $explanation->explain($registry, 'acceptance-summary-field', ['missing-capability']));
    $before = $registry->revision();
    authorCheck($report === $explanation->explain($registry, 'acceptance-summary-field') && $before === $registry->revision(), 'Read-only explanation mutated registry');
    $proofs[] = 'generated typed family collects/compiles/freezes; CSS-only surfaces exclude interactive JS; deterministic value-free explanation';

    final class AuthorToolingFormHost extends Component implements HasForms, HasTable
    {
        use InteractsWithForms;
        use InteractsWithTable;

        public ?array $data = [];

        public function table(Table $table): Table
        {
            return $table->records(fn () => []);
        }

        public function form(Schema $schema): Schema
        {
            return $schema->statePath('data')->components([
                SummaryField::make('summary')->schema([
                    FlexTextInput::make('text')->required()->maxLength(8),
                ]),
            ]);
        }
    }
    $host = new AuthorToolingFormHost;
    $host->setId('author-tooling-proof');
    $host->form->fill(['summary' => ['text' => 'Safe']]);
    $html = $host->form->toHtml();
    authorCheck(str_contains($html, 'acceptance-summary-field') && str_contains($html, 'flex-text-input') && str_contains($html, 'summary.text'), 'Composed input did not render with its independent boundary/state');
    authorCheck($host->form->getState()['summary']['text'] === 'Safe', 'Valid child state cannot dehydrate');
    foreach ([['text' => str_repeat('a', 9)], ['text' => ''], ['text' => 'Safe', 'injected' => 'payload']] as $value) {
        $host->data['summary'] = $value;
        try {
            $host->form->getState();
            throw new RuntimeException('Invalid composed value dehydrated');
        } catch (ValidationException $error) {
            authorCheck(count($error->errors()) > 0, 'Validation failed without field errors');
        }
    }
    $schema = Schema::make($host)->components([
        SummaryEntry::make('summary')->state(['text' => '<img src=x onerror=evil>']),
    ]);
    $entryHtml = $schema->toHtml();
    authorCheck(str_contains($entryHtml, '&lt;img') && str_contains($entryHtml, 'acceptance-summary-entry'), 'Entry lacks escaping or independent ownership');
    $column = SummaryColumn::make('summary')->table(Table::make($host))->record([ArrayRecord::getKeyName() => 'row-one', 'summary' => ['text' => 'Safe']]);
    authorCheck($column->getDisplayValue() === 'Safe', 'Column formatter differs from entry');
    authorCheck(str_contains($column->toHtml(), 'acceptance-summary-column'), 'Column lacks independent asset ownership');
    $proofs[] = 'actual generated form/entry HTML, shared FlexTextInput configuration, valid state, required/length/unknown-key rejection and escaped entry';

    $capabilityRegistry = new ComponentRegistry;
    $capabilityRegistry->registerBatch([
        new ComponentDefinition('explain-root', ComponentKind::Field, stylesFrom: ['explain-css'], capabilities: ['editor' => ['explain-editor']]),
        new ComponentDefinition('explain-css', ComponentKind::Primitive, styles: ['explain-shared'], ssrScripts: ['explain-first-paint']),
        new ComponentDefinition('explain-editor', ComponentKind::Field, uses: ['explain-leaf']),
        new ComponentDefinition('explain-leaf', ComponentKind::Primitive),
    ]);
    $off = $explanation->explain($capabilityRegistry, 'explain-root');
    $on = $explanation->explain($capabilityRegistry, 'explain-root', ['editor']);
    authorCheck($off['blockingSsrScripts'] === ['explain-first-paint'], 'Blocking SSR dependency omitted');
    authorCheck(! isset($off['definitions']['explain-editor']) && isset($on['definitions']['explain-editor']), 'Optional capability became global');
    authorCheck(array_filter($on['edges'], static fn (array $edge): bool => $edge['reason'] === 'capability:editor') !== [], 'Capability reason omitted');
    authorCheck(Artisan::call('fff:assets:explain', ['component' => 'acceptance-summary-field', '--json' => true]) === 0, 'Explain command not registered');
    authorCheck(json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['scope'] === 'installation', 'Explain command not machine readable');
    $proofs[] = 'optional capability/uses reasons, independent inactive child declaration and command integration';

    $registrations = new ComponentRegistrationExplanation;
    $publicMetadata = $registrations->explain($registry, 'acceptance-summary-field');
    $localMetadata = $registrations->explain($registry, 'acceptance-summary-field', locations: true);
    authorCheck($publicMetadata['originKnown'] && $publicMetadata['location'] === null
        && $localMetadata['location']['file'] === realpath($packageRoot.'/src/SummaryServiceProvider.php')
        && $localMetadata['location']['line'] > 1, 'Generated provider does not retain explicit source provenance');
    authorCheck(! str_contains(json_encode($publicMetadata), $root)
        && ! str_contains(json_encode($explanation->explain($registry, 'acceptance-summary-field')), $root), 'Portable diagnostics leak source paths');
    authorCheck($registrations->explain($registry, 'flex-text-input', true)['location']['file'] === realpath(getcwd().'/resources/components/flex-text-input.php'), 'Built-in origin was fabricated');
    authorCheck(Artisan::call('fff:assets:explain', ['component' => 'acceptance-summary-field', '--json' => true, '--locations' => true]) === 0
        && json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['definitions']['acceptance-summary-field']['registration']['location'] === $localMetadata['location'], 'CLI location opt-in does not work');
    authorCheck(Artisan::call('fff:assets:explain', ['component' => 'acceptance-summary-field', '--json' => true]) === 0
        && ! str_contains(Artisan::output(), $root), 'Default CLI exposes source paths');
    authorCheck(Artisan::call('fff:assets:doctor', ['--definitions' => true, '--json' => true]) === 0, 'Definition diagnostics broke sealed-build validation');
    $doctor = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    authorCheck($doctor['registrations']['originsKnown'] > 0 && $doctor['registrations']['definitions']['acceptance-summary-field']['location'] === null
        && ! str_contains(Artisan::output(), $root), 'Doctor loses source metadata or exposes paths without opt-in');
    authorCheck(Artisan::call('fff:assets:doctor', ['--definitions' => true, '--locations' => true, '--json' => true]) === 0
        && json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['registrations']['definitions']['acceptance-summary-field']['location'] === $localMetadata['location'], 'Doctor source location opt-in does not work');
    authorCheck(Artisan::call('fff:assets:doctor', ['--locations' => true]) === 1, 'Doctor accepts ambiguous source-path exposure');
    $proofs[] = 'generated and built-in source provenance retained at boot, CLI locations explicit, portable/default reports contain no local paths';

    $metadataRegistry = new ComponentRegistry;
    $old = new ComponentDefinition('metadata-old', ComponentKind::Primitive);
    $next = new ComponentDefinition('metadata-new', ComponentKind::Primitive);
    $notice = new ComponentDeprecation('metadata-new', '2027-02-28', 'Use the new component after migrating stored schemas.');
    $metadata = new RegistrationMetadata(new DefinitionOrigin(__FILE__, __LINE__), $notice);
    $metadataRegistry->registerBatch([$old, $next], metadata: ['metadata-old' => $metadata]);
    authorCheck($registrations->explain($metadataRegistry, 'metadata-old')['deprecation'] === $notice->export()
        && $registrations->explain($metadataRegistry, 'metadata-old')['replacementInstalled'] === true, 'Compatibility notice lost its deadline/replacement');
    authorCheck($registrations->explain($metadataRegistry, 'metadata-new')['originKnown'] === false, 'Legacy origin was invented');
    $before = $metadataRegistry->revision();
    $inventory = $registrations->inventory($metadataRegistry);
    authorCheck($inventory['originsKnown'] === 1 && $inventory['originsUnknown'] === 1
        && $inventory['deprecatedComponents'] === 1 && ! str_contains(json_encode($inventory), __FILE__), 'Definition inventory exposes undeclared paths or loses notice counts');
    foreach ([['metadata-old' => $metadata], ['metadata-added' => 'not typed'],
        ['metadata-added' => new RegistrationMetadata(deprecation: new ComponentDeprecation('metadata-added', '2027-02-28', 'Invalid self replacement.'))]] as $invalid) {
        authorReject(fn () => $metadataRegistry->registerBatch([new ComponentDefinition('metadata-added', ComponentKind::Primitive)], metadata: $invalid));
        authorCheck($metadataRegistry->revision() === $before && ! $metadataRegistry->has('metadata-added')
            && $metadataRegistry->registrationMetadata('metadata-old') === $metadata, 'Invalid metadata partially committed');
    }
    $prepared = $metadataRegistry->prepareBatch([new ComponentDefinition('metadata-added', ComponentKind::Primitive)], metadata: ['metadata-added' => $metadata]);
    $metadataRegistry->register(new ComponentDefinition('metadata-concurrent', ComponentKind::Primitive));
    authorReject(fn () => $metadataRegistry->commit($prepared));
    authorCheck(! $metadataRegistry->has('metadata-added') && $metadataRegistry->registrationMetadata('metadata-old') === $metadata, 'Stale snapshot replaces provenance');
    foreach (['2027-02-29', '2027-13-01', '2027-01-01x', '0000-01-01', "2027\0-01-01"] as $date) {
        authorReject(fn () => new ComponentDeprecation('metadata-new', $date, 'Compatibility notice.'));
    }
    authorReject(fn () => new ComponentDeprecation('metadata-new', '2027-01-01', '<script>evil</script>'));
    authorReject(fn () => new ComponentDeprecation('metadata-new', '2027-01-01', str_repeat('x', 513)));
    authorReject(fn () => new DefinitionOrigin(__FILE__, 0));
    authorReject(fn () => new DefinitionOrigin($root.'/absent-source.php'));
    $referencedMetadata = $metadata;
    $aliasMap = ['metadata-alias' => &$referencedMetadata];
    $aliasPrepared = $metadataRegistry->prepareBatch([new ComponentDefinition('metadata-alias', ComponentKind::Primitive)], metadata: $aliasMap);
    $referencedMetadata = new RegistrationMetadata;
    $metadataRegistry->commit($aliasPrepared);
    authorCheck($metadataRegistry->registrationMetadata('metadata-alias') === $metadata, 'Caller array references mutate prepared metadata');
    $metadataRegistry->freeze();
    authorReject(fn () => $metadataRegistry->register(new ComponentDefinition('metadata-late', ComponentKind::Primitive), $metadata));
    authorCheck($registrations->explain($metadataRegistry, 'metadata-old') === $registrations->explain($metadataRegistry, 'metadata-old'), 'Frozen notices are not deterministic');
    $proofs[] = 'typed provenance/deprecation batch is atomic, stale/frozen commits rejected, no invented legacy origins, bounded valid deadlines/plain-text messages';

    $impact = new ComponentRemovalImpact;
    $clean = $impact->inspect($registry, 'acceptance/summary');
    authorCheck($clean['readyWithinSuppliedScope'] && count($clean['ownedComponents']) === 4
        && $clean['affectedFamilies'] === ['acceptance-summary-family'] && $clean['inspectedSchemas'] === 0, 'Removal preflight loses package ownership');
    $storedDefinition = new FamilySchemaDefinition('acceptance-summary-family', 'stored_summary', 'Private label', config: ['secret' => 'Private value'], isActive: false);
    $blocked = $impact->inspect($registry, 'acceptance/summary', [$storedDefinition]);
    authorCheck(! $blocked['readyWithinSuppliedScope'] && $blocked['affectedSchemas'][0]['slug'] === 'stored_summary'
        && ! str_contains(json_encode($blocked), 'Private') && ! str_contains(json_encode($blocked), $root), 'Inactive stored schema silently discarded or private configuration leaked');
    authorReject(fn () => $impact->inspect($registry, 'missing/package'));
    authorReject(fn () => $impact->inspect($registry, 'acceptance/summary', [$storedDefinition, $storedDefinition]));
    authorReject(fn () => $impact->inspect($registry, 'acceptance/summary', array_fill(0, 257, $storedDefinition)));
    $before = $registry->revision();
    authorCheck($blocked === $impact->inspect($registry, 'acceptance/summary', [$storedDefinition]) && $registry->revision() === $before, 'Removal preflight mutates registry');
    authorCheck(Artisan::call('fff:components:removal', ['package' => 'acceptance/summary', '--json' => true]) === 0
        && json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['scope'] === 'installation-and-supplied-schemas', 'Removal command integration missing');
    $proofs[] = 'read-only removal impact preserves inactive stored schemas/versions, hides config/labels/paths, bounds inventory and exposes its explicit scope';

    $originalPackage = $registry->family('acceptance-summary-family')->package;
    $metadataAdmissionRegistry = ComponentRegistry::builtIn();
    $metadataAdmissionRegistrar = new ComponentRegistrar($metadataAdmissionRegistry);
    foreach ([['acceptance-summary-field' => null], ['unknown-metadata-id' => $metadata]] as $invalidMap) {
        authorReject(fn () => $metadataAdmissionRegistrar->collectPackage($originalPackage->assets,
            [$registry->definition('acceptance-summary-field')], $invalidMap));
    }
    authorReject(fn () => new ComponentRegistration($old, metadata: new RegistrationMetadata(
        deprecation: new ComponentDeprecation('metadata-old', '2027-02-28', 'Self replacement.'))));
    $metadataAdmissionRegistrar->completeCollection();
    authorCheck(! $metadataAdmissionRegistry->has('acceptance-summary-field'), 'Invalid map partially entered the pending collection');
    $impactRegistry = ComponentRegistry::builtIn();
    $packageDefinitions = array_values(array_filter($registry->all(), static fn (ComponentDefinition $definition): bool => str_starts_with($definition->id, 'acceptance-summary-')));
    $impactRegistry->registerBatch($packageDefinitions, array_fill_keys(array_map(static fn (ComponentDefinition $definition): string => $definition->id, $packageDefinitions), $originalPackage->assets));
    $impactRegistry->registerBatch([
        new ComponentDefinition('impact-style', ComponentKind::Primitive, stylesFrom: ['acceptance-summary-display']),
        new ComponentDefinition('impact-use', ComponentKind::Field, uses: ['impact-style']),
        new ComponentDefinition('impact-optional', ComponentKind::Field, capabilities: ['dormant' => ['impact-use']]),
        new ComponentDefinition('impact-child', ComponentKind::Field, children: ['summary' => new ChildSlot('summary', 'acceptance-summary-field', ComponentKind::Field, renderer: SummaryField::class)]),
    ]);
    $impactRegistry->freeze();
    $transitive = $impact->inspect($impactRegistry, 'acceptance/summary');
    authorCheck($transitive['dependentComponents'] === ['impact-child', 'impact-optional', 'impact-style', 'impact-use']
        && ! $transitive['readyWithinSuppliedScope'] && count($transitive['dependencyEdges']) === 4, 'Dormant, CSS, child or transitive removal dependency omitted');
    $app->instance(ComponentRegistry::class, $impactRegistry);
    try {
        authorCheck(Artisan::call('fff:components:removal', ['package' => 'acceptance/summary', '--json' => true]) === 1
            && json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)['dependentComponents'] === $transitive['dependentComponents'], 'Blocked removal command has success exit code');
    } finally {
        $app->instance(ComponentRegistry::class, $registry);
    }
    $proofs[] = 'removal covers CSS-only, composition, dormant capability and independent child edges transitively, with nonzero CLI status for declared blockers';
    $alternateManifest = new AssetManifest('alternate/summary', $manifest->artifacts, $manifest->entries, $manifest->styles, $manifest->helpers);
    $alternatePackage = new ComponentPackage(new AssetPackage($alternateManifest, $originalPackage->assets->sourceRoot), 'acceptance-summary', '2.0.0');
    $selection = new ComponentPackageSelection($alternatePackage, $originalPackage);
    $candidateDefinitions = static function (ComponentPackage $package) use ($registry): array {
        $family = new ComponentFamily($package, 'acceptance-summary-family', [
            'field' => 'acceptance-summary-field', 'column' => 'acceptance-summary-column', 'entry' => 'acceptance-summary-entry',
        ]);
        $definitions = [];
        foreach (['display', 'field', 'column', 'entry'] as $suffix) {
            $source = $registry->definition('acceptance-summary-'.$suffix);
            $definitions[] = new ComponentDefinition($source->id, $source->kind, $source->styles, $source->stylesFrom,
                $source->uses, $source->entry, $source->capabilities, $source->ssrScripts,
                surface: $source->surface === null ? null : new ComponentSurface($family, $source->kind, $source->surface->class),
                children: $source->children, childContract: $source->childContract);
        }

        return $definitions;
    };
    $originalDefinitions = $candidateDefinitions($originalPackage);
    $alternateDefinitions = $candidateDefinitions($alternatePackage);
    $selectedReport = null;
    foreach ([false, true] as $reverse) {
        $selectedRegistry = ComponentRegistry::builtIn();
        $selectedRegistrar = new ComponentRegistrar($selectedRegistry);
        $candidates = [[$originalPackage, $originalDefinitions], [$alternatePackage, $alternateDefinitions]];
        if ($reverse) {
            $candidates = array_reverse($candidates);
        }
        foreach ($candidates as [$candidate, $definitions]) {
            $candidateMetadata = new RegistrationMetadata(origin: new DefinitionOrigin(__FILE__, $candidate === $alternatePackage ? 123 : 456));
            $selectedRegistrar->collectPackage($candidate->assets, $definitions, ['acceptance-summary-field' => $candidateMetadata]);
        }
        authorCheck($selectedRegistrar->appliedPackageSelections() === [], 'Selection reported success before the commit barrier');
        $selectedRegistrar->selectPackage($selection);
        $selectedRegistrar->completeCollection();
        $selectedRegistry->freeze();
        $selected = $explanation->explain($selectedRegistry, 'acceptance-summary-field');
        authorCheck(array_keys($selected['packages']) === ['alternate/summary'] && count($selected['artifacts']) === count($alternateManifest->artifacts), 'Losing package resources remain reachable');
        authorCheck($selectedRegistry->family('acceptance-summary-family')->package === $alternatePackage, 'Preferred semantic package was not selected');
        authorCheck($selectedRegistry->registrationMetadata('acceptance-summary-field')?->origin?->line === 123, 'Losing package provenance survived selection');
        authorCheck($selectedRegistrar->appliedPackageSelections() === [$selection->export()]
            && ! str_contains(json_encode($selectedRegistrar->appliedPackageSelections()), $root), 'Applied decision missing or leaks installation paths');
        authorCheck($selectedReport === null || $selectedReport === $selected, 'Provider order changes selected graph/resources');
        $selectedReport = $selected;
        authorReject(fn () => $selectedRegistrar->selectPackage($selection));
        authorReject(fn () => $selectedRegistrar->collectPackage($originalPackage->assets, $originalDefinitions));
    }
    $proofs[] = 'explicit exact package selection before freeze, identical A/B and B/A graph, one CSS owner and no losing lazy/assets';

    mkdir($root.'/selected-public');
    $sourceManifestBeforePublication = file_get_contents($manifestPath);
    $selectedPublisher = new ComponentAssetPublisher($selectedRegistry, new Filesystem);
    $published = $selectedPublisher->publish($root.'/selected-public');
    $prefix = config('filament.assets_path') ?? '';
    authorCheck(is_string($prefix), 'Fixture asset prefix is not a string');
    $selectedDirectory = $root.'/selected-public/'.$alternatePackage->assets->publicDirectory($prefix);
    authorCheck($published['copied'] > 0 && $selectedPublisher->publish($root.'/selected-public')['copied'] === 0, 'Selected package publication is not idempotent');
    foreach ($alternateManifest->artifacts as $artifact) {
        authorCheck(hash_file('sha256', $selectedDirectory.'/'.$artifact->path) === $artifact->sha256, 'Selected publication has stale or incomplete resources');
    }
    authorCheck(! is_dir($root.'/selected-public/'.$originalPackage->assets->publicDirectory($prefix))
        && file_get_contents($manifestPath) === $sourceManifestBeforePublication, 'Unexpected replaced publication or source mutation');
    $proofs[] = 'actual shared publisher installs all selected static/lazy/CSS bytes exactly once, excludes the replaced release and leaves Composer source installations intact';

    foreach ([false, true] as $reverse) {
        $collisionRegistry = ComponentRegistry::builtIn();
        $collisionRegistrar = new ComponentRegistrar($collisionRegistry);
        $candidates = [[$originalPackage, $originalDefinitions], [$alternatePackage, $alternateDefinitions]];
        foreach ($reverse ? array_reverse($candidates) : $candidates as [$candidate, $definitions]) {
            $collisionRegistrar->collectPackage($candidate->assets, $definitions);
        }
        $before = $collisionRegistry->revision();
        authorReject(fn () => $collisionRegistrar->completeCollection());
        authorCheck($collisionRegistry->revision() === $before && ! $collisionRegistry->has('acceptance-summary-field'), 'Implicit collision partially changed registry');
    }
    authorReject(fn () => new ComponentPackageSelection($originalPackage, $originalPackage));
    $differentNamespace = new ComponentPackage($originalPackage->assets, 'acceptance', '1.0.0');
    authorReject(fn () => new ComponentPackageSelection($alternatePackage, $differentNamespace));
    $pendingRegistry = ComponentRegistry::builtIn();
    $pendingRegistrar = new ComponentRegistrar($pendingRegistry);
    $pendingRegistrar->selectPackage($selection);
    authorReject(fn () => $pendingRegistrar->selectPackage($selection));
    $pendingRegistrar->collectPackage($originalPackage->assets, $originalDefinitions);
    $before = $pendingRegistry->revision();
    authorReject(fn () => $pendingRegistrar->completeCollection());
    authorCheck($pendingRegistry->revision() === $before && $pendingRegistrar->appliedPackageSelections() === [], 'Missing selected package fabricated a fallback');
    $pendingRegistrar->collectPackage($alternatePackage->assets, $alternateDefinitions);
    $pendingRegistrar->completeCollection();
    authorCheck($pendingRegistry->family('acceptance-summary-family')->package === $alternatePackage, 'Failed preflight cannot retry with late missing package');
    $proofs[] = 'implicit collisions reject in both orders, no same-package upgrade or cross-namespace override, one explicit choice, absent candidate preserves pending state and can retry';

    $selectionFails = static function (array $preferredDefinitions, array $replacedDefinitions, ?ComponentPackageSelection $decision = null) use ($originalPackage, $alternatePackage, $selection): void {
        $candidateRegistry = ComponentRegistry::builtIn();
        $candidateRegistrar = new ComponentRegistrar($candidateRegistry);
        $candidateRegistrar->selectPackage($decision ?? $selection);
        $candidateRegistrar->collectPackage($originalPackage->assets, $replacedDefinitions);
        $candidateRegistrar->collectPackage($alternatePackage->assets, $preferredDefinitions);
        $before = $candidateRegistry->revision();
        authorReject(fn () => $candidateRegistrar->completeCollection());
        authorCheck($candidateRegistry->revision() === $before && $candidateRegistrar->appliedPackageSelections() === []
            && ! $candidateRegistry->has('acceptance-summary-field'), 'Invalid selection partially committed');
    };
    $selectionFails(array_slice($alternateDefinitions, 0, 3), $originalDefinitions);
    $selectionFails($alternateDefinitions, array_slice($originalDefinitions, 0, 3));
    $selectionFails($alternateDefinitions, [...$originalDefinitions, $originalDefinitions[0]]);
    $selectionFails($alternateDefinitions, [...$originalDefinitions, new ComponentDefinition('acceptance-summary-extra', ComponentKind::Primitive)]);
    $selectionFails([...$alternateDefinitions, new ComponentDefinition('flex-text-input', ComponentKind::Field)], $originalDefinitions);
    $changedKind = $alternateDefinitions;
    $changedKind[1] = new ComponentDefinition('acceptance-summary-field', ComponentKind::Primitive);
    $selectionFails($changedKind, $originalDefinitions);
    $stalePackage = new ComponentPackage($alternatePackage->assets, $alternatePackage->namespace, '2.1.0');
    $selectionFails($alternateDefinitions, $originalDefinitions, new ComponentPackageSelection($stalePackage, $originalPackage));
    mkdir($root.'/different-installation');
    $staleRoot = new ComponentPackage(new AssetPackage($alternateManifest, $root.'/different-installation'), $alternatePackage->namespace, '2.0.0');
    $selectionFails($alternateDefinitions, $originalDefinitions, new ComponentPackageSelection($staleRoot, $originalPackage));
    $proofs[] = 'no partial family/component removal, duplicate masking, semantic-version or source-installation drift; every rejected selection leaves live registry and applied decisions intact';

    $thirdManifest = new AssetManifest('third/summary', $manifest->artifacts, $manifest->entries, $manifest->styles, $manifest->helpers);
    $thirdPackage = new ComponentPackage(new AssetPackage($thirdManifest, $originalPackage->assets->sourceRoot), 'acceptance-summary', '1.0.0');
    $thirdRegistry = ComponentRegistry::builtIn();
    $thirdRegistrar = new ComponentRegistrar($thirdRegistry);
    $thirdRegistrar->selectPackage($selection);
    foreach ([[$originalPackage, $originalDefinitions], [$alternatePackage, $alternateDefinitions], [$thirdPackage, $candidateDefinitions($thirdPackage)]] as [$candidate, $definitions]) {
        $thirdRegistrar->collectPackage($candidate->assets, $definitions);
    }
    $before = $thirdRegistry->revision();
    authorReject(fn () => $thirdRegistrar->completeCollection());
    authorCheck($thirdRegistry->revision() === $before && $thirdRegistrar->appliedPackageSelections() === [], 'Selected namespace silently discards an undeclared third package');
    $proofs[] = 'selection is an exact two-package decision, never a namespace wildcard or implicit suppression of another conflicting author';

    $directRegistry = ComponentRegistry::builtIn();
    $directRegistrar = new ComponentRegistrar($directRegistry);
    $directRegistrar->registerFamily(array_map(static fn (ComponentDefinition $definition): ComponentRegistration => new ComponentRegistration($definition, assetPackage: $originalPackage->assets), $originalDefinitions));
    $directRegistrar->selectPackage($selection);
    $directRegistrar->collectPackage($alternatePackage->assets, $alternateDefinitions);
    $before = $directRegistry->revision();
    authorReject(fn () => $directRegistrar->completeCollection());
    authorCheck($directRegistry->revision() === $before && $directRegistry->family('acceptance-summary-family')->package === $originalPackage, 'Selection mutated an already committed graph');
    $boundedCollector = new ComponentRegistrationCollector;
    $member = new ComponentRegistration(new ComponentDefinition('bounded-pending', ComponentKind::Primitive));
    $boundedCollector->collect(array_fill(0, ComponentRegistrationCollector::MAX_MEMBERS, $member));
    authorReject(fn () => $boundedCollector->collect([$member]));
    authorCheck(count($boundedCollector->members()) === ComponentRegistrationCollector::MAX_MEMBERS, 'Collection overflow partially appended');
    $invalidCollector = new ComponentRegistrationCollector;
    authorReject(fn () => $invalidCollector->collect([$member, 'untyped']));
    authorCheck($invalidCollector->members() === [], 'Malformed collection partially appended');
    $invalidCollector->seal();
    $invalidCollector->seal();
    authorReject(fn () => $invalidCollector->selectPackage($selection));
    authorReject(fn () => $invalidCollector->collect([$member]));
    $proofs[] = 'selection cannot override already committed/native graphs, pending collection has atomic typed admission and aggregate 8192-member limit, sealed collection rejects mutation';

    $distribution = $root.'/distribution';
    mkdir($distribution, 0700);
    $filesystem = new Filesystem;
    $filesystem->copy(getcwd().'/.gitattributes', $distribution.'/.gitattributes');
    $filesystem->copyDirectory(getcwd().'/stubs/component-package', $distribution.'/stubs/component-package');
    mkdir($distribution.'/scripts/assets', 0755, true);
    foreach (['compile-package-manifest.php', 'compile-esbuild-package.mjs', 'esbuild-package-graph.mjs', 'flex-sdk-plugin.mjs'] as $tool) {
        $filesystem->copy(getcwd().'/scripts/assets/'.$tool, $distribution.'/scripts/assets/'.$tool);
    }
    file_put_contents($distribution.'/scripts/assets/internal.js', 'private build tooling');
    file_put_contents($distribution.'/scripts/internal.php', 'private diagnostic');
    foreach ([['git', 'init', '--quiet'], ['git', 'add', '.'],
        ['git', '-c', 'user.name=FLEX Acceptance', '-c', 'user.email=acceptance@example.invalid', 'commit', '--quiet', '-m', 'Temporary distribution probe'],
        ['git', 'archive', '--format=tar', '--output='.$root.'/distribution.tar', 'HEAD']] as $command) {
        [$code, , $error] = authorProcess($command, $distribution);
        authorCheck($code === 0, 'Distribution acceptance failed: '.$error);
    }
    [$code, $archive] = authorProcess(['tar', '-tf', $root.'/distribution.tar'], $distribution);
    authorCheck($code === 0 && str_contains($archive, 'scripts/assets/flex-sdk-plugin.mjs') && str_contains($archive, 'scripts/assets/compile-package-manifest.php')
        && str_contains($archive, 'scripts/assets/esbuild-package-graph.mjs') && str_contains($archive, 'scripts/assets/compile-esbuild-package.mjs')
        && str_contains($archive, 'stubs/component-package/composer.json.stub') && ! str_contains($archive, 'internal.'), 'Required author tools are excluded or private tooling is included');
    $proofs[] = 'actual Git distribution attributes retain starter templates and all four public build tools while excluding internal scripts';

    // Exercise installed-vendor autoload resolution without installing the final Composer fixture.
    $vendorLayout = $root.'/installed/vendor/acceptance/flex';
    mkdir($vendorLayout.'/scripts/assets', 0755, true);
    file_put_contents(dirname($vendorLayout, 2).'/autoload.php', '<?php require '.var_export(getcwd().'/vendor/autoload.php', true).';');
    copy(getcwd().'/scripts/assets/compile-package-manifest.php', $vendorLayout.'/scripts/assets/compile-package-manifest.php');
    $specification = $manifest->export();
    unset($specification['release']);
    foreach ($specification['artifacts'] as &$artifact) {
        unset($artifact['sha256'], $artifact['bytes']);
    }
    unset($artifact);
    file_put_contents($root.'/specification.json', json_encode($specification, JSON_THROW_ON_ERROR));
    $noHostAutoload = [...getenv()];
    unset($noHostAutoload['FLEX_FIELDS_AUTOLOAD']);
    [$code, , $error] = authorProcess([PHP_BINARY, $vendorLayout.'/scripts/assets/compile-package-manifest.php', $root.'/specification.json',
        $packageRoot.'/resources/dist/'.$manifest->release, $root.'/installed-manifest.json'], $root, $noHostAutoload);
    authorCheck($code === 0 && (new AssetManifestCompiler)->load($root.'/installed-manifest.json')->release === $manifest->release, 'Installed vendor autoload resolution failed: '.$error);
    $noHostAutoload['FLEX_FIELDS_AUTOLOAD'] = $root.'/absent-autoload.php';
    [$code, , $error] = authorProcess([PHP_BINARY, $vendorLayout.'/scripts/assets/compile-package-manifest.php', $root.'/specification.json',
        $packageRoot.'/resources/dist/'.$manifest->release, $root.'/invalid-manifest.json'], $root, $noHostAutoload);
    authorCheck($code !== 0 && ! file_exists($root.'/invalid-manifest.json') && str_contains($error, 'autoload is unavailable'), 'Explicit missing autoload silently fell back');
    $proofs[] = 'installed-vendor compiler autoload layout works without booting a host; explicit missing autoload fails without output';

    $installed = in_array('--composer-install', $_SERVER['argv'] ?? [], true)
        ? authorComposerInstallation($root, $packageRoot, $filesystem) : null;
    if ($installed !== null) {
        $proofs[] = 'independent Composer ZIP dist installation and fresh autoload: two configured parents plus independent child, own validation file, field/column/entry and idempotent custom public-path publication';
    }
    echo json_encode(['groups' => count($proofs), 'proofs' => $proofs, 'testsModified' => false,
        'composerArchiveInstall' => $installed ?? 'not requested in this invocation', 'release' => $manifest->release], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    // Remove only this diagnostic's owned temporary directory, never source files.
    (new Filesystem)->deleteDirectory($root);
}
