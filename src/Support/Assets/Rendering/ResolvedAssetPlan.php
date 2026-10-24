<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Assets\FlexFieldsAlpineComponent;
use Bjanczak\FilamentFlexFields\Assets\FlexFieldsCss;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Support\Assets\AssetVersionManifest;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Publication\BuiltInAssetRelease;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Filament\Support\Facades\FilamentAsset;
use WeakMap;

/** Request-owned public metadata only. Instance IDs, visibility and readiness are never cached. */
final class ResolvedAssetPlan
{
    /** @var WeakMap<ComponentAssetPlan, array{context: string, plan: RenderedAssetPlan}> */
    private WeakMap $plans;

    public function __construct()
    {
        $this->plans = new WeakMap;
    }

    public function resolve(ComponentAssetPlan $plan): RenderedAssetPlan
    {
        $delivery = app(AssetDeliveryContext::class)->policy();
        $release = RequestState::resolve(BuiltInAssetRelease::class);
        $sources = [];
        $cacheable = true;
        $wanted = array_fill_keys([...array_map(FlexFieldAssets::stylesheetId(...), $plan->stylesheets),
            ...array_map(static fn (string $chunk): string => str_replace('.js', '', $chunk), $plan->chunks)], true);
        foreach ([...FilamentAsset::getStyles([FilamentFlexFieldsPlugin::PACKAGE_NAME]),
            ...FilamentAsset::getAlpineComponents([FilamentFlexFieldsPlugin::PACKAGE_NAME])] as $asset) {
            if (! isset($wanted[$asset->getId()])) {
                continue;
            }
            $sources[] = [$asset::class, spl_object_id($asset), $asset->getId(), $asset->getPath(), $asset->getPackage()];
            if (! in_array($asset::class, [FlexFieldsCss::class, FlexFieldsAlpineComponent::class], true)
                || $release->relativeSource($asset->getPath()) === null) {
                $cacheable = false;
            }
        }
        $origin = request()->getSchemeAndHttpHost();
        $context = hash('xxh128', serialize([$sources, asset('/'), $origin, config('filament.assets_path'),
            spl_object_id($delivery), RequestState::resolve(AssetVersionManifest::class)->release()]));
        if ($cacheable && isset($this->plans[$plan]) && $this->plans[$plan]['context'] === $context) {
            return $this->plans[$plan]['plan'];
        }

        $stylesheets = array_map(FlexFieldAssets::stylesheetHref(...), $plan->stylesheets);
        $chunks = array_map(FlexFieldAssets::alpineChunkSrc(...), $plan->chunks);
        $sizes = [];
        foreach ($plan->stylesheets as $index => $stylesheet) {
            $bytes = FlexFieldAssets::stylesheetBytes($stylesheet);
            if ($bytes !== null) {
                $sizes[$stylesheets[$index]] = $bytes;
            }
        }
        foreach ($plan->chunks as $index => $chunk) {
            $bytes = FlexFieldAssets::chunkBytes($chunk);
            if ($bytes !== null) {
                $sizes[$chunks[$index]] = $bytes;
            }
        }
        foreach ([...$stylesheets, ...$chunks] as $url) {
            $delivery->attributes($url, $origin);
        }
        $resolved = new RenderedAssetPlan($plan->component, $stylesheets, $chunks, $sizes,
            RenderedFactoryOwners::forComponents($plan->components));
        if ($cacheable && count($this->plans) < 256) {
            $this->plans[$plan] = ['context' => $context, 'plan' => $resolved];
        }

        return $resolved;
    }
}
