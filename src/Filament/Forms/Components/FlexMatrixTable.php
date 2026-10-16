<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Support\Matrix\DimensionCatalog;
use Bjanczak\FilamentFlexFields\Support\Matrix\Presentation\SizeStyles;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;

class FlexMatrixTable extends Repeater
{
    use HasControlSize;
    use HasFieldRounding;

    protected string $view = 'filament-flex-fields::forms.components.flex-matrix-table';

    /** @var array<string|int,string|array<string,mixed>>|Closure */
    protected array|Closure $rows = [];

    /** @var array<string,string>|Closure */
    protected array|Closure $columnWidths = [];

    /** @param array<string|int,string|array<string,mixed>>|Closure $rows */
    public function rows(array|Closure $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /** @param array<string,string>|Closure $widths */
    public function columnWidths(array|Closure $widths): static
    {
        $this->columnWidths = $widths;

        return $this;
    }

    /** @return array<string,string> */
    public function getColumnWidths(): array
    {
        return $this->evaluate($this->columnWidths);
    }

    /** @return list<string> */
    public function getRowKeys(): array
    {
        return array_map(strval(...), array_keys($this->getNormalizedRows()));
    }

    /** @return array<string|int,array<string,mixed>> */
    public function getNormalizedRows(): array
    {
        return DimensionCatalog::tableRows($this->evaluate($this->rows));
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        return ['fff-matrix-choice', 'fff-matrix-choice--'.$this->getSize(), 'fff-rounding-'.$this->getRounding()];
    }

    /** @return array<string,string> */
    public function getMatrixSizeStyles(): array
    {
        return SizeStyles::for($this->getSize(), false);
    }

    public function hydrateItems(): void
    {
        $state = $this->getRawState();
        if ($state === null) {
            $state = [];
        }
        if (! is_array($state)) {
            return;
        }
        foreach ($this->getRowKeys() as $key) {
            if (! array_key_exists($key, $state)) {
                $state[$key] = [];
            }
        }
        $this->rawState($state);
    }

    /**
     * @param array<string|int,array<string,mixed>>|null $state
     * @return array<string|int,array<string,mixed>>
     */
    public function dehydrateItems(?array $state): array
    {
        $state ??= [];
        foreach ($this->getNormalizedRows() as $key => $row) {
            if ($row['disabled']) {
                $state[$key] = [];
            }
        }

        return $state;
    }

    /** @return array<Schema> */
    public function getDefaultChildSchemas(): array
    {
        if (! is_array($this->getRawState())) {
            return [];
        }
        $rows = $this->getNormalizedRows();
        $schemas = [];
        foreach (parent::getDefaultChildSchemas() as $key => $schema) {
            if (array_key_exists($key, $rows)) {
                $schemas[$key] = $schema->disabled((bool) $rows[$key]['disabled']);
            }
        }

        return $schemas;
    }

    protected function areCachedDefaultChildSchemasFresh(): bool
    {
        return is_array($this->getRawState()) && parent::areCachedDefaultChildSchemasFresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->addable(false)->deletable(false)->reorderable(false);
        $this->rule('array');
        $this->rule(static fn (self $component): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($component): void {
            // Child validation may materialize nested paths. Inspect the original
            // Livewire payload before that traversal can replace a malformed row.
            $submitted = $component->getRawState();
            if (! is_array($submitted)) {
                $fail(__('validation.array', ['attribute' => $attribute]));

                return;
            }
            $rows = $component->getNormalizedRows();
            foreach ($submitted as $key => $row) {
                if (! array_key_exists($key, $rows) || ! is_array($row)) {
                    $fail(__('validation.array', ['attribute' => $attribute]));

                    return;
                }
            }
        });
    }
}
