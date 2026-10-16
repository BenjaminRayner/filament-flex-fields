<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;

final class DictionaryTransportProbe extends Component
{
    public int $count = 2;

    public static bool $failNext = false;

    public const MARKUP = <<<'BLADE'
<div class="dictionary-transport">
@for ($i = 0; $i < $count; $i++)
    <section>
        @include('filament-flex-fields::partials.emit-assets', [
            'stylesheets' => [], 'chunks' => [],
            'externalStylesheets' => ['/z-first.css', '/a-last.css'],
            'consumerComponent' => 'select-field', 'livewireKey' => 'transport-'.$i,
        ])
        <div class="field-root"></div>
    </section>
@endfor
</div>
BLADE;

    public function render(): string
    {
        if (self::$failNext) {
            self::$failNext = false;

            return self::MARKUP.'@php throw new \\RuntimeException("Intentional discarded render."); @endphp';
        }

        return self::MARKUP;
    }

    public function partials(): void
    {
        // These strings are built before Filament's own render event.
        app(PartialsComponentHook::class)->renderPartial($this, fn (): array => [
            'first' => Blade::render(self::MARKUP, ['count' => 3]),
            'second' => Blade::render(self::MARKUP, ['count' => 4]),
        ]);
    }

    public function skipped(): void
    {
        $this->skipRender(Blade::render(self::MARKUP, ['count' => 5]));
    }

    public function customHtml(): string
    {
        $this->skipRender();

        return Blade::render(self::MARKUP, ['count' => 2]);
    }
}

final class DictionaryParentProbe extends Component
{
    public function render(): string
    {
        return '<div>@livewire("dictionary-transport", ["count" => 2], key("first")) @livewire("dictionary-transport", ["count" => 3], key("second"))</div>';
    }
}

final class DictionaryIslandProbe extends Component
{
    public function render(): string
    {
        return '<div>@island(name: "photos") <br>'.DictionaryTransportProbe::MARKUP.' @endisland</div>';
    }

    public int $count = 2;

    public function refreshIsland(): void
    {
        $this->count = 3;
        $this->triggerImplicitIslandRender(name: 'photos', mode: 'morph');
        $this->skipRender();
    }
}

Livewire::component('dictionary-transport', DictionaryTransportProbe::class);
Livewire::component('dictionary-parent', DictionaryParentProbe::class);
Livewire::component('dictionary-island', DictionaryIslandProbe::class);

$proofs = [];
$check = static function (bool $ok, string $message) use (&$proofs): void {
    if (! $ok) {
        throw new RuntimeException($message);
    }
    $proofs[] = $message;
};
$verify = static function (string $html, int $owners): bool {
    return substr_count($html, 'data-fff-plan-id=') === $owners
        && substr_count($html, 'data-fff-plan-definition=') === 1
        && ! str_contains($html, 'data-fff-stylesheets=');
};
$snapshotFrom = static function (string $html): array {
    if (! preg_match('/wire:snapshot="([^"]*)"/', $html, $match)) {
        throw new RuntimeException('Native Livewire snapshot is absent.');
    }

    return json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
};
$mounted = Livewire::mount('dictionary-transport', ['count' => 1000]);
$check($verify($mounted, 1000), 'Native mount: 1000 independent boundaries and one self-contained descriptor.');
$snapshot = $snapshotFrom($mounted);
request()->headers->set('X-Livewire', 'true');
$app->forgetScopedInstances();
[$snapshot, $effects] = Livewire::update($snapshot, ['count' => 6], []);
$check($verify($effects['html'], 6), 'Native update: fresh request catalog emits an independent complete dictionary.');
$full = $effects['html'];
$app->forgetScopedInstances();
[$snapshot, $effects] = Livewire::update($snapshot, [], [['method' => 'partials', 'params' => []]]);
$check(! isset($effects['html']) && $verify($effects['partials']['first'], 3) && $verify($effects['partials']['second'], 4), 'Native Filament partials: pre-rendered strings each carry their own dictionary. '.json_encode(['effects' => array_keys($effects), 'partials' => array_map(static fn (string $html): array => [substr_count($html, 'data-fff-plan-id='), substr_count($html, 'data-fff-plan-definition=')], $effects['partials'] ?? [])]));
$partials = $effects['partials'];
$app->forgetScopedInstances();
[$snapshot, $effects] = Livewire::update($snapshot, [], [['method' => 'skipped', 'params' => []]]);
$check(substr_count($effects['html'], 'data-fff-stylesheets=') === 5 && ! str_contains($effects['html'], 'data-fff-plan-id='), 'Native skipRender(string): preassembled action HTML remains self-contained without requiring projection.');
$app->forgetScopedInstances();
[$snapshot, $effects] = Livewire::update($snapshot, [], [['method' => 'customHtml', 'params' => []]]);
$check(substr_count($effects['returns'][0], 'data-fff-stylesheets=') === 2 && ! str_contains($effects['returns'][0], 'data-fff-plan-id='), 'Arbitrary action HTML stays inline and safe for custom transports and streaming.');
$parent = Livewire::mount('dictionary-parent');
$check($verify($parent, 5) && substr_count($parent, 'wire:snapshot=') === 3, 'Nested native mounts: parent deduplicates descriptors while preserving three component snapshots.');
DictionaryTransportProbe::$failNext = true;
try {
    Livewire::mount('dictionary-transport');
    throw new LogicException('Intentional failure did not occur.');
} catch (Throwable $exception) {
    if (! str_contains($exception->getMessage(), 'Intentional discarded render.')) {
        throw $exception;
    }
}
$check(! Livewire::current() instanceof Component && $verify(Livewire::mount('dictionary-transport'), 2), 'Discarded render: native component stack unwinds and retry still emits a dictionary.');
$plain = Blade::render(DictionaryTransportProbe::MARKUP, ['count' => 1]);
$check(str_contains($plain, 'data-fff-stylesheets=') && ! str_contains($plain, 'data-fff-plan-id='), 'Standalone Blade remains self-contained without a framework response adapter.');
$explicit = AssetPlanDictionary::render(static fn (AssetPlanDictionary $plans): string => Blade::render(DictionaryTransportProbe::MARKUP, ['count' => 2, 'planDictionary' => $plans]));
$check($verify((new AssetPlanDictionary)->project('<div>'.$explicit.'</div>'), 2), 'Explicit nested dictionary can be safely adopted by an automatic enclosing fragment.');
$islandMount = Livewire::mount('dictionary-island');
$islandSnapshot = $snapshotFrom($islandMount);
$app->forgetScopedInstances();
[$islandSnapshot, $effects] = Livewire::update($islandSnapshot, [], [['method' => 'refreshIsland', 'params' => []]]);
$island = $effects['islandFragments'][0];
$check($verify($island, 3) && str_starts_with(trim($island), '<!--') && str_ends_with(trim($island), '-->') && str_contains($island, '<br>'), 'Native island update: comment boundaries and void-first sibling layout survive dictionary projection.');
$firstCatalog = app(AssetPlanDictionary::class);
$app->forgetScopedInstances();
$check(app(AssetPlanDictionary::class) !== $firstCatalog, 'Container scope reset releases the request catalog.');

echo json_encode(['proofs' => $proofs, 'mounted' => $mounted, 'full' => $full, 'partials' => $partials, 'island' => $island], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
