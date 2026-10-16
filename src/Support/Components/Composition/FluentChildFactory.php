<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use InvalidArgumentException;

/** Creates the registered renderer. Typed option application remains owned by ChildRenderer. */
final class FluentChildFactory implements ChildFactory
{
    public function make(ChildRenderContext $context): Component|Column|Action
    {
        $contract = $context->contract ?? throw new InvalidArgumentException('Missing resolved child contract.');
        $class = $contract->renderer;
        $child = $class::make($context->statePath);
        if (! $child instanceof Component && ! $child instanceof Column && ! $child instanceof Action) {
            throw new InvalidArgumentException('Child renderer returned an unsupported surface.');
        }

        return $child;
    }
}
