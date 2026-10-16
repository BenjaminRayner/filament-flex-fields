<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAlpineQueue;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;

beforeEach(function () {
    FlexFieldAlpineQueue::reset();
});

it('resolves shared alpine chunks for audio components from the build manifest', function () {
    $manifestPath = FlexFieldAssets::alpineManifestPath();

    expect(is_file($manifestPath))->toBeTrue();

    $audioChunks = FlexFieldAssets::alpineChunksFor('audio-field');
    $voiceChunks = FlexFieldAssets::alpineChunksFor('voice-note-recorder-field');

    expect($audioChunks)->not->toBeEmpty()
        ->and($voiceChunks)->not->toBeEmpty()
        ->and(array_intersect($audioChunks, $voiceChunks))->not->toBeEmpty();
});

it('deduplicates alpine chunk preloads across multiple fields on one request', function () {
    $first = FlexFieldAlpineQueue::enqueueChunksFor('audio-field');
    $second = FlexFieldAlpineQueue::enqueueChunksFor('voice-note-recorder-field');

    expect($first)->not->toBeEmpty();

    foreach ($first as $chunk) {
        expect(FlexFieldAlpineQueue::has($chunk))->toBeTrue();
    }

    expect($second)->toBe(array_values(array_diff(
        FlexFieldAssets::alpineChunksFor('voice-note-recorder-field'), $first,
    )))->not->toBeEmpty();
});

it('lazy-loads emoji picker chunks only when fields request shared chunks', function () {
    $inputChunks = FlexFieldAlpineQueue::enqueueChunksFor('flex-text-input');
    $textareaChunks = FlexFieldAlpineQueue::enqueueChunksFor('flex-textarea');

    expect($inputChunks)->toBe(FlexFieldAssets::alpineChunksFor('flex-text-input'))
        ->and($textareaChunks)->toBe(array_values(array_diff(
            FlexFieldAssets::alpineChunksFor('flex-textarea'), $inputChunks,
        )))->not->toBeEmpty();
});

it('registers only alpine manifest entry names as primary alpine components', function () {
    $provider = new FilamentFlexFieldsServiceProvider(app());
    $method = new ReflectionMethod($provider, 'registeredAlpineComponents');

    $registeredIds = array_map(
        fn ($asset) => $asset->getId(),
        $method->invoke($provider),
    );

    $expected = array_values(array_filter(
        FlexFieldAssets::alpineEntryNames(),
        fn (string $entry): bool => is_file(__DIR__.'/../../resources/dist/components/'.$entry.'.js'),
    ));

    sort($registeredIds);
    sort($expected);

    expect($registeredIds)->toBe($expected)
        ->and(collect($registeredIds)->filter(fn (string $id): bool => str_starts_with($id, 'flex-fields-')))->toBeEmpty();
});

it('does not preload phone lib chunks from the alpine queue', function () {
    $phoneChunks = FlexFieldAssets::alpineChunksFor('phone-field');

    foreach ($phoneChunks as $chunk) {
        expect($chunk)->not->toStartWith('flex-fields-phone-lib');
    }
});

it('deduplicates mapbox chunk preloads across map picker and address autocomplete', function () {
    FlexFieldAlpineQueue::reset();

    $mapChunks = FlexFieldAlpineQueue::enqueueChunksFor('map-picker');
    $addressChunks = FlexFieldAlpineQueue::enqueueChunksFor('address-autocomplete');

    expect($mapChunks)->not->toBeEmpty()
        ->and($addressChunks)->toBe(array_values(array_diff(
            FlexFieldAssets::alpineChunksFor('address-autocomplete'), $mapChunks,
        )))->not->toBeEmpty();
});

it('deduplicates searchable select menu chunk preloads across country timezone and currency fields', function () {
    FlexFieldAlpineQueue::reset();

    $countryChunks = FlexFieldAlpineQueue::enqueueChunksFor('country-field');
    $timezoneChunks = FlexFieldAlpineQueue::enqueueChunksFor('timezone-field');
    $currencyChunks = FlexFieldAlpineQueue::enqueueChunksFor('currency-field');

    $selectMenuChunk = collect(FlexFieldAssets::alpineChunksFor('country-field'))
        ->first(fn (string $chunk): bool => str_contains($chunk, 'flex-fields-select-menu-'));

    expect($countryChunks)->not->toBeEmpty()
        ->and($selectMenuChunk)->toBeString()
        ->and($countryChunks)->toContain($selectMenuChunk)
        ->and($timezoneChunks)->not->toContain($selectMenuChunk)
        ->and($currencyChunks)->not->toContain($selectMenuChunk);
});

it('documents shared chunk source modules in the build manifest with semantic chunk names', function () {
    $manifest = json_decode((string) file_get_contents(FlexFieldAssets::alpineManifestPath()), true);

    $emojiChunk = collect($manifest['__chunk_modules__'] ?? [])
        ->filter(fn (array $modules): bool => in_array('core/shared-emoji-picker.js', $modules, true))
        ->keys()
        ->first();

    expect($emojiChunk)
        ->toBeString()
        ->toStartWith('flex-fields-emoji-')
        ->toEndWith('.js');

    $selectMenuChunk = collect($manifest['__chunk_modules__'] ?? [])
        ->filter(fn (array $modules): bool => in_array('core/searchable-select-menu.js', $modules, true))
        ->keys()
        ->first();

    expect($selectMenuChunk)
        ->toBeString()
        ->toStartWith('flex-fields-select-menu-')
        ->toEndWith('.js');
});

it('keeps flex text input shell code separate from the shared emoji chunk', function () {
    $flexTextInputJs = file_get_contents(__DIR__.'/../../resources/dist/components/flex-text-input.js');
    $sharedChunks = glob(__DIR__.'/../../resources/dist/components/flex-fields-*.js');

    expect($flexTextInputJs)
        ->toContain('flex-fields-')
        ->not->toContain('emoji-picker-element');

    expect($sharedChunks)->not->toBeEmpty();
});

it('rewrites nested chunk imports to semantic names after build', function () {
    $manifest = FlexFieldAssets::alpineManifest();
    $chunks = $manifest['__shared_chunks__'];
    expect($chunks)->not->toBeEmpty();

    foreach ($chunks as $chunk) {
        expect($chunk)->toStartWith('flex-fields-')->toEndWith('.js');
        $source = file_get_contents(__DIR__.'/../../resources/dist/components/'.$chunk);
        preg_match_all('~\\b(?:from\\s*|import\\s*(?:\\(\\s*)?)[\'"]\\./([^\'"]+\\.js)[\'"]~', $source, $imports);
        foreach ($imports[1] as $import) {
            expect($import)->toStartWith('flex-fields-')
                ->and($chunks)->toContain($import)
                ->and(is_file(__DIR__.'/../../resources/dist/components/'.$import))->toBeTrue();
        }
    }
});

it('uses a registry-only overlay coordinator without legacy window event bus', function () {
    $coordinatorChunk = collect(glob(__DIR__.'/../../resources/dist/components/flex-fields-flex-dropdown-coordinator-*.js'))
        ->first();

    expect($coordinatorChunk)->toBeString();

    $source = file_get_contents($coordinatorChunk);

    expect($source)
        ->toContain('fffOverlays')
        ->not->toContain('fff:flex-dropdown-open')
        ->not->toContain('__fffFlexDropdownCoordinator');
});

it('preloads the overlay coordinator chunk for select field dropdown exclusivity', function () {
    $overlayChunk = FlexFieldAssets::overlayCoordinatorChunk();

    expect($overlayChunk)
        ->toBeString()
        ->toStartWith('flex-fields-flex-dropdown-coordinator-');

    expect(FlexFieldAssets::alpineChunksFor('select-field'))
        ->toContain($overlayChunk);
});

it('preloads transitive barcode scanner zxing chunks from the alpine manifest graph', function () {
    $manifest = FlexFieldAssets::alpineManifest();
    $barcodeChunks = FlexFieldAssets::alpineChunksFor('barcode-scanner-field');

    expect($barcodeChunks)->not->toBeEmpty();

    foreach ($manifest['__chunk_imports__'] ?? [] as $chunk => $imports) {
        if (! in_array($chunk, $barcodeChunks, true)) {
            continue;
        }

        foreach ($imports as $import) {
            expect($barcodeChunks)->toContain($import);
        }
    }
});
