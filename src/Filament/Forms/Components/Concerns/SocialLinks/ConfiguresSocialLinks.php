<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SocialLinks;

use Bjanczak\FilamentFlexFields\Data\SocialPlatform;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialPlatformDefinition;
use Closure;
use InvalidArgumentException;

trait ConfiguresSocialLinks
{
    protected string|Closure $variant = 'primary';

    /** @var list<string|SocialPlatform>|Closure|null */
    protected array|Closure|null $platforms = null;

    /** @var list<string|SocialPlatform>|Closure */
    protected array|Closure $excludePlatforms = [];

    /** @var list<array<string, mixed>>|Closure */
    protected array|Closure $customPlatforms = [];

    protected int|Closure|null $maxLinks = null;

    protected bool|Closure $reorderable = false;

    protected bool|Closure $autoFormatUrls = true;

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        if (! in_array($variant, ['primary', 'secondary', 'soft', 'flat', 'ghost'], true)) {
            throw new InvalidArgumentException("Invalid SocialLinksField variant [{$variant}].");
        }

        return $variant;
    }

    /**
     * @param  list<string|SocialPlatform>|Closure|null  $platforms
     */
    public function platforms(array|Closure|null $platforms): static
    {
        $this->platforms = $platforms;

        return $this;
    }

    /**
     * @param  list<string|SocialPlatform>|Closure  $platforms
     */
    public function excludePlatforms(array|Closure $platforms): static
    {
        $this->excludePlatforms = $platforms;

        return $this;
    }

    /**
     * @param  list<array<string, mixed>>|Closure  $platforms
     */
    public function customPlatforms(array|Closure $platforms): static
    {
        $this->customPlatforms = $platforms;

        return $this;
    }

    public function reorderable(bool|Closure $enabled = true): static
    {
        $this->reorderable = $enabled;

        return $this;
    }

    public function isReorderable(): bool
    {
        return (bool) $this->evaluate($this->reorderable);
    }

    public function autoFormatUrls(bool|Closure $enabled = true): static
    {
        $this->autoFormatUrls = $enabled;

        return $this;
    }

    public function shouldAutoFormatUrls(): bool
    {
        return (bool) $this->evaluate($this->autoFormatUrls);
    }

    /**
     * @return list<SocialPlatform>
     */
    public function getPlatforms(): array
    {
        return array_values(array_filter(array_map(
            fn (string $value): ?SocialPlatform => SocialPlatform::tryFrom($value),
            $this->getPlatformValues(),
        )));
    }

    /**
     * @return list<string>
     */
    public function getPlatformValues(): array
    {
        return array_keys($this->getPlatformDefinitionMap());
    }

    /**
     * @return array<string, SocialPlatformDefinition>
     */
    public function getPlatformDefinitionMap(): array
    {
        $customDefinitions = $this->resolveCustomPlatformDefinitions();
        $configured = $this->evaluate($this->platforms);

        if ($configured !== null) {
            if (! is_array($configured) || ! array_is_list($configured)) {
                throw new InvalidArgumentException('Platforms must be a list.');
            }
            $definitions = [];

            foreach ($configured as $platform) {
                if ($platform instanceof SocialPlatform) {
                    $definitions[$platform->value] = SocialPlatformDefinition::fromEnum($platform);

                    continue;
                }

                if (! is_string($platform)) {
                    throw new InvalidArgumentException('Invalid social platform.');
                }

                $value = trim($platform);

                if ($value === '') {
                    throw new InvalidArgumentException('Empty social platform.');
                }

                if (isset($customDefinitions[$value])) {
                    $definitions[$value] = $customDefinitions[$value];

                    continue;
                }

                $enum = SocialPlatform::tryFrom($value);

                if ($enum === null) {
                    throw new InvalidArgumentException("Unknown social platform [{$value}].");
                }
                $definitions[$value] = SocialPlatformDefinition::fromEnum($enum);
            }

            return $this->filterDefinitions($definitions);
        }

        return $this->defaultPlatformDefinitionMap($customDefinitions);
    }

    /**
     * @return array<string, SocialPlatformDefinition>
     */
    protected function defaultPlatformDefinitionMap(array $customDefinitions): array
    {
        $definitions = [];

        foreach (SocialPlatform::defaults() as $platform) {
            $definitions[$platform->value] = SocialPlatformDefinition::fromEnum($platform);
        }

        foreach ($customDefinitions as $value => $definition) {
            $definitions[$value] = $definition;
        }

        return $this->filterDefinitions($definitions);
    }

    /** @param array<string, SocialPlatformDefinition> $definitions
     * @return array<string, SocialPlatformDefinition> */
    private function filterDefinitions(array $definitions): array
    {
        $definitions = array_diff_key($definitions, array_flip($this->getExcludedPlatformValues()));
        if (count($definitions) > 128) {
            throw new InvalidArgumentException('At most 128 social platforms can be configured.');
        }

        return $definitions;
    }

    /**
     * @return array<string, SocialPlatformDefinition>
     */
    protected function resolveCustomPlatformDefinitions(): array
    {
        $configured = $this->evaluate($this->customPlatforms);

        if (! is_array($configured) || ! array_is_list($configured) || count($configured) > 128) {
            throw new InvalidArgumentException('Social platform configuration must be a list of at most 128 items.');
        }

        $definitions = [];

        foreach ($configured as $entry) {
            if (! is_array($entry)) {
                throw new InvalidArgumentException('Custom platform must be an array.');
            }

            $definition = SocialPlatformDefinition::fromArray($entry);
            if (isset($definitions[$definition->value])) {
                throw new InvalidArgumentException('Duplicate custom social platform.');
            }
            $definitions[$definition->value] = $definition;
        }

        return $definitions;
    }

    /**
     * @return list<string>
     */
    protected function getExcludedPlatformValues(): array
    {
        $configured = $this->evaluate($this->excludePlatforms);

        if (! is_array($configured) || ! array_is_list($configured) || count($configured) > 128) {
            throw new InvalidArgumentException('Social platform configuration must be a list of at most 128 items.');
        }

        $values = [];

        foreach ($configured as $platform) {
            if ($platform instanceof SocialPlatform) {
                $values[] = $platform->value;

                continue;
            }

            if (! is_string($platform) || trim($platform) === '') {
                throw new InvalidArgumentException('Invalid excluded social platform.');
            }
            $values[] = trim($platform);
        }

        return $values;
    }

    public function maxLinks(int|Closure|null $max): static
    {
        $this->maxLinks = $max;

        return $this;
    }

    public function getMaxLinks(): ?int
    {
        $max = $this->evaluate($this->maxLinks);

        if ($max !== null && (! is_int($max) || $max < 1 || $max > 128)) {
            throw new InvalidArgumentException('maxLinks must be between 1 and 128, or null.');
        }

        return $max;
    }
}
