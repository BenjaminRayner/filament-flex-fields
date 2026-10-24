<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Closure;
use Livewire\Component;
use Livewire\LivewireManager;
use Livewire\Mechanisms\HandleComponents\ComponentContext;
use Livewire\Mechanisms\HandleComponents\HandleComponents;

use function Livewire\after;
use function Livewire\before;

/** Final transport projection, after native hooks have produced their HTML effects. */
final class LivewirePlanProjection
{
    public static function register(): void
    {
        before('dehydrate', static function (Component $component): void {
            app(LivewireRenderState::class)->beginDehydrate($component);
        });
        after('mount', static fn (): Closure => static fn (string $html): string => self::project($html));
        after('renderIsland', static fn (): Closure => static function (string $html, Closure $replaceHtml): void {
            $replaceHtml(self::project($html, false));
        });
        after('dehydrate', static function (Component $component, ComponentContext $context): void {
            try {
                self::projectEffects($context);
            } finally {
                app(LivewireRenderState::class)->endDehydrate($component);
            }
        });
    }

    /** Only phases whose outgoing markup has a native finalizer may emit references.
     * Action methods can build arbitrary streams/HTML returns: those stay self-contained.
     */
    public static function dictionaryForRender(): ?AssetPlanDictionary
    {
        $component = app(LivewireManager::class)->current();
        if (! $component instanceof Component) {
            return null;
        }
        if (! in_array($component, HandleComponents::$renderStack, true)
            && ! app(LivewireRenderState::class)->isDehydrating($component)) {
            return null;
        }

        return app(AssetPlanDictionary::class);
    }

    private static function projectEffects(ComponentContext $context): void
    {
        if (is_string($context->effects['html'] ?? null)) {
            $context->addEffect('html', self::project($context->effects['html']));
        }
        foreach (['partials', 'islandFragments'] as $effect) {
            if (! is_array($context->effects[$effect] ?? null)) {
                continue;
            }
            $fragments = $context->effects[$effect];
            foreach ($fragments as $key => $html) {
                if (is_string($html)) {
                    $fragments[$key] = self::project($html, $effect !== 'islandFragments');
                }
            }
            $context->addEffect($effect, $fragments);
        }
    }

    private static function project(string $html, bool $rooted = true): string
    {
        // Unrelated responses are byte-for-byte unchanged and do not instantiate the catalog.
        if (! str_contains($html, 'data-fff-plan-id=') && ! str_contains($html, 'data-fff-portal-template-id=')
            && ! str_contains($html, 'data-fff-control-configuration-id=')) {
            return $html;
        }

        return app(AssetPlanDictionary::class)->project($html, $rooted);
    }
}
