<?php

declare(strict_types=1);

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$case = $argv[1] ?? '';
if (! in_array($case, ['native', 'enabled-empty', 'one', 'twenty'], true)) {
    throw new InvalidArgumentException('Unknown fixture.');
}
$started = hrtime(true);
$providers = [BladeIconsServiceProvider::class, BladeHeroiconsServiceProvider::class, BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class, ActionsServiceProvider::class, SchemasServiceProvider::class, FormsServiceProvider::class,
    LivewireServiceProvider::class];
if ($case !== 'native') {
    $providers[] = FilamentFlexFieldsServiceProvider::class;
}
$app = Application::create(null, null, ['extra' => ['providers' => $providers]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$bootMs = (hrtime(true) - $started) / 1e6;
$host = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public array $data = [];
};
$host->setId('adoption-cost');
$fields = [TextInput::make('title')->label('Title')];
$count = match ($case) {
    'one' => 1, 'twenty' => 20, default => 0
};
for ($index = 0; $index < $count; $index++) {
    $fields[] = FlexTextInput::make('flex_'.$index)->label('Field '.$index);
}
$started = hrtime(true);
$schema = Schema::make($host)->statePath('data')->components($fields);
$schema->fill();
$html = $schema->toHtml();
$head = FilamentAsset::renderStyles().FilamentAsset::renderScripts();
$renderMs = (hrtime(true) - $started) / 1e6;
$resources = [];
if ($count > 0) {
    $plan = ComponentAssetPlan::for('flex-text-input');
    foreach ($plan->stylesheets as $id) {
        $resources['css:'.$id] = FlexFieldAssets::stylesheetBytes($id);
    }
    foreach ($plan->chunks as $id) {
        $resources['js:'.$id] = FlexFieldAssets::chunkBytes($id);
    }
}
echo json_encode([
    'case' => $case, 'flexFields' => $count, 'bootMs' => $bootMs, 'renderMs' => $renderMs,
    'peakPhpBytes' => memory_get_peak_usage(true), 'htmlBytes' => strlen($head.$html),
    'htmlGzipBytes' => strlen(gzencode($head.$html, 9)),
    'consumerBoundaries' => substr_count($html, 'data-fff-asset-consumer='),
    'declaredFieldResources' => $resources,
    'globalAssetMarkupBytes' => strlen($head),
], JSON_THROW_ON_ERROR).PHP_EOL;
