<?php

declare(strict_types=1);

/**
 * @author Bartłomiej Janczak <barek122@gmail.com>
 * @copyright Copyright (c) 2026 Bartłomiej Janczak. All rights reserved.
 * @license Proprietary
 */

namespace Bjanczak\FilamentFlexFields\Support\Theme;

use Bjanczak\FilamentFlexFields\Enums\Density;

class FlexFieldsTheme
{
    protected Density $density;

    /**
     * @var array<string, string|int|float>
     */
    protected array $theme = [];

    public function __construct()
    {
        $this->density = Density::default();
    }

    public function density(): Density
    {
        return $this->density;
    }

    public function setDensity(Density|string $density): static
    {
        $this->density = Density::fromMixed($density);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function theme(): array
    {
        return $this->theme;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    public function setTheme(array $theme): static
    {
        $this->theme = ThemeTokenMap::validate($theme);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    public function mergeTheme(array $theme): static
    {
        $this->theme = ThemeTokenMap::validate(array_replace($this->theme, $theme));

        return $this;
    }

    /** @param array<string, mixed> $tokens */
    public function mergePackageTheme(AppearanceNamespace $appearance, array $tokens): static
    {
        return $this->mergeTheme($appearance->tokens($tokens));
    }

    /**
     * @return array<string, string>
     */
    public function toHtmlAttributes(): array
    {
        $attributes = [
            'data-fff-density' => $this->density->value,
        ];

        if ($this->theme !== []) {
            $attributes['data-fff-theme'] = json_encode($this->theme, JSON_THROW_ON_ERROR);
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function toCssVariables(): array
    {
        $variables = [];

        foreach ($this->theme as $key => $value) {
            $variableName = $this->resolveThemeVariableName((string) $key);

            if ($variableName === null) {
                continue;
            }

            $variables[$variableName] = (string) $value;
        }

        return $variables;
    }

    protected function resolveThemeVariableName(string $key): ?string
    {
        return ThemeTokenMap::variable($key);
    }
}
