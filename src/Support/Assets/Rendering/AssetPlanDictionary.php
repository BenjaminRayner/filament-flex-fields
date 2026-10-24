<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

use Bjanczak\FilamentFlexFields\Support\Rendering\ControlConfigurationDictionary;
use Bjanczak\FilamentFlexFields\Support\Rendering\PortalTemplateDictionary;
use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Closure;
use LogicException;

/** Scoped descriptor catalog; each independent fragment emits its own dictionary. */
final class AssetPlanDictionary
{
    /** @var array<string, RenderedAssetPlan> */
    private array $plans = [];

    private int $bytes = 0;

    public function register(RenderedAssetPlan $plan): string
    {
        if (isset($this->plans[$plan->id])) {
            if ($this->plans[$plan->id]->json !== $plan->json) {
                throw new LogicException('Conflicting immutable asset plan identity.');
            }

            return $plan->id;
        }
        if (count($this->plans) >= 4096 || $this->bytes + strlen($plan->json) > 4 * 1024 * 1024) {
            throw new LogicException('The asset plan dictionary exceeds its fragment budget.');
        }
        $this->plans[$plan->id] = $plan;
        $this->bytes += strlen($plan->json);

        return $plan->id;
    }

    /** Final serialized fragment; discarded renders never consume an emitted flag. */
    public function project(string $html, bool $rooted = true): string
    {
        $portals = str_contains($html, 'data-fff-portal-template-')
            ? RequestState::resolve(PortalTemplateDictionary::class)->all() : [];
        $configurations = str_contains($html, 'data-fff-control-configuration-')
            ? RequestState::resolve(ControlConfigurationDictionary::class)->all() : [];

        return FragmentPlanProjection::project($html, $this->plans, $rooted, $portals, $configurations);
    }

    /** @param Closure(self): string $renderer */
    public static function render(Closure $renderer): string
    {
        $dictionary = new self;

        return $dictionary->project($renderer($dictionary));
    }
}
