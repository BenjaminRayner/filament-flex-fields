<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetCatalog;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAlpineQueue;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;

function preparedQueueSnapshot(): array
{
    return [
        'stylesheets' => FlexFieldStylesheetQueue::registered(),
        'pending' => FlexFieldStylesheetQueue::pending(),
        'consumers' => FlexFieldStylesheetQueue::pendingConsumers(),
        'orphans' => FlexFieldStylesheetQueue::pendingOrphans(),
        'chunks' => FlexFieldAlpineQueue::pending(),
        'critical' => FlexFieldStylesheetQueue::criticalStylesheets(),
    ];
}

it('keeps exact queue bookkeeping while reusing a prepared plan', function (string $component, array $capabilities, bool $emitted): void {
    $plan = ComponentAssetPlan::for($component, $capabilities);
    $snapshots = [];
    foreach ([false, true] as $prepared) {
        FlexFieldStylesheetQueue::reset();
        FlexFieldAlpineQueue::reset();
        FlexFieldStylesheetQueue::enqueue('unrelated-sheet');
        FlexFieldAlpineQueue::enqueue('unrelated-chunk.js');
        if ($emitted) {
            FlexFieldStylesheetQueue::markStylesheetsEmitted(array_slice($plan->stylesheets, 0, 1));
            FlexFieldAlpineQueue::markChunksEmitted(array_slice($plan->chunks, 0, 1));
        }
        for ($instance = 0; $instance < 3; $instance++) {
            if ($prepared) {
                FlexFieldStylesheetQueue::requireCriticalPlan($plan);
                app(FlexFieldStylesheetQueue::class)->queuePlan($plan);
                app(FlexFieldAlpineQueue::class)->queuePlan($plan);
            } else {
                FlexFieldStylesheetQueue::requireCritical($component, $capabilities);
                foreach ($plan->components as $root) {
                    FlexFieldStylesheetQueue::enqueueFor($root);
                    FlexFieldAlpineQueue::enqueueChunksFor($root);
                }
            }
        }
        $snapshots[] = preparedQueueSnapshot();
    }
    expect($snapshots[1])->toBe($snapshots[0]);
})->with([
    ['select-field', [], false], ['select-field', [], true],
    ['flex-text-input', [], false], ['flex-text-input', [], true],
    ['flex-text-input', ['emoji-picker', 'icon-picker', 'suggestions'], false],
    ['flex-text-input', ['emoji-picker', 'icon-picker', 'suggestions'], true],
    ['social-links-field', [], false], ['schedule-field', [], true],
]);

it('retains custom queue overrides when a prepared plan is queued', function (): void {
    $sheets = new class extends FlexFieldStylesheetQueue
    {
        public array $seen = [];

        public function queueFor(string $component): array
        {
            $this->seen[] = $component;

            return parent::queueFor($component);
        }
    };
    $chunks = new class extends FlexFieldAlpineQueue
    {
        public array $seen = [];

        public function queueChunksFor(string $component): array
        {
            $this->seen[] = $component;

            return parent::queueChunksFor($component);
        }
    };
    $plan = ComponentAssetPlan::for('flex-text-input', ['emoji-picker', 'icon-picker']);
    $sheets->queuePlan($plan);
    $chunks->queuePlan($plan);
    expect($sheets->seen)->toBe($plan->components)->and($chunks->seen)->toBe($plan->components);
});

it('keeps legacy queue state for every built-in component and each optional capability', function (): void {
    foreach (ComponentAssetCatalog::registry()->all() as $definition) {
        $variants = [[], ...array_map(static fn (string $capability): array => [$capability], array_keys($definition->capabilities))];
        foreach ($variants as $capabilities) {
            $plan = ComponentAssetPlan::for($definition->id, $capabilities);
            FlexFieldStylesheetQueue::reset();
            FlexFieldAlpineQueue::reset();
            foreach ($plan->components as $root) {
                FlexFieldStylesheetQueue::enqueueFor($root);
                FlexFieldAlpineQueue::enqueueChunksFor($root);
            }
            $legacy = preparedQueueSnapshot();
            FlexFieldStylesheetQueue::reset();
            FlexFieldAlpineQueue::reset();
            app(FlexFieldStylesheetQueue::class)->queuePlan($plan);
            app(FlexFieldAlpineQueue::class)->queuePlan($plan);
            expect(preparedQueueSnapshot())->toBe($legacy);
        }
    }
});
