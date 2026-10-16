<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use InvalidArgumentException;

/** Request-local composition session; installed renderers retain their own state/validation/asset boundaries. */
final class ChildRenderer
{
    /** @var array<string, mixed> */
    private readonly array $parentConfiguration;

    /** @var array<string, true> */
    private array $claims = [];

    /** @var array<string, bool> */
    private array $stateSlots = [];

    /** @param array<string, mixed> $parentConfiguration */
    public function __construct(
        private readonly ComponentRegistry $registry,
        private readonly string $parentComponent,
        private readonly FamilyRenderContext $context,
        array $parentConfiguration = [],
        private readonly bool $disabled = false,
        private readonly bool $readOnly = false,
    ) {
        $registry->definition($parentComponent);
        $registry->validate();
        $this->parentConfiguration = ConfigurationSchema::copyArray($parentConfiguration);
        foreach ($registry->definition($parentComponent)->children as $name => $slot) {
            if ($slot->kind === ComponentKind::Field) {
                $this->stateSlots[$name] = $slot->shareState;
            }
        }
    }

    /** @param array<string, mixed> $configuration Explicit child instance options, not the parent's entire configuration. */
    public function render(string $slotName, string $key, array $configuration = [], ?Closure $configure = null): Component|Column|Action
    {
        FamilyRenderContext::assertKey($key);
        $resolvedSlot = $this->registry->childSlot($this->parentComponent, $slotName);
        $slot = $resolvedSlot->slot;
        $contract = $resolvedSlot->contract;
        $identity = $this->context->identity($this->parentComponent, hash('sha256', json_encode([$slotName, $key], JSON_THROW_ON_ERROR)));
        if (isset($this->claims[$identity]) || count($this->claims) >= 256) {
            throw new InvalidArgumentException('Duplicate child key or composition session budget exceeded.');
        }
        $mapped = [];
        foreach ($slot->configurationMap as $source => $target) {
            if (array_key_exists($source, $this->parentConfiguration)) {
                $mapped[$target] = $this->parentConfiguration[$source];
            }
        }
        $resolved = $contract->configuration->resolve([$contract->packageDefaults, $configuration, $mapped]);
        $statePath = ChildStatePath::for($slotName, $key);
        $childContext = new ChildRenderContext($this->context, $slot->component, $identity, $statePath, $resolved, $slot->capabilities, $contract, $this->stateSlots);
        $this->claims[$identity] = true;
        try {
            /** @var ChildFactory $factory */
            $factory = app()->build($contract->factory);
            $child = $factory->make($childContext);
            if (! $child instanceof $contract->renderer) {
                throw new InvalidArgumentException('Child factory must reuse its declared renderer class.');
            }
            $contract->apply($child, $resolved);
            if ($configure !== null) {
                $configured = $configure($child, $childContext);
                if ($configured !== null && $configured !== $child) {
                    throw new InvalidArgumentException('Child PHP configuration must preserve its renderer instance.');
                }
                $contract->apply($child, $resolved, lockedOnly: true);
            }
            if ($child instanceof Component) {
                $child->key($identity)->id(null);
            }
            if ($child instanceof Field) {
                $child->statePath($slot->shareState ? null : $statePath);
                if ($this->disabled || $this->readOnly) {
                    $child->disabled()->dehydrated(false)->validatedWhenNotDehydrated(false);
                }
            }

            return $child;
        } catch (\Throwable $error) {
            unset($this->claims[$identity]);
            throw $error;
        }
    }
}
