<?php

declare(strict_types=1);

ob_start();
require __DIR__.'/shared-overlay-v4-contract.php';
ob_end_clean();

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FragmentPlanProjection;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RenderedAssetPlan;
use Illuminate\Support\Facades\Blade;

$assert = static function (bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$reject = static function (Closure $operation) use ($assert): void {
    try {
        $operation();
    } catch (InvalidArgumentException|LogicException) {
        return;
    }
    $assert(false, 'Invalid asset plan was accepted.');
};
$template = <<<'BLADE'
<div id="dictionary-owners">
@for ($index = 0; $index < $count; $index++)
    <section class="ordered-proof">
        @include('filament-flex-fields::partials.emit-assets', [
            'stylesheets' => [], 'chunks' => [],
            'externalStylesheets' => ['/z-first.css', '/a-last.css'],
            'consumerComponent' => 'select-field', 'livewireKey' => 'dictionary-'.$index,
        ])
        <div class="field-root"></div>
    </section>
@endfor
</div>
BLADE;
$dictionary = new AssetPlanDictionary;
$raw = Blade::render($template, ['count' => 1000, 'planDictionary' => $dictionary]);
$html = $dictionary->project($raw);
$inline = Blade::render($template, ['count' => 1000]);
$assert(substr_count($html, 'data-fff-asset-batch') === 1000, 'Independent owner boundaries were lost.');
$assert(substr_count($html, 'data-fff-plan-definition=') === 1, 'Identical plans were duplicated.');
$assert(substr_count($html, 'data-fff-plan-generation="1"') === 1000, 'Owners lack their independent generation.');
$assert(! str_contains($html, 'data-fff-stylesheets='), 'Referenced owners still carry inline URL lists.');
$assert($dictionary->project($html) === $html, 'Fragment projection is not idempotent.');
$assert($dictionary->project($raw) === $html, 'A discarded render consumed a fragment emission flag.');
$marker = '<span data-fff-asset-batch data-fff-plan-id="'.$dictionary->register(new RenderedAssetPlan('probe', ['/safe.css'], [])).'" data-fff-asset-consumer="probe"></span>';
$opaque = '<!--'.$marker.'--><script>const example = `'.$marker.'`;</script><style>/* '.$marker.' */</style><textarea>'.$marker.'</textarea>';
$inert = $dictionary->project($opaque.'<div data-note="x > y"><template x-if="open">'.$marker.'</template>'.$marker.'</div>');
$assert(str_starts_with($inert, $opaque), 'Fragment projection changed comments or raw-text content.');
$assert(str_contains($inert, '<div data-note="x > y"><template x-if="open">') && str_ends_with($inert, '</template></span></div>'), 'Dictionary was not appended inside the stable live fragment root.');
$assert(substr_count($inert, 'data-fff-plan-definition=') === 1, 'An inert first owner duplicated or hid the fragment dictionary.');
$publicInline = Blade::render('<div><x-filament-flex-fields::assets component="select-field" consumer="first" /><x-filament-flex-fields::assets component="select-field" consumer="second" /></div>');
$second = AssetPlanDictionary::render(static fn (AssetPlanDictionary $plans): string => Blade::render($template, ['count' => 1, 'planDictionary' => $plans]));
$assert(substr_count($second, 'data-fff-plan-definition=') === 1, 'Independent fragment omitted its dictionary.');
$assert(strlen($html) < strlen($inline), 'Dictionary increased repeated boundary transport.');
$plan = new RenderedAssetPlan('probe', ['/z-first.css', '/a-last.css'], ['/helper.js'], ['/helper.js' => 20], [
    '/helper.js' => ['package' => 'example/probe', 'component' => 'probe', 'export' => 'default', 'abi' => 1],
]);
$same = new RenderedAssetPlan('probe', ['/z-first.css', '/a-last.css', '/a-last.css'], ['/helper.js'], ['/helper.js' => 20], [
    '/helper.js' => ['abi' => 1, 'export' => 'default', 'component' => 'probe', 'package' => 'example/probe'],
]);
$assert($plan->id === $same->id, 'Canonical plan identity depends on map insertion order or duplicate resources.');
$assert($plan->id !== (new RenderedAssetPlan('probe', ['/a-last.css', '/z-first.css'], ['/helper.js']))->id, 'CSS ordering disappeared from plan identity.');
foreach ([
    static fn () => new RenderedAssetPlan('Invalid', ['/good.css'], []),
    static fn () => new RenderedAssetPlan('probe', ['javascript:bad'], []),
    static fn () => new RenderedAssetPlan('probe', ['//evil.test/file'], []),
    static fn () => new RenderedAssetPlan('probe', ['relative.css'], []),
    static fn () => new RenderedAssetPlan('probe', array_fill(0, 513, '/good.css'), []),
    static fn () => new RenderedAssetPlan('probe', ['/good.css'], [], ['/other.css' => 10]),
    static fn () => new RenderedAssetPlan('probe', ['/good.css'], [], ['/good.css' => -1]),
    static fn () => new RenderedAssetPlan('probe', [], [], [], ['/wrong.js' => ['package' => 'example/probe', 'component' => 'probe', 'export' => 'default', 'abi' => 1]]),
    static fn () => (new AssetPlanDictionary)->project('<span data-fff-plan-id="'.str_repeat('a', 64).'"></span>'),
] as $operation) {
    $reject($operation);
}
$escaped = new RenderedAssetPlan('probe', ['/safe.css?query=</template><script>alert(1)</script>'], []);
$escapeDictionary = new AssetPlanDictionary;
$escapeId = $escapeDictionary->register($escaped);
$escapeHtml = $escapeDictionary->project('<span data-fff-plan-id="'.$escapeId.'"></span>');
$assert(! str_contains($escapeHtml, '<script>'), 'Non-executable plan payload escaped its template.');
// Public composition takes the same explicit fragment dictionary, without a page-global flag.
$public = AssetPlanDictionary::render(static fn (AssetPlanDictionary $plans): string => Blade::render('<div><x-filament-flex-fields::assets component="select-field" consumer="public-first" :plan-dictionary="$plans" /><x-filament-flex-fields::assets component="select-field" consumer="public-second" :plan-dictionary="$plans" /></div>', ['plans' => $plans]));
$assert(substr_count($public, 'data-fff-plan-definition=') === 1 && substr_count($public, 'data-fff-plan-id=') === 2, 'Public composition duplicated its plan.');
$largeTemplate = <<<'BLADE'
<div>
@for ($index = 0; $index < 1000; $index++)
    <x-filament-flex-fields::assets component="select-field" :consumer="'public-'.$index" :plan-dictionary="$plans" />
@endfor
</div>
BLADE;
$publicLarge = AssetPlanDictionary::render(static fn (AssetPlanDictionary $plans): string => Blade::render($largeTemplate, ['plans' => $plans]));
$publicLargeInline = Blade::render($largeTemplate, ['plans' => null]);
$assert(substr_count($publicLarge, 'data-fff-plan-definition=') === 1 && substr_count($publicLarge, 'data-fff-asset-batch') === 1000, 'Native large composition lost its shared descriptor or independent boundaries.');
$reduction = 1 - strlen($publicLarge) / strlen($publicLargeInline);
$assert($reduction >= .70, 'Native 1000-owner plan transport misses the 70 percent reduction target.');
$morphRoot = '<section id="morph-root"><div><input id="native-edit" x-data="{ value: \'server\' }" x-model="value"></div><div id="flex-morph-owner">';
$morphPlan = new RenderedAssetPlan('probe', ['/additional.css'], []);
$morphPlans = [$plan->id => $plan, $morphPlan->id => $morphPlan];
$morphMarker = fn (string $id): string => '<span hidden data-fff-asset-batch data-fff-plan-id="'.$id.'" data-fff-plan-generation="1" data-fff-asset-consumer="probe" data-fff-asset-consumer-id="morph-'.$id.'"></span>';
$morph = [];
foreach ([[], [$plan->id], [$plan->id, $morphPlan->id], []] as $ids) {
    $morph[] = FragmentPlanProjection::project($morphRoot.implode('', array_map($morphMarker, $ids)).'</div></section>', $morphPlans);
}
echo json_encode([
    'morph' => $morph,
    'html' => $html, 'partial' => $second, 'plan' => $plan->json, 'id' => $plan->id,
    'publicDictionaryBytes' => strlen($public), 'publicInlineBytes' => strlen($publicInline),
    'publicLargeDictionaryBytes' => strlen($publicLarge), 'publicLargeInlineBytes' => strlen($publicLargeInline),
    'publicLargeReduction' => $reduction,
    'proofs' => 13, 'owners' => 1000, 'descriptors' => 1,
    'dictionaryBytes' => strlen($html), 'inlineBytes' => strlen($inline),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
