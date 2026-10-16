<?php

declare(strict_types=1);

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Console\AssetReleasesCommand;
use Bjanczak\FilamentFlexFields\Console\AssetsDoctorCommand;
use Bjanczak\FilamentFlexFields\Console\AssetsExplainCommand;
use Bjanczak\FilamentFlexFields\Console\ComponentRemovalImpactCommand;
use Bjanczak\FilamentFlexFields\Console\InstallWhisperRuntimeCommand;
use Bjanczak\FilamentFlexFields\Console\MakeFlexComponentPackageCommand;
use Bjanczak\FilamentFlexFields\Console\MakeFlexFieldSchemaMigrationCommand;
use Bjanczak\FilamentFlexFields\Console\PruneCaptureMediaCommand;
use Bjanczak\FilamentFlexFields\Console\PruneMediaConversionJournalsCommand;
use Bjanczak\FilamentFlexFields\Console\PruneMediaStagingCommand;
use Bjanczak\FilamentFlexFields\Console\PublishComponentAssetsCommand;
use Bjanczak\FilamentFlexFields\Console\WhisperRuntimeStatusCommand;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Events\ActivityDispatcher;
use Bjanczak\FilamentFlexFields\Support\Events\SelectSearched;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingOs;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingProviderContract;
use Bjanczak\FilamentFlexFields\Support\Geocoding\GeocodingState;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\VoiceNoteTranscriptionInterface;
use Bjanczak\FilamentFlexFields\Support\Theme\FlexFieldsTheme;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';

function coreCheck(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, 'FAIL: '.$message.PHP_EOL);
        exit(1);
    }
}

final class CoreProbeGeocoder implements GeocodingProviderContract
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function search(array $options): array
    {
        return ['features' => [], 'error' => null];
    }

    public function reverse(array $options): array
    {
        return ['feature' => null, 'error' => null];
    }
}

final class CoreProbeTranscriber implements VoiceNoteTranscriptionInterface
{
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function transcribe(string $disk, string $path, array $context = []): ?string
    {
        return 'probe';
    }
}

final class CoreProbeProvider extends FilamentFlexFieldsServiceProvider
{
    public static int $publications = 0;

    protected function publishStalePackageAssets(): void
    {
        self::$publications++;
    }
}

final class CoreProbeDefaultsProvider extends ServiceProvider
{
    public static int $events = 0;

    public static int $queries = 0;

    public function register(): void
    {
        $this->app->make(Dispatcher::class)->listen(QueryExecuted::class, static function (): void {
            self::$queries++;
        });
        config([
            'filament-flex-fields.v3.auto_upgrade' => true,
            'filament-flex-fields.geocoding.mapbox_provider' => CoreProbeGeocoder::class,
            'filament-flex-fields.geocoding.circuit_breaker.enabled' => false,
            'filament-flex-fields.media_capture.transcription' => CoreProbeTranscriber::class,
            'filament-flex-fields.media_capture.transcription_circuit_breaker.enabled' => false,
        ]);
    }

    public function boot(): void
    {
        MediaCaptureOs::registerVirusScanCallback(static fn (string $path): bool => $path === 'allowed');
        $this->app->make(Dispatcher::class)->listen(SelectSearched::class, static function (SelectSearched $event): void {
            self::$events++;
        });
    }
}

$storage = getcwd().'/build/core-bootstrap-probe-storage';
foreach (['app', 'framework/views', 'framework/sessions', 'framework/cache/data', 'logs'] as $directory) {
    if (! is_dir($storage.'/'.$directory)) {
        mkdir($storage.'/'.$directory, 0755, true);
    }
}
$marker = $storage.'/app/fff-v3-upgraded.json';
if (is_file($marker)) {
    unlink($marker);
}
$app = Application::create(null, static function ($app) use ($storage): void {
    $app->useStoragePath($storage);
}, ['extra' => ['dont-discover' => ['*'], 'providers' => [
    LivewireServiceProvider::class, BladeIconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class, SupportServiceProvider::class,
    ActionsServiceProvider::class, SchemasServiceProvider::class,
    FormsServiceProvider::class, CoreProbeProvider::class, CoreProbeDefaultsProvider::class,
]]]);
coreCheck(CoreProbeGeocoder::$constructed === 0 && CoreProbeTranscriber::$constructed === 0, 'Boot constructs unused optional providers');
coreCheck(CoreProbeProvider::$publications === 0 && ! is_file($marker), 'Ordinary boot writes publication or upgrade files');
coreCheck(CoreProbeDefaultsProvider::$queries === 0, 'Ordinary boot queries the database');
coreCheck(! $app->resolved(GeocodingState::class), 'Boot eagerly resolves geocoding state');
coreCheck(MediaCaptureOs::passesVirusScan('allowed') && ! MediaCaptureOs::passesVirusScan('other'), 'Boot scanner defaults are missing');

$theme = $app->make(FlexFieldsTheme::class)->setTheme(['primary' => 'tenant-a']);
app(ActivityDispatcher::class)->dispatch(new SelectSearched('country', hash('sha256', 'pol'), 3, 'options'));
coreCheck(CoreProbeDefaultsProvider::$events === 1, 'Laravel listener did not receive exactly one event');
GeocodingOs::provider();
MediaCaptureOs::transcriptionInterface();
coreCheck(CoreProbeGeocoder::$constructed === 1 && CoreProbeTranscriber::$constructed === 1, 'Optional providers were not lazily initialized');
$geocoder = GeocodingOs::provider();
$transcriber = MediaCaptureOs::transcriptionInterface();
MediaCaptureOs::registerVirusScanCallback(static fn (string $path): bool => false);
$firstActivity = $app->make(ActivityDispatcher::class);
$app->forgetScopedInstances();
coreCheck($app->make(FlexFieldsTheme::class) !== $theme && $app->make(FlexFieldsTheme::class)->theme() !== ['primary' => 'tenant-a'], 'Theme leaked across scopes');
coreCheck(MediaCaptureOs::passesVirusScan('allowed') && ! MediaCaptureOs::passesVirusScan('other'), 'Bootstrap defaults were lost or runtime override leaked');
$app->make(ActivityDispatcher::class)->dispatch(new SelectSearched('country', hash('sha256', 'pol'), 3, 'options'));
coreCheck($app->make(ActivityDispatcher::class) !== $firstActivity && CoreProbeDefaultsProvider::$events === 2, 'Activity delivery scope was retained, or Laravel boot listener disappeared');
coreCheck(GeocodingOs::provider() !== $geocoder && MediaCaptureOs::transcriptionInterface() !== $transcriber, 'Provider instances leaked across scopes');
for ($scope = 0; $scope < 32; $scope++) {
    $app->forgetScopedInstances();
    $app->make(ActivityDispatcher::class)->dispatch(new SelectSearched('country', hash('sha256', 'pol'), 3, 'options'));
}
coreCheck(CoreProbeDefaultsProvider::$events === 34, 'Listeners accumulate across worker scopes');

$artisan = new Illuminate\Console\Application($app, $app->make(Dispatcher::class), 'probe');
$artisan->setContainerCommandLoader();
$commandClasses = [
    AssetsDoctorCommand::class,
    AssetReleasesCommand::class,
    AssetsExplainCommand::class,
    ComponentRemovalImpactCommand::class,
    MakeFlexComponentPackageCommand::class,
    PublishComponentAssetsCommand::class,
    InstallWhisperRuntimeCommand::class,
    WhisperRuntimeStatusCommand::class,
    PruneCaptureMediaCommand::class,
    PruneMediaStagingCommand::class,
    PruneMediaConversionJournalsCommand::class,
    MakeFlexFieldSchemaMigrationCommand::class,
];
foreach ($commandClasses as $commandClass) {
    $attributes = (new ReflectionClass($commandClass))->getAttributes(AsCommand::class);
    coreCheck(count($attributes) === 1, 'Command does not declare a lazy name: '.$commandClass);
    $name = $attributes[0]->newInstance()->name;
    coreCheck(! $app->resolved($commandClass), 'Command discovery eagerly constructs '.$commandClass);
    coreCheck($artisan->has($name), 'Command discovery omits '.$commandClass);
    $command = $artisan->get($name);
    coreCheck($command->getName() === $name, 'Lazy command name differs from its signature: '.$commandClass);
}
foreach (['fff:v3:upgrade', 'fff:assets:export-registry', 'fff:icons:manifest'] as $removed) {
    coreCheck(! $artisan->has($removed), 'Obsolete command is still registered: '.$removed);
}
coreCheck(CoreProbeProvider::$publications === 0, 'Command discovery publishes assets');

$dispatcher = $app->make(Dispatcher::class);
$input = new ArrayInput([]);
$output = new NullOutput;
$dispatcher->dispatch(new CommandFinished('route:list', $input, $output, 0));
$dispatcher->dispatch(new CommandFinished('filament:assets', $input, $output, 1));
coreCheck(CoreProbeProvider::$publications === 0, 'Unrelated or failed console command publishes assets');
$dispatcher->dispatch(new CommandFinished('filament:assets', $input, $output, 0));
coreCheck(CoreProbeProvider::$publications === 1, 'Successful Filament publication does not publish component resources');
echo 'PASS: lazy optional providers, write-free ordinary boot, 34 isolated scopes, retained bootstrap defaults, native Laravel activity delivery, scoped theme, 12 lazy commands, obsolete commands absent, explicit successful publication.'.PHP_EOL;
