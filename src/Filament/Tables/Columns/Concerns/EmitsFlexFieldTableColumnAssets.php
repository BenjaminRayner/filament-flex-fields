<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Tables\Columns\Concerns;

use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Livewire\Component;
use Throwable;

/**
 * Emit CRG asset consumers inside the Livewire table DOM (form-field parity).
 * Markers follow the Livewire component lifecycle — SPA navigate / morph teardown
 * drops them and the injector uninstalls unretained CSS/JS.
 */
trait EmitsFlexFieldTableColumnAssets
{
    protected bool $hasCriticalFlexStyles = false;

    /** Opt in for an initially visible table; hidden tabs/modals remain lazy. */
    public function criticalStyles(bool $condition = true): static
    {
        $this->hasCriticalFlexStyles = $condition;

        return $this;
    }

    protected function withFlexFieldColumnAssets(string $component, string $html): string
    {
        if ($this->hasCriticalFlexStyles) {
            FlexFieldStylesheetQueue::requireCritical($component);
        }

        return $this->flexFieldColumnAssetMarkup($component).$html;
    }

    protected function flexFieldColumnAssetMarkup(string $component): string
    {
        return FlexFieldStylesheetQueue::emitDomRootConsumerMarkup(
            $component,
            $this->flexFieldColumnAssetConsumerId($component),
            $this->hasCriticalFlexStyles,
        );
    }

    protected function flexFieldColumnAssetConsumerId(string $component): string
    {
        $livewireId = 'page';

        try {
            $livewire = $this->getLivewire();

            if ($livewire instanceof Component) {
                $livewireId = (string) $livewire->getId();
            }
        } catch (Throwable) {
            // Unmounted columns (unit tests / early format) fall back to page scope.
        }

        return $livewireId.'.'.$component;
    }
}
