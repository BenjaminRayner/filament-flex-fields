@php
    use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;

    $stylesheets = $stylesheets ?? [];
    $chunks = $chunks ?? [];
    $externalStylesheets = $externalStylesheets ?? [];
    if (! is_array($externalStylesheets) || ! array_is_list($externalStylesheets) || count($externalStylesheets) > 16) {
        throw new \InvalidArgumentException('External stylesheets require a list of at most sixteen URLs.');
    }
    foreach ($externalStylesheets as $externalStylesheet) {
        if (! is_string($externalStylesheet) || strlen($externalStylesheet) > 2048 || ! preg_match('~^(?:https://[^\s]+|/(?!/)[^\s]*)$~D', $externalStylesheet)) {
            throw new \InvalidArgumentException('External stylesheet requirements need HTTPS or a same-origin absolute path.');
        }
    }
    $consumerComponent = $consumerComponent ?? null;
    $livewireKey = $livewireKey ?? null;

    // Defense in depth: every non-empty batch must carry CRG consumer attrs.
    // Keyless schema components previously emitted consumer-less markers; the injector
    // loaded CSS then uninstalled it (~150ms) — production forms broke, playground did not.
    if (! filled($consumerComponent) && (count($stylesheets) > 0 || count($chunks) > 0 || count($externalStylesheets) > 0)) {
        $consumerComponent = $stylesheets[0] ?? $chunks[0] ?? $externalStylesheets[0] ?? 'asset-batch';
    }

    $stylesheetHrefs = array_map(
        static fn (string $stylesheet): string => FlexFieldAssets::stylesheetHref($stylesheet),
        $stylesheets,
    );
    $chunkHrefs = array_map(
        static fn (string $chunk): string => FlexFieldAssets::alpineChunkSrc($chunk),
        $chunks,
    );
    $stylesheetHrefs = array_values(array_unique([...$stylesheetHrefs, ...$externalStylesheets]));
    $deliveryPolicy = app(\Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext::class)->policy();
    foreach ([...$stylesheetHrefs, ...$chunkHrefs] as $resourceUrl) {
        $deliveryPolicy->attributes($resourceUrl, request()->getSchemeAndHttpHost());
    }
    $consumerAttributes = filled($consumerComponent)
        ? FlexFieldAssets::consumerAttributesForComponent((string) $consumerComponent, $livewireKey)
        : [];
    $assetSizes = [];
    $factoryOwners = $factoryOwners ?? [];

    foreach ($stylesheets as $index => $stylesheet) {
        $bytes = FlexFieldAssets::stylesheetBytes($stylesheet);
        if ($bytes !== null) {
            $assetSizes[$stylesheetHrefs[$index]] = $bytes;
        }
    }

    foreach ($chunks as $index => $chunk) {
        $bytes = FlexFieldAssets::chunkBytes($chunk);
        if ($bytes !== null) {
            $assetSizes[$chunkHrefs[$index]] = $bytes;
        }
    }
    $renderedPlanId = null;
    // A true Livewire lifecycle has final mount/dehydrate projection. Standalone
    // Blade keeps its self-contained inline protocol unless a dictionary is explicit.
    $planDictionary ??= \Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewirePlanProjection::dictionaryForRender();
    if (isset($planDictionary)) {
        if (! $planDictionary instanceof \Bjanczak\FilamentFlexFields\Support\Assets\Rendering\AssetPlanDictionary) {
            throw new \InvalidArgumentException('Expected a fragment asset plan dictionary.');
        }
        $renderedPlanId = $planDictionary->register(new \Bjanczak\FilamentFlexFields\Support\Assets\Rendering\RenderedAssetPlan(
            (string) $consumerComponent, $stylesheetHrefs, $chunkHrefs, $assetSizes, $factoryOwners,
        ));
    }
@endphp

@if (count($stylesheetHrefs) > 0 || count($chunkHrefs) > 0)
    <span
        hidden
        aria-hidden="true"
        data-fff-asset-batch
        @if ($initialCssReady ?? false) data-fff-assets-ready @endif
        @if ($renderedPlanId !== null)
        data-fff-plan-id="{{ $renderedPlanId }}"
        data-fff-plan-generation="1"
        @else
        data-fff-stylesheets='@json($stylesheetHrefs)'
        data-fff-chunks='@json($chunkHrefs)'
        data-fff-asset-sizes='@json($assetSizes)'
        @if ($factoryOwners !== []) data-fff-factory-owners='@json($factoryOwners)' @endif
        @endif
        @if (filled($consumerExpression ?? null))
            x-bind:data-fff-asset-consumer-id="{{ $consumerExpression }}"
        @endif
        @foreach ($consumerAttributes as $attribute => $value)
            {{ $attribute }}="{{ $value }}"
        @endforeach
    ></span>
@endif
