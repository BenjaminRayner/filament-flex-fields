<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Composer\InstalledVersions;
use Illuminate\Database\Events\QueryExecuted;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Core benchmarks are CLI-only.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';

/** @param list<float> $samples
 * @return array{p50: float, p95: float, p99: float}
 */
function registryPercentiles(array $samples): array
{
    sort($samples, SORT_NUMERIC);
    $result = [];
    foreach (['p50' => 0.50, 'p95' => 0.95, 'p99' => 0.99] as $name => $percentile) {
        $result[$name] = round($samples[(int) ceil(count($samples) * $percentile) - 1], 6);
    }

    return $result;
}

$initialMemory = memory_get_usage();
$started = hrtime(true);
$registry = ComponentRegistry::builtIn();
$collectionMs = (hrtime(true) - $started) / 1e6;
$started = hrtime(true);
$graph = $registry->freeze();
$firstCompileMs = (hrtime(true) - $started) / 1e6;
$retainedMemory = memory_get_usage() - $initialMemory;
$definitions = $graph->definitions();
$ids = array_keys($definitions);
$fingerprint = [];
foreach ($ids as $id) {
    $definition = $definitions[$id];
    $fingerprint[$id] = [
        'kind' => $definition->kind->value,
        'entry' => $definition->entry,
        'capabilities' => $definition->capabilities,
        'ssr_scripts' => $definition->ssrScripts,
        'styles' => $graph->stylesFor($id),
        'runtime' => $graph->runtimeComponents($id),
    ];
}

$coldSamples = [];
for ($sample = 0; $sample < 50; $sample++) {
    $candidate = ComponentRegistry::builtIn();
    $started = hrtime(true);
    $candidate->freeze();
    $coldSamples[] = (hrtime(true) - $started) / 1e6;
    unset($candidate);
}

$warmSamples = [];
$readsPerSample = 20000;
$warmMemory = memory_get_usage();
for ($sample = 0; $sample < 30; $sample++) {
    $started = hrtime(true);
    for ($read = 0; $read < $readsPerSample; $read++) {
        $id = $ids[$read % count($ids)];
        $registry->stylesFor($id);
        $registry->runtimeComponents($id);
    }
    $warmSamples[] = (hrtime(true) - $started) / 1e6;
}
$warmGrowth = memory_get_usage() - $warmMemory;
if ($registry->compiledGraph() !== $graph || ! $registry->isFrozen()) {
    throw new RuntimeException('Warm reads replaced the immutable registry snapshot.');
}

// Native URL projection and Blade emission have a separate measured budget.
ob_start();
require dirname(__DIR__).'/compatibility/core-framework-host.php';
ob_end_clean();
$databaseQueries = 0;
app('events')->listen(QueryExecuted::class, static function () use (&$databaseQueries): void {
    $databaseQueries++;
});
$nativeSamples = [];
$renderSamples = [];
$nativeMemory = memory_get_usage();
$nativeWarmMemory = null;
for ($sample = 0; $sample < 50; $sample++) {
    app()->forgetScopedInstances();
    $started = hrtime(true);
    for ($read = 0; $read < 1000; $read++) {
        ComponentAssetPlan::for('flex-text-input');
    }
    $nativeSamples[] = (hrtime(true) - $started) / 1e6;
    $started = hrtime(true);
    for ($read = 0; $read < 100; $read++) {
        view('filament-flex-fields::partials.emit-assets', [
            'stylesheets' => [], 'chunks' => [], 'consumerComponent' => 'flex-text-input',
            'livewireKey' => 'benchmark-'.$read, 'externalStylesheets' => [],
        ])->render();
    }
    $renderSamples[] = (hrtime(true) - $started) / 1e6;
    if ($sample === 9) {
        $nativeWarmMemory = memory_get_usage();
    }
}
app()->forgetScopedInstances();
gc_collect_cycles();
$nativeGrowth = memory_get_usage() - $nativeMemory;
$nativeWarmGrowth = memory_get_usage() - $nativeWarmMemory;
$budgets = ['compile_p95_ms' => 10, 'warm_20000_p95_ms' => 50, 'native_1000_p95_ms' => 25, 'render_100_p95_ms' => 150, 'warm_retained_bytes' => 1048576];
if (registryPercentiles($coldSamples)['p95'] > $budgets['compile_p95_ms']
    || registryPercentiles($warmSamples)['p95'] > $budgets['warm_20000_p95_ms']
    || registryPercentiles(array_slice($nativeSamples, 1))['p95'] > $budgets['native_1000_p95_ms']
    || registryPercentiles(array_slice($renderSamples, 1))['p95'] > $budgets['render_100_p95_ms']
    || $nativeWarmGrowth > $budgets['warm_retained_bytes']) {
    throw new RuntimeException('Core PHP regression budget exceeded.');
}
if ($databaseQueries !== 0) {
    throw new RuntimeException('Asset planning/rendering introduced database queries.');
}

$sources = [];
foreach ([
    'src/Support/Components/ComponentRegistry.php',
    'src/Support/Components/Compilation/ComponentGraphCompiler.php',
    'src/Support/Components/Compilation/CompiledComponentGraph.php',
    'src/Support/Components/Compilation/ComponentOwnershipValidator.php',
    'src/Support/Components/Compilation/ComponentFamilyCompiler.php',
    'src/Support/Components/Compilation/PackageAssetCompiler.php',
    'src/Support/Components/Compilation/PackageAssetBudget.php',
] as $path) {
    $sources[$path] = hash_file('sha256', dirname(__DIR__, 2).'/'.$path);
}

echo json_encode([
    'profile' => 'local-cli-registry-v1',
    'scope' => 'compiled graph; separately measured native URL plans and Blade boundaries; excludes network and application schema DB work',
    'environment' => ['php' => PHP_VERSION, 'os' => PHP_OS, 'architecture' => php_uname('m'), 'opcache_cli' => ini_get('opcache.enable_cli')],
    'definitions' => count($definitions),
    'graph_sha256' => hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR)),
    'source_sha256' => $sources,
    'first_collection_ms' => round($collectionMs, 6),
    'first_compile_ms' => round($firstCompileMs, 6),
    'fresh_compile_ms' => ['samples' => count($coldSamples), ...registryPercentiles($coldSamples)],
    'warm_projection_batch_ms' => ['samples' => count($warmSamples), 'reads_per_sample' => $readsPerSample, 'two_projections_per_read' => true, ...registryPercentiles($warmSamples)],
    'retained_registry_bytes' => $retainedMemory,
    'warm_sample_growth_bytes' => $warmGrowth,
    'warm_sample_growth_scope' => 'includes the 30-sample timing array; not a registry leak measurement',
    'snapshot_reused' => true,
    'native_plan_1000_ms' => ['samples' => 50, ...registryPercentiles($nativeSamples)],
    'native_render_100_ms' => ['samples' => 50, ...registryPercentiles($renderSamples)],
    'asset_database_queries' => $databaseQueries,
    'native_scope_cold_retained_byte_delta' => $nativeGrowth,
    'native_scope_warm_retained_byte_delta' => $nativeWarmGrowth,
    'regression_budgets_v1' => $budgets,
    'native_profile' => ['filament' => InstalledVersions::getPrettyVersion('filament/filament'), 'laravel' => InstalledVersions::getPrettyVersion('laravel/framework')],
    'production_sla' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
