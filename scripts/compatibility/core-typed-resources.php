<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RenderedAssetPlan;
use Illuminate\Support\Facades\Blade;

$styles = ['/theme?edition=1&signature=abc', '/misleading.js?type=css'];
$template = <<<'BLADE'
<section id="{{ $id }}" class="typed-proof">
    @include('filament-flex-fields::partials.emit-assets', [
        'stylesheets' => [], 'chunks' => [], 'externalStylesheets' => $styles,
        'consumerComponent' => 'select-field', 'livewireKey' => $id,
    ])
    <div class="field-root"></div>
</section>
BLADE;
$inline = Blade::render($template, ['id' => 'inline', 'styles' => $styles]);
$dictionary = AssetPlanDictionary::render(static fn (AssetPlanDictionary $plans): string => Blade::render($template, [
    'id' => 'dictionary', 'styles' => $styles, 'planDictionary' => $plans,
]));
$modules = ['/runtime.css?type=js&signature=xyz', '/runtime?version=2'];
$plan = new RenderedAssetPlan('probe', [], $modules);
echo json_encode([
    'inline' => $inline, 'dictionary' => $dictionary, 'plan' => $plan->json, 'id' => $plan->id,
    'styles' => $styles, 'modules' => $modules,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
