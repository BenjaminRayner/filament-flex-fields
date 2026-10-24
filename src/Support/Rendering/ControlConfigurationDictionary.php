<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewirePlanProjection;

/** Request-owned definitions; final fragments emit only their referenced options. */
final class ControlConfigurationDictionary
{
    /** @var array<string, RenderedControlConfiguration> */
    private array $configurations = [];

    private int $bytes = 0;

    /** @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function bootstrap(string $component, array $configuration): array
    {
        if (LivewirePlanProjection::dictionaryForRender() === null) {
            return $configuration;
        }
        $local = array_intersect_key($configuration, array_flip(['statePath', 'initialState']));
        $options = array_diff_key($configuration, $local);
        // Budget the escaped transport, not just the unescaped source string.
        // Leave room for the definition envelope; oversized valid options stay inline.
        if (strlen(json_encode($options, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)) > 65000) {
            return $configuration;
        }
        $definition = new RenderedControlConfiguration($component, $options);
        if (! isset($this->configurations[$definition->id])) {
            if (count($this->configurations) >= 512 || $this->bytes + strlen($definition->json) > 4 * 1024 * 1024) {
                return $configuration;
            }
            $this->configurations[$definition->id] = $definition;
            $this->bytes += strlen($definition->json);
        }

        return ['configurationId' => $definition->id, ...$local];
    }

    /** @param array<string, mixed> $options */
    public function metadata(string $component, array $options): ?RenderedControlConfiguration
    {
        if (LivewirePlanProjection::dictionaryForRender() === null) {
            return null;
        }
        if (strlen(json_encode($options, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)) > RenderedControlConfiguration::maximumBytes($component) - 256) {
            return null;
        }
        $definition = new RenderedControlConfiguration($component, $options);
        if (isset($this->configurations[$definition->id])) {
            return $this->configurations[$definition->id];
        }
        if (count($this->configurations) >= 512 || $this->bytes + strlen($definition->json) > 4 * 1024 * 1024) {
            return null;
        }
        $this->configurations[$definition->id] = $definition;
        $this->bytes += strlen($definition->json);

        return $definition;
    }

    /** @return array<string, RenderedControlConfiguration> */
    public function all(): array
    {
        return $this->configurations;
    }
}
