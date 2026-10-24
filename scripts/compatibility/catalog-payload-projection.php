<?php

declare(strict_types=1);

require __DIR__.'/core-framework-host.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\FragmentPlanProjection;
use Bjanczak\FilamentFlexFields\Support\Rendering\CatalogPayloadTransport;
use Bjanczak\FilamentFlexFields\Support\Rendering\ControlConfigurationDictionary;
use Bjanczak\FilamentFlexFields\Support\Rendering\RenderedControlConfiguration;
use Livewire\Component;
use Livewire\Mechanisms\HandleComponents\HandleComponents;

$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$transport = app(CatalogPayloadTransport::class);
$options = [['value' => 'PL', 'label' => '<Poland & "PL">', 'image' => '/flags/pl.svg', 'isDisabled' => false]];
$inline = (string) $transport->select('fixture', $options);
$check(str_contains($inline, 'data-fff-select-catalog="fixture"') && ! str_contains($inline, 'configuration-id'), 'Standalone render lost its complete inline fallback.');
$component = new class extends Component {};
HandleComponents::$componentStack[] = $component;
HandleComponents::$renderStack[] = $component;
try {
    $marker = (string) $transport->select('fixture', $options);
    $phone = $transport->phone([['code' => 'PL', 'name' => 'Poland', 'dial_code' => '+48', 'flag_url' => '/flags/pl.svg']], true);
    $check($phone['id'] !== null && ! isset($phone['options']['countries']), 'Phone repeated its metadata inline.');
    $phoneMarker = '<div data-fff-control-configuration-id="'.$phone['id'].'"></div>';
    $dictionary = app(AssetPlanDictionary::class);
    $html = $dictionary->project('<div wire:id="full"><input id="native">'.$marker.$marker.$phoneMarker.'</div>');
    $check(substr_count($html, 'data-fff-control-configuration-definition=') === 2, 'Full projection duplicated or omitted definitions.');
    $check(substr_count($html, 'data-fff-select-catalog-ref=') === 2, 'Independent catalog owners were merged.');
    $check($dictionary->project($html) === $html, 'Reprojection was not idempotent.');
    $partial = $dictionary->project('<section wire:partial="late">'.$marker.'</section>');
    $check(substr_count($partial, 'data-fff-control-configuration-definition=') === 1, 'A later independent partial lacked its required definition.');
    $island = $dictionary->project('<!--[if BLOCK]><![endif]-->'.$marker.'<!--[if ENDBLOCK]><![endif]-->', false);
    $check(str_contains($island, '<!--[if BLOCK]><![endif]-->') && substr_count($island, 'configuration-definition=') === 1, 'Island boundaries or self-contained metadata were lost.');
    $check(substr_count($dictionary->project('<main>'.$html.$partial.'</main>'), 'configuration-definition=') === 2, 'Nested independently complete fragments did not deduplicate.');
    $definitions = app(ControlConfigurationDictionary::class)->all();
    foreach ($definitions as $definition) {
        $check(RenderedControlConfiguration::fromJson($definition->json)->id === $definition->id, 'Catalog canonical identity changed.');
    }
    $definition = reset($definitions);
    $conflict = str_replace('Poland', 'Changed', $html);
    $rejected = false;
    try {
        FragmentPlanProjection::project($conflict, [], true, [], $definitions);
    } catch (LogicException) {
        $rejected = true;
    }
    $check($rejected, 'An immutable same-ID metadata conflict was accepted.');
    $check(app(ControlConfigurationDictionary::class)->metadata('select-catalog', ['options' => [['value' => str_repeat('x', 4 * 1024 * 1024)]]]) === null, 'Oversized valid metadata did not fall back.');
    $unshared = $transport->phone([['code' => 'US']], false);
    $check($unshared['id'] === null && isset($unshared['options']['countries']), 'Custom Phone lost its inline fallback.');
    $scoped = app(ControlConfigurationDictionary::class);
    $app->forgetScopedInstances();
    $check(app(ControlConfigurationDictionary::class) !== $scoped && app(ControlConfigurationDictionary::class)->all() === [], 'Metadata survived request/job scope reset.');
} finally {
    array_pop(HandleComponents::$renderStack);
    array_pop(HandleComponents::$componentStack);
}
echo "PASS: standalone/native transport separation; immutable escaped catalogs/Phone; independently complete full/partial/island/nested fragments; conflict rejection; oversized/custom fallback and scope reset.\n";
