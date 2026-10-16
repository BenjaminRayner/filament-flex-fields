<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Composition;

use Bjanczak\FilamentFlexFields\Support\Assets\Manifest\ManifestIdentity;
use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Components\Schema\FamilyRenderContext;
use InvalidArgumentException;

/** The state path is relative to the real parent Filament schema, which owns error mapping. */
final readonly class ChildRenderContext
{
    /** @var array<string, mixed> */
    public array $configuration;

    /** @var list<string> */
    public array $capabilities;

    /** @var array<string, bool> */
    public array $stateSlots;

    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<string>  $capabilities
     * @param  array<string, bool>  $stateSlots  Field slot => shares parent state.
     */
    public function __construct(
        public FamilyRenderContext $parent,
        public string $component,
        public string $identity,
        public string $statePath,
        array $configuration,
        array $capabilities,
        public ?ChildComponentContract $contract = null,
        array $stateSlots = [],
    ) {
        $this->configuration = ConfigurationSchema::copyArray($configuration);
        if (! array_is_list($capabilities) || count($capabilities) > 128 || count($stateSlots) > 128) {
            throw new InvalidArgumentException('Child render context requires bounded capabilities and state slots.');
        }
        $declaredCapabilities = [];
        foreach ($capabilities as $capability) {
            ManifestIdentity::assert($capability);
            if (in_array($capability, $declaredCapabilities, true)) {
                throw new InvalidArgumentException('Child render capabilities must be unique.');
            }
            $declaredCapabilities[] = $capability;
        }
        $declaredSlots = [];
        foreach ($stateSlots as $name => $shared) {
            if (! is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1 || ! is_bool($shared)) {
                throw new InvalidArgumentException('Child state slots require declared names and boolean ownership.');
            }
            $declaredSlots[$name] = $shared;
        }
        $this->capabilities = $declaredCapabilities;
        $this->stateSlots = $declaredSlots;
    }

    public function pathTo(string $slot, string $key): string
    {
        $path = ChildStatePath::for($slot, $key);
        if (! array_key_exists($slot, $this->stateSlots)) {
            throw new InvalidArgumentException('Sibling state reference needs a declared field slot: '.$slot);
        }

        return $this->stateSlots[$slot] ? './' : $path;
    }
}
