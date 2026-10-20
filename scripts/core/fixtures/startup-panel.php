<?php

declare(strict_types=1);

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Composer\InstalledVersions;
use Filament\Actions\Action;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament as FilamentFacade;
use Filament\FilamentServiceProvider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$case = $argv[1] ?? '';
$directory = realpath($argv[2] ?? '');
if (! in_array($case, ['publish', 'native', 'enabled-empty', 'one', 'twenty', 'hundred', 'dynamic', 'tabs'], true)
    || $directory === false || ! is_file($directory.'/.startup-fixture')) {
    throw new InvalidArgumentException('An isolated startup fixture directory and a known case are required.');
}
$providers = [
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
    InfolistsServiceProvider::class,
    TablesServiceProvider::class,
    NotificationsServiceProvider::class,
    FilamentServiceProvider::class,
    LivewireServiceProvider::class,
];
if ($case !== 'native') {
    $providers[] = FilamentFlexFieldsServiceProvider::class;
}
$started = hrtime(true);
$app = Application::create(null, null, ['extra' => ['providers' => $providers]]);
foreach (['public', 'views', 'sessions', 'files'] as $name) {
    if (! is_dir($directory.'/'.$name)) {
        mkdir($directory.'/'.$name, 0755, true);
    }
}
$app->usePublicPath($directory.'/public');
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('app.url', 'http://localhost');
$app['url']->forceRootUrl('http://localhost');
$app['config']->set('app.debug', false);
$app['config']->set('view.compiled', $directory.'/views');
$app['config']->set('session.driver', 'file');
$app['config']->set('session.files', $directory.'/sessions');
$app['config']->set('cache.default', 'array');
$app['config']->set('queue.default', 'sync');
$app['config']->set('filament-flex-fields.playground.enabled', false);
$app['view']->share('errors', new ViewErrorBag);
$app['view']->addLocation(__DIR__);
$bootMs = (hrtime(true) - $started) / 1e6;

if ($case === 'publish') {
    $status = Artisan::call('filament:assets', ['--no-interaction' => true]);
    if ($status !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    echo json_encode(['published' => true, 'php' => PHP_VERSION,
        'filament' => InstalledVersions::getPrettyVersion('filament/filament'),
        'livewire' => InstalledVersions::getPrettyVersion('livewire/livewire'),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}

class StartupCostPanel extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'startup-panel';

    public array $data = [];

    public bool $dynamicDemo = false;

    public string $scenario = '';

    public function mount(): void
    {
        $this->scenario = (string) request()->query('case');
        $this->dynamicDemo = $this->scenario === 'dynamic';
        $this->form->fill();
    }

    public function firstFlexAction(): Action
    {
        return Action::make('firstFlex')->label('Open first FLEX field')
            ->schema(fn (): array => [
                FlexTextInput::make('message')->label('Message')->required(),
                Action::make('nestedFlex')->label('Open child FLEX field')
                    ->schema([FlexTextInput::make('child_message')->label('Child message')->required()])
                    ->action(static fn (): null => null),
            ])
            ->action(static fn (): null => null);
    }

    public function form(Schema $schema): Schema
    {
        if (($this->scenario ?: request()->query('case')) === 'tabs') {
            return $schema->statePath('data')->components([Tabs::make('Startup tabs')->tabs([
                Tab::make('Basics')->schema([TextInput::make('title')->label('Title')]),
                Tab::make('FLEX details')->schema([FlexTextInput::make('tab_message')->label('Tab message')]),
            ])]);
        }
        $fields = [TextInput::make('title')->label('Title')];
        $count = match ($this->scenario ?: request()->query('case')) {
            'one' => 1, 'twenty' => 20, 'hundred' => 100, default => 0,
        };
        for ($index = 0; $index < $count; $index++) {
            $fields[] = FlexTextInput::make('flex_'.$index)->label('Field '.$index);
        }

        return $schema->statePath('data')->components($fields);
    }
}

$panel = Panel::make()->id('startup-cost')->default()->path('cost')->navigation(false)->pages([StartupCostPanel::class]);
if ($case !== 'native') {
    $panel->plugin(FilamentFlexFieldsPlugin::make());
}
FilamentFacade::registerPanel($panel);
FilamentFacade::setCurrentPanel($panel);
$panel->boot();
Livewire\Livewire::component('startup-cost-panel', StartupCostPanel::class);
Route::middleware('web')->get('/cost', StartupCostPanel::class)->name(StartupCostPanel::getRouteName($panel));
$app['router']->getRoutes()->refreshNameLookups();
$started = hrtime(true);
$transport = ($argv[3] ?? '') === 'request' ? json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR) : null;
$cookieFile = $directory.'/fixture-cookies.json';
$initialCookies = is_file($cookieFile) ? json_decode(file_get_contents($cookieFile), true, 32, JSON_THROW_ON_ERROR) : [];
$request = $transport === null ? Request::create('http://localhost/cost?case='.$case, 'GET', [], $initialCookies)
    : Request::create('http://localhost'.$transport['uri'], 'POST', [], $transport['cookies'], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_LIVEWIRE' => 'true', 'HTTP_ACCEPT' => 'application/json',
    ], $transport['body']);
$response = $app->make(Kernel::class)->handle($request);
if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('Startup panel returned HTTP '.$response->getStatusCode().': '.$response->getContent());
}
if ($transport !== null) {
    echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent()], JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
$cookieValues = $initialCookies;
foreach ($response->headers->getCookies() as $cookie) {
    $cookieValues[$cookie->getName()] = $cookie->getValue();
}
file_put_contents($cookieFile, json_encode($cookieValues, JSON_THROW_ON_ERROR));
file_put_contents($directory.'/'.$case.'.html', $response->getContent());
echo json_encode(['case' => $case, 'bootMs' => $bootMs, 'renderMs' => (hrtime(true) - $started) / 1e6,
    'peakPhpBytes' => memory_get_peak_usage(true), 'htmlBytes' => strlen($response->getContent()),
    'cookies' => array_map(static fn ($cookie): string => (string) $cookie, $response->headers->getCookies()),
], JSON_THROW_ON_ERROR).PHP_EOL;
