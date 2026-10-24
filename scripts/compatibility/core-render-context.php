<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCoreCss;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaLayout;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ArtifactKind;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetArtifact;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\AssetPackage;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RuntimeRenderPhase;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\SchemaRendererIdentity;
use Bjanczak\FilamentFlexFields\Support\Bootstrap\RenderHookRegistration;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistrar;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentPackage;
use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentSurface;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

function renderContextCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$app->register(SchemasServiceProvider::class);
$app->register(FormsServiceProvider::class);
Filament::swap(new FilamentManager);
$reset = static function () use ($app): RuntimeRenderContext {
    $app->forgetScopedInstances();

    return $app->make(RuntimeRenderContext::class);
};
$core = array_values(array_filter(FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]),
    static fn ($asset): bool => $asset->getId() === FlexFieldAssets::CORE_STYLESHEET_ID))[0];
renderContextCheck($core instanceof FlexFieldsCoreCss && ! $core->isLoadedOnRequest(), 'Core CSS lost native publication/rendering.');
renderContextCheck($core->getId() === FlexFieldAssets::CORE_STYLESHEET_ID, 'Core CSS identity changed.');
$inactive = Panel::make()->id('without-flex');
$active = Panel::make()->id('with-flex')->plugin(FilamentFlexFieldsPlugin::make());
$hooks = [...array_map(fn ($phase): string => $phase->hook(), RuntimeRenderPhase::cases()), PanelsRenderHook::STYLES_BEFORE];

foreach ([$inactive, null] as $panel) {
    Filament::setCurrentPanel($panel);
    $reset();
    foreach ($hooks as $hook) {
        renderContextCheck((string) FilamentView::renderHook($hook) === '', 'Unused surface emitted FLEX: '.$hook);
    }
    renderContextCheck((string) $core->getHtml() === '', 'Unused surface received core CSS.');
    renderContextCheck(trim(view('filament-flex-fields::partials.flex-fff-load-bootstrap')->render()) === '', 'Direct bootstrap bypassed activation.');
    renderContextCheck(trim(view('filament-flex-fields::partials.flex-field-asset-injector')->render()) === '', 'Direct injector bypassed activation.');
}
echo 'PASS: unused panels and unconfigured standalone emit no FLEX hooks, bootstrap, injector or core CSS.'.PHP_EOL;

Filament::setCurrentPanel($active);
$reset();
$head = (string) FilamentView::renderHook(PanelsRenderHook::HEAD_START);
$css = (string) $core->getHtml();
$before = (string) FilamentView::renderHook(PanelsRenderHook::SCRIPTS_BEFORE);
$after = (string) FilamentView::renderHook(PanelsRenderHook::SCRIPTS_AFTER);
renderContextCheck(str_contains($head, 'document.documentElement'), 'Active panel lost theme.');
renderContextCheck(str_contains($css, 'rel="stylesheet"'), 'Active panel lost core CSS.');
renderContextCheck(str_contains($before, 'flex-fff-load-bootstrap') && str_contains($after, 'flex-field-asset-injector'), 'Active panel lost startup scripts.');
renderContextCheck((string) $core->getHtml() === '' && (string) FilamentView::renderHook(PanelsRenderHook::HEAD_START) === '', 'Layout emissions duplicated.');
renderContextCheck(trim(view('filament-flex-fields::partials.flex-fff-load-bootstrap')->render()) === '', 'Repeated direct include duplicated bootstrap.');
renderContextCheck(trim(view('filament-flex-fields::partials.flex-field-asset-injector')->render()) === '', 'Repeated direct include duplicated injector.');
echo 'PASS: active native panel preserves publication identities and emits each core tag/layout phase once.'.PHP_EOL;
renderContextCheck(strpos($css, '<script>') > strpos($css, 'rel="stylesheet"'), 'Readiness helper must follow the blocking core stylesheet.');
renderContextCheck(substr_count($css, '// fff-first-paint:readiness:') === 1, 'Core CSS lost its one synchronous readiness owner.');
request()->attributes->set('fff.csp_nonce', 'fff-readiness-probe-nonce');
$reset();
$nonceCss = (string) $core->getHtml();
renderContextCheck(str_contains($nonceCss, '<script nonce="fff-readiness-probe-nonce">// fff-first-paint:readiness:'), 'Early readiness helper lost the application CSP nonce.');
request()->attributes->remove('fff.csp_nonce');
$reset();
echo 'PASS: one sealed readiness owner follows core CSS and carries the application-provided nonce.'.PHP_EOL;

foreach ([$inactive, $active, null] as $panel) {
    Filament::setCurrentPanel($panel);
    $reset()->enableStandalone();
    $app['config']->set('filament-flex-fields.enabled', false);
    renderContextCheck((string) $core->getHtml() === '', 'Global disable lost core CSS boundary.');
    foreach ($hooks as $hook) {
        renderContextCheck((string) FilamentView::renderHook($hook) === '', 'Global disable lost hook boundary.');
    }
}
$app['config']->set('filament-flex-fields.enabled', true);
Filament::setCurrentPanel($inactive);
$context = $reset();
$context->enableStandalone();
renderContextCheck(! $context->allows() && RenderHookRegistration::renderStandalone(RuntimeRenderPhase::ScriptsBefore) === '', 'Standalone enabled an unregistered panel.');
Filament::setCurrentPanel(null);
renderContextCheck($context->allows(), 'Panel decision was cached across scopes.');
echo 'PASS: global disable wins; standalone intent cannot bypass a panel without the plugin.'.PHP_EOL;

$context = $reset();
FilamentView::registerRenderHook(PanelsRenderHook::HEAD_START, static fn (): string => '<!-- another-plugin -->');
$html = Blade::render('<x-filament-flex-fields::runtime phase="head-start" /><x-filament-flex-fields::runtime phase="scripts-before" /><x-filament-flex-fields::runtime phase="scripts-after" />');
renderContextCheck(str_contains($html, 'document.documentElement') && str_contains($html, 'flex-fff-load-bootstrap') && str_contains($html, 'flex-field-asset-injector'), 'Explicit standalone API lost a phase.');
renderContextCheck(! str_contains($html, 'another-plugin'), 'Standalone duplicated foreign render hooks.');
renderContextCheck(str_contains((string) $core->getHtml(), 'rel="stylesheet"'), 'Explicit standalone API lost core CSS.');
$invalid = false;
try {
    Blade::render('<x-filament-flex-fields::runtime phase="arbitrary-path" />');
} catch (Throwable $exception) {
    $invalid = str_contains($exception->getMessage(), 'Unknown FLEX runtime layout phase');
}
renderContextCheck($invalid, 'Unknown phase was accepted.');
echo 'PASS: native Blade standalone composition is explicit, phase checked and never invokes foreign hooks.'.PHP_EOL;

request()->headers->set('X-Livewire', 'true');
$reset()->enableStandalone();
renderContextCheck((string) $core->getHtml() === '', 'Livewire update emitted document CSS.');
renderContextCheck(trim(view('filament-flex-fields::partials.flex-fff-load-bootstrap')->render()) === '', 'Livewire update emitted bootstrap.');
renderContextCheck(trim(view('filament-flex-fields::partials.flex-field-asset-injector')->render()) === '', 'Livewire update emitted injector.');
request()->headers->remove('X-Livewire');
renderContextCheck(str_contains((string) $core->getHtml(), 'rel="stylesheet"'), 'Skipped fragment consumed initial CSS emission.');
$newContext = $reset();
renderContextCheck($newContext !== $context && ! $newContext->allows(), 'Standalone/emission state survived a request scope.');
$newContext->enableStandalone();
renderContextCheck(str_contains((string) $core->getHtml(), 'rel="stylesheet"'), 'Next request inherited prior CSS emission.');
echo 'PASS: request reset isolates standalone intent and emission; Livewire fragments never consume initial-document tags.'.PHP_EOL;

class RuntimeContextExternalField extends TextInput
{
    protected string $view = 'runtime-context-probe::field';
}
class RuntimeContextExternalSubclass extends RuntimeContextExternalField {}

$host = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
};
$host->setId('runtime-context-probe');
$previousRegistry = $app->make(ComponentRegistry::class);
$registry = ComponentRegistry::builtIn();
$app->instance(ComponentRegistry::class, $registry);
$external = RuntimeContextExternalField::make('external');
$schema = Schema::make($host)->statePath('data')->components([$external]);
renderContextCheck(! SchemaRendererIdentity::isFlex($external), 'Unknown renderer was registered implicitly.');
$root = sys_get_temp_dir().'/fff-render-context-'.bin2hex(random_bytes(8));
$fs = new Filesystem;
$fs->ensureDirectoryExists($root);
try {
    $bytes = '.runtime-context-probe { color: inherit; }';
    file_put_contents($root.'/field.css', $bytes);
    $assets = new AssetPackage(new AssetManifest('example/runtime-context', [
        'style' => new AssetArtifact('field.css', ArtifactKind::Stylesheet, hash('sha256', $bytes), strlen($bytes)),
    ], styles: ['runtimeprobe-style' => 'style']), $root);
    $family = new ComponentFamily(new ComponentPackage($assets, 'runtimeprobe', '1.0.0'), 'runtimeprobe-family', ['field' => 'runtimeprobe-field']);
    $nativeFamily = new ComponentFamily($family->package, 'runtimeprobe-native-family', ['field' => 'runtimeprobe-native']);
    $registrar = new ComponentRegistrar($registry);
    $registrar->collectPackage($assets, [
        new ComponentDefinition('runtimeprobe-field', ComponentKind::Field,
            styles: ['runtimeprobe-style'], surface: new ComponentSurface($family, ComponentKind::Field, RuntimeContextExternalField::class)),
        new ComponentDefinition('runtimeprobe-native', ComponentKind::Field,
            stylesFrom: ['runtimeprobe-field'], surface: new ComponentSurface($nativeFamily, ComponentKind::Field, TextInput::class)),
    ]);
    $registrar->completeCollection();
    renderContextCheck(SchemaRendererIdentity::isFlex($external), 'Registry revision retained negative renderer cache.');
    $subclass = RuntimeContextExternalSubclass::make('subclass');
    $schema->components([$subclass]);
    renderContextCheck(SchemaRendererIdentity::isFlex($subclass), 'Registered renderer subclass was excluded.');
    $dormant = 0;
    $tabs = Tabs::make('External')->tabs([
        Tab::make('Live')->schema([$subclass]),
        Tab::make('Dormant')->schema(function () use (&$dormant): array {
            $dormant++;

            return [RuntimeContextExternalField::make('later')];
        }),
    ]);
    $schema->components([$tabs]);
    InitialSchemaLayout::prepare($schema);
    renderContextCheck(($tabs->getExtraAttributes()['data-fff-initial-layout'] ?? null) === 'native-tabs' && $dormant === 0, 'External metadata lost initial layout or materialized dormant schema.');
    $nativeAlias = TextInput::make('ordinary_native');
    $schema->components([$nativeAlias]);
    renderContextCheck(! SchemaRendererIdentity::isFlex($nativeAlias), 'Registering a native class claimed unrelated native fields.');
    echo 'PASS: external family metadata and subclasses prepare native layout without opening dormant child schemas; revision invalidates negative cache.'.PHP_EOL;

    $unrelated = ComponentRegistry::builtIn();
    $app->instance(ComponentRegistry::class, $unrelated);
    renderContextCheck(! SchemaRendererIdentity::isFlex($subclass), 'Another registry inherited renderer classes.');
    $native = TextInput::make('native');
    $nativeTabs = Tabs::make('Native')->tabs([Tab::make('Native')->schema([$native])]);
    $schema->components([$nativeTabs]);
    InitialSchemaLayout::prepare($schema);
    renderContextCheck(! isset($nativeTabs->getExtraAttributes()['data-fff-initial-layout']), 'Native-only schema was modified.');
    $marked = TextInput::make('marked')->extraAttributes(['data-fff-component' => 'explicit-probe']);
    $schema->components([$marked]);
    renderContextCheck(SchemaRendererIdentity::isFlex($marked), 'Explicit legacy/custom renderer marker stopped working.');
    $weak = WeakReference::create($registry);
    unset($registrar, $registry);
    gc_collect_cycles();
    renderContextCheck($weak->get() === null, 'Renderer index retained an obsolete registry.');
    echo 'PASS: metadata cache isolates registries, preserves explicit markers/native-only layout and releases obsolete registries.'.PHP_EOL;
} finally {
    $app->instance(ComponentRegistry::class, $previousRegistry);
    $fs->deleteDirectory($root);
}

echo 'PASS: core render context acceptance — 7 groups, actual installed Laravel/Filament/Blade and isolated package metadata; no host data writes.'.PHP_EOL;

if (in_array('--browser-fixtures', $argv, true)) {
    $deliveryProbe = in_array('--delivery-policy', $argv, true);
    if ($deliveryProbe) {
        $app['config']->set('filament-flex-fields.assets.delivery', ['allowed_origins' => [], 'integrity_from_manifest' => true, 'importmap_integrity' => true, 'require_integrity' => true, 'referrer_policy' => 'no-referrer']);
        request()->attributes->set('fff.csp_nonce', 'deliveryProbe123456');
    }
    $fixtureNonce = $deliveryProbe ? ' nonce="deliveryProbe123456"' : '';
    $fixtures = [];
    foreach (['inactive', 'active', 'standalone', 'disabled'] as $mode) {
        $reset();
        Filament::setCurrentPanel(match ($mode) {
            'inactive' => $inactive,
            'active', 'disabled' => $active,
            default => null,
        });
        $app['config']->set('filament-flex-fields.enabled', $mode !== 'disabled');
        $render = static fn (RuntimeRenderPhase $phase): string => $mode === 'standalone'
            ? RenderHookRegistration::renderStandalone($phase)
            : (string) FilamentView::renderHook($phase->hook());
        $head = $render(RuntimeRenderPhase::HeadStart);
        $body = in_array($mode, ['active', 'standalone'], true)
            ? Blade::render('<section id="fixture"><x-filament-flex-fields::assets component="flex-text-input" /><input value="FLEX" /></section>')
            : '<p>Unused integration</p>';
        $head .= (string) $core->getHtml().$render(RuntimeRenderPhase::HeadEnd).$render(RuntimeRenderPhase::StylesAfter);
        $tail = $render(RuntimeRenderPhase::BodyEnd).$render(RuntimeRenderPhase::ScriptsBefore)
            .'<script'.$fixtureNonce.' src="/fixture-alpine.js"></script>'.$render(RuntimeRenderPhase::ScriptsAfter);
        $fixtures[$mode] = '<!doctype html><html><head><meta charset="utf-8">'.$head.'</head><body x-data>'
            .'<script'.$fixtureNonce.'>'.($deliveryProbe ? 'window.livewireScriptConfig={nonce:"deliveryProbe123456"};' : '').'window.tippy={defaultProps:{},setDefaultProps(props){this.defaultProps=props}}</script>'.$body.$tail.'</body></html>';
    }
    $files = [];
    foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]),
        ...FilamentAsset::getScripts([FilamentFlexFieldsPlugin::PACKAGE_NAME], withCore: false),
        ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
        if (is_file($asset->getPath())) {
            $files['/'.$asset->getRelativePublicPath()] = $asset->getPath();
        }
    }
    $alpineModule = dirname((new ReflectionClass(Component::class))->getFileName(), 2).'/dist/livewire.esm.js';
    renderContextCheck(is_file($alpineModule), 'Installed Livewire ESM module is unavailable.');
    echo 'BROWSER_FIXTURES:'.base64_encode(json_encode([
        'html' => $fixtures,
        'files' => $files,
        'alpineModule' => $alpineModule,
    ], JSON_THROW_ON_ERROR)).PHP_EOL;
}
