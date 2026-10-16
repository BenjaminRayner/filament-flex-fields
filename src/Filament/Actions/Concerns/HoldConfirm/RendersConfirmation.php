<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\HoldConfirm;

use Bjanczak\FilamentFlexFields\Support\Confirmation\HoldTriggerRenderer;

trait RendersConfirmation
{
    /** @return list<string> */
    public function getHoldConfirmTriggerClasses(): array
    {
        if ($this->isConfirmMorph()) {
            return ['fff-confirm-morph__trigger'];
        }
        $classes = ['fff-hold-confirm-action'];
        if ($palette = $this->getHoldConfirmPalette()) {
            $classes[] = 'fff-hold-confirm-action--palette-'.$palette;
        }

        return $classes;
    }

    public function getHoldConfirmMountHandler(): ?string
    {
        if (filled($this->getUrl())) {
            return null;
        }
        $handler = $this->getJsClickHandler();

        return blank($handler) ? null : $handler;
    }

    public function getHoldConfirmCompleteExpression(): string
    {
        if ($url = $this->getUrl()) {
            $value = json_encode($url, JSON_THROW_ON_ERROR);

            return $this->shouldOpenUrlInNewTab() ? "window.open({$value}, '_blank', 'noopener,noreferrer')" : "window.location.href = {$value}";
        }

        return ($handler = $this->getHoldConfirmMountHandler()) ? '$wire.'.$handler : '';
    }

    public function getConfirmationConsumerKey(): ?string
    {
        return $this->getLivewireKey();
    }

    /** @return array<string, mixed> */
    public function getConfirmationConfiguration(): array
    {
        $morph = $this->getConfirmMorphConfiguration();

        return [
            'variant' => $this->confirmationVariant, 'duration' => $this->getHoldConfirmDuration() ?? 2000,
            'releaseDuration' => $this->getHoldConfirmReleaseDuration(), 'sweep' => $this->getHoldConfirmSweep(),
            'disabled' => $this->isDisabled(), 'actionName' => $this->getName(),
            'url' => $this->getUrl(), 'newTab' => $this->shouldOpenUrlInNewTab(),
            'arguments' => $this->getInvokedArguments() ?? [], 'context' => $this->getContext(),
            ...$morph,
        ];
    }

    public function renderHoldConfirmTriggerHtml(): string
    {
        $clone = $this->getClone();
        $clone->view($this->holdConfirmOriginalView ?? $this->getView());
        $clone->url(null)->livewireClickHandlerEnabled(false);
        $clone->extraAttributes([
            'class' => implode(' ', $this->getHoldConfirmTriggerClasses()),
            'type' => 'button', 'data-confirm-role' => $this->isConfirmMorph() ? 'open' : 'hold',
            // The confirmation instance owns loading, never the page-wide request state.
            'wire:loading.attr' => false,
        ], merge: true);
        if ($this->isConfirmMorph()) {
            $clone->color('gray')->size('sm');
        }

        return $clone->toHtml();
    }

    protected function wrapHoldConfirmButtonHtml(string $html): string
    {
        $feedback = view('filament-flex-fields::actions.confirmation.hold-feedback', [
            'labels' => $this->getConfirmMorphConfiguration()['labels'],
        ])->render();

        return HoldTriggerRenderer::render($html, $this->getHoldConfirmSweep(), $feedback);
    }
}
