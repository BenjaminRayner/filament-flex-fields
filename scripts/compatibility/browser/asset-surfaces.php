<?php

declare(strict_types=1);

// Builds an isolated, disposable browser fixture. Does not boot the host app.
if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}
$root = dirname(__DIR__, 3);
chdir($root);
require 'vendor/autoload.php';
$destination = $argv[1] ?? sys_get_temp_dir().'/fff-asset-surfaces';
$port = (int) ($argv[2] ?? 8769);
@mkdir($destination.'/views', 0755, true);
@mkdir($destination.'/compiled', 0755, true);
$app = Application::create(null, null, ['extra' => ['providers' => [
    LivewireServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
]]]);
$app['config']->set('view.compiled', $destination.'/compiled');
$app['config']->set('app.url', 'http://127.0.0.1:'.$port);
$app['url']->forceRootUrl('http://127.0.0.1:'.$port);
$app['view']->share('errors', new ViewErrorBag);
$app['view']->addLocation($destination.'/views');

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$probe = function (string $component, string $key) use ($destination): string {
    $plan = ComponentAssetPlan::for($component);
    $marker = view('filament-flex-fields::partials.emit-assets', [
        'stylesheets' => $plan->stylesheets, 'chunks' => $plan->chunks,
        'consumerComponent' => $component, 'livewireKey' => $key,
    ])->render();
    $factory = 'probe'.str_replace('-', '', $component);
    file_put_contents($destination.'/'.$component.'.js', 'export default () => ({ init() { this.$el.dataset.probeInitialized = "true"; } })');

    return '<div class="probe-field fi-fo-field-wrp">'.$marker.'<div x-fff-load x-fff-load-src="/'.$component.'.js" x-data="'.$factory.'()"><strong>'.e($key).'</strong><p>'.e($component).'</p></div></div>';
};
file_put_contents($destination.'/views/probe.blade.php', '{!! $content !!}');
$host = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
};
$host->setId('asset-surfaces-browser');
$tabHtml = Schema::make($host)->components([
    Tabs::make('Scopes')->tabs([
        Tab::make('Input')->schema([View::make('probe')->viewData(['content' => $probe('flex-text-input', 'Tab Input')])]),
        Tab::make('Color')->schema([View::make('probe')->viewData(['content' => $probe('flex-color-picker', 'Tab Color')])]),
        Tab::make('Table')->schema([View::make('probe')->viewData(['content' => '<table><tbody id="probe-rows"><tr><td>'.FlexFieldStylesheetQueue::emitDomRootConsumerMarkup('status-chip-column', 'table.status').'<span>Row one</span></td></tr><tr><td>'.FlexFieldStylesheetQueue::emitDomRootConsumerMarkup('status-chip-column', 'table.status').'<span>Row two</span></td></tr></tbody></table><button @click="$el.previousElementSibling.querySelector(\'tr\')?.remove()">Remove row</button>'])]),
    ])->persistTabInQueryString(),
])->toHtml();
$modals = '';
$types = ['select-field', 'schedule-field', 'flex-color-picker', 'social-links-field', 'rating-field'];
for ($depth = 1; $depth <= 5; $depth++) {
    $content = $probe($types[$depth - 1], 'Modal '.$depth);
    if ($depth < 5) {
        $content .= '<button @click="$dispatch(\'close-modal-quietly\', { id: \'probe-'.$depth.'\' }); $dispatch(\'open-modal\', { id: \'probe-'.($depth + 1).'\' })">Open modal '.($depth + 1).'</button>';
    }
    $content .= '<button @click="$dispatch(\'close-modal\', { id: \'probe-'.$depth.'\' }); '.($depth > 1 ? '$dispatch(\'open-modal\', { id: \'probe-'.($depth - 1).'\' })' : '').'">Close modal '.$depth.'</button>';
    $modals .= Blade::render('<x-filament::modal :id="$id" :heading="$heading" :close-button="false" :close-by-clicking-away="false">{!! $content !!}</x-filament::modal>', ['id' => 'probe-'.$depth, 'heading' => 'Modal '.$depth, 'content' => $content]);
}
$html = '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>FLEX asset lifecycle audit</title><link rel="stylesheet" href="/dist/css/core.css"><style>[x-cloak]{display:none!important}body{font:14px system-ui;background:#fafafa;color:#222;margin:24px}main{max-width:900px;margin:auto}button{padding:8px 14px;border:1px solid #ddd;border-radius:10px;background:white;cursor:pointer;margin:6px}.probe-field{padding:16px;background:white;border:1px solid #eee;border-radius:16px;margin:12px 0}.fi-sc-tabs-tab:not(.fi-active){display:none}.fi-modal{position:fixed;inset:0;z-index:50}.fi-modal-close-overlay{position:absolute;inset:0;background:#0004}.fi-modal-window-ctn{position:absolute;inset:0;display:grid;place-items:center}.fi-modal-window{position:relative;background:white;border-radius:24px;padding:24px;min-width:400px}#audit-report{white-space:pre-wrap;font:12px monospace;background:#111;color:#a3e635;padding:16px;border-radius:16px}td{padding:12px}h2{margin:0}</style></head><body x-data><main><h1>FLEX · asset lifecycle audit</h1><p>Real Filament modal and tab renderers; isolated from application data.</p>'.$probe('select-field', 'Page Select').$tabHtml.'<button @click="$dispatch(\'open-modal\', { id: \'probe-1\' })">Open modal 1</button><pre id="audit-report" aria-live="polite">Preparing runtime…</pre></main>'.$modals.'<script data-fff-runtime-src="/dist/components/flex-core-runtime.js" src="/dist/core/flex-fff-load-bootstrap.js"></script><script data-fff-runtime-src="/dist/components/flex-core-runtime.js" src="/dist/core/flex-field-asset-injector.js"></script><script src="/fixture-bootstrap.js"></script></body></html>';
file_put_contents($destination.'/index.html', $html);
echo $destination.PHP_EOL;
