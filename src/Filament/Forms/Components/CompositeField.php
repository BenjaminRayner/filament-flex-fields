<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Validation\Rule;

/** Nested state and rendering belong to Filament; child renderers own their assets. */
class CompositeField extends Field
{
    protected string $view = 'filament-flex-fields::forms.components.composite-field';

    protected string $assetComponent = 'composite-field';

    /** @var list<string>|Closure */
    protected array|Closure $assetCapabilities = [];

    /**
     * Validate container keys without replacing child-specific validation or casts.
     * Atomic child fields (uploads, repeaters, JSON fields) own their internal shape.
     *
     * @param  array<string, array<mixed>>  $rules
     */
    public function dehydrateValidationRules(array &$rules): void
    {
        parent::dehydrateValidationRules($rules);
        $root = $this->getStatePath();
        if ($root === null || $root === '') {
            return; // A stateless layout has no submitted container of its own.
        }
        $paths = [];
        foreach ($this->getChildSchema()?->getFlatFields(withHidden: true) ?? [] as $field) {
            $path = $field->getStatePath();
            if ($path !== null && str_starts_with($path, $root.'.')) {
                $paths[] = substr($path, strlen($root) + 1);
            }
        }
        usort($paths, static fn (string $a, string $b): int => substr_count($a, '.') <=> substr_count($b, '.'));
        $branches = [$root => []];
        $leaves = [];
        foreach ($paths as $relative) {
            $branch = $root;
            foreach (explode('.', $relative) as $segment) {
                if (isset($leaves[$branch])) {
                    continue 2;
                }
                $branches[$branch][$segment] = true;
                $branch .= '.'.$segment;
            }
            $leaves[$branch] = true;
        }
        foreach ($branches as $path => $keys) {
            // Nullable preserves an optional empty container; required children still validate.
            $rules[$path] = [...($rules[$path] ?? ['nullable']), Rule::array(array_keys($keys))];
        }
    }

    /** @param list<string>|Closure $capabilities */
    public function assetComponent(string $component, array|Closure $capabilities = []): static
    {
        $this->assetComponent = $component;
        $this->assetCapabilities = $capabilities;

        return $this;
    }

    public function getAssetComponent(): string
    {
        return $this->assetComponent;
    }

    /** @return list<string> */
    public function getAssetCapabilities(): array
    {
        return $this->evaluate($this->assetCapabilities);
    }
}
