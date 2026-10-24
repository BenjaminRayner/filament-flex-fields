<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Icons\AdmittedIconIndexes;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogFingerprint;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndex;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogIndexBuilder;
use Bjanczak\FilamentFlexFields\Support\Icons\IconCatalogResolver;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use BladeUI\Icons\Factory;
use BladeUI\Icons\IconsManifest;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$app = Application::create(null, null, ['extra' => ['providers' => [
    TranslationServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
    FilamentServiceProvider::class,
    SupportServiceProvider::class,
    LivewireServiceProvider::class,
    FormsServiceProvider::class,
    SchemasServiceProvider::class,
    ActionsServiceProvider::class,
    TablesServiceProvider::class,
]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('cache.default', 'array');

$store = new class extends ArrayStore
{
    public int $reads = 0;

    public bool $fail = false;

    public function many(array $keys): array
    {
        $this->reads++;
        if ($this->fail) {
            throw new RuntimeException('Controlled unavailable cache.');
        }

        return parent::many($keys);
    }
};
Cache::swap(new Repository($store));
$resolver = app(IconCatalogResolver::class);
$catalog = ['fixture' => ['prefix' => 'fixture', 'label' => 'Fixture', 'icons' => ['fixture-a', 'fixture-b', 'fixture-c']]];
$check = static function (bool $ok, string $message): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
};
$first = $resolver->indexFor($catalog);
$second = $resolver->indexFor($catalog);
$check($first === $second && $store->reads === 1, 'Identical fields reread or reconstructed the same admitted index.');
$restricted = $resolver->indexFor($catalog, ['fixture-a']);
$check($restricted !== $first && ! $restricted->isAllowed('fixture-b'), 'Whitelist leaked between owners.');
$excluded = $resolver->indexFor($catalog, [], ['fixture-a']);
$check(! $excluded->isAllowed('fixture-a') && $first->isAllowed('fixture-a'), 'Exclusions changed a neighboring index.');
$limited = $resolver->indexFor($catalog, [], [], 1);
$check(count($limited->toArray()['entries']) === 1, 'Index limit was ignored.');
$app->setLocale('pl');
$check($resolver->indexFor($catalog) !== $first, 'Locale did not invalidate metadata identity.');
$app->setLocale('en');
$app->forgetScopedInstances();
$next = $resolver->indexFor($catalog);
$check($next !== $first, 'Admitted index survived the request/job scope reset.');
$namespace = IconCatalogFingerprint::namespace();
$key = 'fff.icon-index.'.$namespace.'.'.IconCatalogFingerprint::of([IconCatalogIndexBuilder::CACHE_REVISION, $catalog, [], [], null]);
Cache::put($key, 'corrupt external payload', 60);
$app->forgetScopedInstances();
$check($resolver->indexFor($catalog)->isAllowed('fixture-a'), 'Malformed shared cache was trusted or blocked source fallback.');
$app->forgetScopedInstances();
$store->fail = true;
$check($resolver->indexFor($catalog)->isAllowed('fixture-a'), 'Unavailable cache blocked source fallback.');
$store->fail = false;
$repository = new AdmittedIconIndexes;
$productions = 0;
$produce = static function () use (&$productions, $first): IconCatalogIndex {
    $productions++;

    return clone $first;
};
for ($i = 0; $i < 33; $i++) {
    $repository->remember('entry-'.$i, $produce);
}
$repository->remember('entry-32', $produce);
$check($productions === 34, 'Admission budget failed to bypass extra entries.');
$repository->remember('entry-0', $produce);
$check($productions === 34, 'Budget overflow displaced an already admitted index.');
$oversized = new IconCatalogIndex(
    [['name' => 'fixture-a', 'label' => str_repeat('x', 8_388_608), 'set' => 'fixture', 'nameLower' => 'fixture-a', 'labelLower' => 'a']],
    [], ['fixture-a' => true], [],
);
$byteRepository = new AdmittedIconIndexes;
$bigProductions = 0;
$produceBig = static function () use (&$bigProductions, $oversized): IconCatalogIndex {
    $bigProductions++;

    return $oversized;
};
$check($byteRepository->remember('big', $produceBig) === $oversized, 'Oversized valid metadata was rejected.');
$byteRepository->remember('big', $produceBig);
$check($bigProductions === 2, 'Byte admission limit retained oversized metadata.');
$failed = false;
try {
    $byteRepository->remember('retry', static fn () => throw new RuntimeException('Controlled source failure.'));
} catch (RuntimeException) {
    $failed = true;
}
$check($failed && $byteRepository->remember('retry', static fn () => $first) === $first, 'Producer failure poisoned a later retry.');
$custom = new class(app(Factory::class), app(IconsManifest::class)) extends IconCatalogResolver {};
$check($custom->indexFor($catalog) !== $custom->indexFor($catalog), 'Custom resolver inherited the new call-reuse policy.');
echo "PASS: one cache admission per policy; whitelist/exclusions/limits/locale isolation, scoped reset, corrupt/unavailable fallback, count/byte budgets, source retry and custom resolver fallback.\n";
