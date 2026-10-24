<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;

class FlexFieldStylesheetQueue
{
    use FlexFieldAssetQueue;

    /**
     * Root components passed to queueFor() (table columns, deferred flush).
     * Used so queued-stylesheets can emit one CRG consumer per root with the full
     * dependency URL set — same model as load-stylesheet for form fields.
     *
     * @var array<string, true>
     */
    protected array $roots = [];

    /** Explicit SSR requirements for surfaces known to be initially visible. */
    /** @var array<string, true> */
    protected array $critical = [];

    /** @param list<string> $capabilities */
    public static function requireCritical(string $component, array $capabilities = []): void
    {
        self::requireCriticalPlan(ComponentAssetPlan::for($component, $capabilities));
    }

    public static function requireCriticalPlan(ComponentAssetPlan $plan): void
    {
        $queue = app(self::class);
        foreach ($plan->stylesheets as $stylesheet) {
            $queue->critical[$stylesheet] = true;
        }
    }

    /** Reuse validated requirements; recording roots still supports deferred/table flushes. */
    public function queuePlan(ComponentAssetPlan $plan): void
    {
        if (static::class !== self::class) {
            foreach ($plan->components as $component) {
                $this->queueFor($component);
            }

            return;
        }
        foreach ($plan->components as $component) {
            $this->roots[FlexFieldAssets::resolveStylesheetComponent($component)] = true;
        }
        foreach ($plan->queuedStylesheets as $stylesheet) {
            $this->queue($stylesheet);
        }
    }

    /** @return list<string> */
    public static function criticalStylesheets(): array
    {
        return array_keys(app(self::class)->critical);
    }

    /**
     * Queue a component and its declared dependencies, returning only stylesheets
     * that have not been registered yet during the current request.
     *
     * @return list<string>
     */
    public function queueFor(string $component): array
    {
        $resolved = FlexFieldAssets::resolveStylesheetComponent($component);
        $this->roots[$resolved] = true;

        $pending = [];

        foreach (FlexFieldAssets::stylesheetsFor($resolved) as $stylesheet) {
            if ($this->queue($stylesheet)) {
                $pending[] = $stylesheet;
            }
        }

        return $pending;
    }

    /**
     * Emit a consumer at every rendered cell boundary, including repeated columns.
     * Full stylesheetsFor() + alpineChunksFor() URL sets — form-field ownership model.
     * Marks sheets/chunks emitted so panel BODY_END does not duplicate page-scoped markers.
     */
    public function emitDomRootConsumer(string $component, string $livewireKey, bool $initialCssReady = false): string
    {
        if ($livewireKey === '') {
            return '';
        }

        $resolved = FlexFieldAssets::resolveStylesheetComponent($component);
        $this->roots[$resolved] = true;

        $stylesheets = FlexFieldAssets::stylesheetsFor($resolved);
        $chunks = FlexFieldAssets::alpineChunksFor($resolved);

        foreach ($stylesheets as $stylesheet) {
            $this->queue($stylesheet);
        }

        foreach ($chunks as $chunk) {
            FlexFieldAlpineQueue::enqueue($chunk);
        }

        $this->markEmitted($stylesheets);
        FlexFieldAlpineQueue::markChunksEmitted($chunks);

        return view('filament-flex-fields::partials.emit-assets', [
            'stylesheets' => $stylesheets,
            'chunks' => $chunks,
            'consumerComponent' => $resolved,
            'livewireKey' => $livewireKey,
            'initialCssReady' => $initialCssReady && ! request()->hasHeader('X-Livewire'),
        ])->render();
    }

    public function hasComponent(string $component): bool
    {
        return $this->hasKey($component);
    }

    /**
     * @return list<string>
     */
    public function enqueuedStylesheets(): array
    {
        return $this->registeredKeys();
    }

    /**
     * Root consumers that still have at least one pending stylesheet.
     * Each entry carries the full stylesheetsFor()/alpineChunksFor() set so CRG
     * retains every dependency for that root (form-field parity).
     *
     * @return list<array{component: string, stylesheets: list<string>, chunks: list<string>}>
     */
    public function pendingRootConsumers(): array
    {
        $pendingSheets = array_flip($this->pendingRegistered());
        $consumers = [];

        foreach (array_keys($this->roots) as $root) {
            $stylesheets = FlexFieldAssets::stylesheetsFor($root);
            $hasPending = false;

            foreach ($stylesheets as $stylesheet) {
                if (isset($pendingSheets[$stylesheet])) {
                    $hasPending = true;

                    break;
                }
            }

            if (! $hasPending) {
                continue;
            }

            $consumers[] = [
                'component' => $root,
                'stylesheets' => $stylesheets,
                'chunks' => FlexFieldAssets::alpineChunksFor($root),
            ];
        }

        return $consumers;
    }

    /**
     * Pending stylesheets not covered by any recorded root (direct enqueue()).
     *
     * @return list<string>
     */
    public function pendingOrphanStylesheets(): array
    {
        $pending = $this->pendingRegistered();
        $covered = [];

        foreach (array_keys($this->roots) as $root) {
            foreach (FlexFieldAssets::stylesheetsFor($root) as $stylesheet) {
                $covered[$stylesheet] = true;
            }
        }

        return array_values(array_filter(
            $pending,
            static fn (string $stylesheet): bool => ! isset($covered[$stylesheet]),
        ));
    }

    public function clear(): void
    {
        $this->enqueued = [];
        $this->emitted = [];
        $this->roots = [];
        $this->critical = [];
    }

    public static function emitDomRootConsumerMarkup(string $component, string $livewireKey, bool $initialCssReady = false): string
    {
        return app(self::class)->emitDomRootConsumer($component, $livewireKey, $initialCssReady);
    }

    /**
     * @return list<string>
     */
    public static function enqueueFor(string $component): array
    {
        return app(self::class)->queueFor($component);
    }

    public static function enqueue(string $component): bool
    {
        return app(self::class)->queue($component);
    }

    public static function has(string $component): bool
    {
        return app(self::class)->hasComponent($component);
    }

    public static function reset(): void
    {
        app(self::class)->clear();
    }

    /**
     * @return list<string>
     */
    public static function registered(): array
    {
        return app(self::class)->enqueuedStylesheets();
    }

    /**
     * @return list<string>
     */
    public static function pending(): array
    {
        return app(self::class)->pendingRegistered();
    }

    /**
     * @return list<array{component: string, stylesheets: list<string>, chunks: list<string>}>
     */
    public static function pendingConsumers(): array
    {
        return app(self::class)->pendingRootConsumers();
    }

    /**
     * @return list<string>
     */
    public static function pendingOrphans(): array
    {
        return app(self::class)->pendingOrphanStylesheets();
    }

    /**
     * @param  list<string>  $stylesheets
     */
    public static function markStylesheetsEmitted(array $stylesheets): void
    {
        app(self::class)->markEmitted($stylesheets);
    }
}
