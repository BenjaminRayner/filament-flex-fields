<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Matrix\ConfiguresMatrixChoice;
use Bjanczak\FilamentFlexFields\Support\ChoiceLists\OptionCatalog;
use Bjanczak\FilamentFlexFields\Support\Matrix\ChoicePolicy;
use Bjanczak\FilamentFlexFields\Support\Matrix\DimensionCatalog;
use Bjanczak\FilamentFlexFields\Support\Matrix\Presentation\SizeStyles;
use Bjanczak\FilamentFlexFields\Support\Matrix\State\MatrixStateCast;
use Bjanczak\FilamentFlexFields\Support\Matrix\Validation\MatrixSelection;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Support\Arr;

class MatrixChoiceField extends Field
{
    use ConfiguresMatrixChoice;
    use HasControlSize;
    use HasFieldRounding;

    protected string $view = 'filament-flex-fields::forms.components.matrix-choice-field';

    /** @return array<string|int,array{label:string,description:?string,disabled:bool,required:bool,min_selections:?int,max_selections:?int}> */
    public function getNormalizedRows(): array
    {
        return DimensionCatalog::rows($this->evaluate($this->rows), $this->getDisabledRowKeys(), $this->getRequiredRowKeys(), $this->isRequired());
    }

    /** @return array<string|int,array{label:string,icon:?string,disabled:bool}> */
    public function getNormalizedColumns(): array
    {
        $columns = OptionCatalog::normalize($this->evaluate($this->matrixColumns), $this->evaluate($this->columnIcons));
        foreach ($columns as &$column) {
            unset($column['description']);
        }

        return $columns;
    }

    /** @return list<string> */
    public function getRowKeys(): array
    {
        return array_map(strval(...), array_keys($this->getNormalizedRows()));
    }

    /** @return list<string> */
    public function getColumnKeys(): array
    {
        return array_map(strval(...), array_keys($this->getNormalizedColumns()));
    }

    /** @return array<string|int,list<string>> */
    public function getDisabledCellsMap(): array
    {
        $map = [];
        foreach ($this->evaluate($this->disabledCells) as $row => $columns) {
            $map[$row] = array_values(array_map(strval(...), Arr::wrap($columns)));
        }

        return $map;
    }

    public function getChoicePolicy(): ChoicePolicy
    {
        return new ChoicePolicy($this->getNormalizedRows(), $this->getNormalizedColumns(), $this->getDisabledCellsMap(), $this->getConditionalDisableRules(), $this->isCheckboxMode());
    }

    /** @param array<string,mixed>|null $state */
    public function isRowDisabled(string $rowKey, ?array $state = null): bool
    {
        return $this->getChoicePolicy()->rowDisabled($rowKey, $state ?? []);
    }

    /** @param array<string,mixed>|null $state */
    public function isCellDisabled(string $rowKey, string $columnKey, ?array $state = null): bool
    {
        return $this->getChoicePolicy()->cellDisabled($rowKey, $columnKey, $state ?? []);
    }

    /** @return list<string> */
    public function getWrapperClasses(): array
    {
        $classes = ['fff-matrix-choice', 'fff-matrix-choice--'.$this->getSize(), 'fff-rounding-'.$this->getRounding(), 'fff-matrix-choice--'.$this->getMode()];
        if ($color = $this->getColor()) {
            $classes[] = 'fi-color-'.$color;
        }

        return $classes;
    }

    /** @return array<string,string> */
    public function getMatrixSizeStyles(): array
    {
        return SizeStyles::for($this->getSize());
    }

    /** @return array<string|int,mixed> */
    public function getInitialState(): array
    {
        $state = $this->getState();
        $safe = [];
        foreach ($this->getRowKeys() as $key) {
            $value = is_array($state) ? ($state[$key] ?? null) : null;
            if ($this->isCheckboxMode()) {
                $safe[$key] = is_array($value) ? array_map(strval(...), array_filter($value, fn (mixed $v): bool => is_string($v) || is_int($v))) : [];
            } else {
                $safe[$key] = is_string($value) || is_int($value) ? (string) $value : null;
            }
        }

        return $safe;
    }

    /** @return array<string|int,string|list<string>> */
    public function dehydrateValue(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }
        $result = [];
        $columns = array_fill_keys($this->getColumnKeys(), true);
        foreach ($this->getRowKeys() as $key) {
            $value = $state[$key] ?? null;
            if ($this->isCheckboxMode()) {
                if (! is_array($value)) {
                    continue;
                }
                $values = [];
                foreach ($value as $column) {
                    if ((is_string($column) || is_int($column)) && isset($columns[(string) $column])) {
                        $values[] = (string) $column;
                    }
                }
                if ($values !== []) {
                    $result[$key] = $values;
                }
            } elseif ((is_string($value) || is_int($value)) && isset($columns[(string) $value])) {
                $result[$key] = (string) $value;
            }
        }

        return $result;
    }

    /** @return array<StateCast> */
    public function getDefaultStateCasts(): array
    {
        return [...parent::getDefaultStateCasts(), new MatrixStateCast($this->isCheckboxMode())];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
        $this->dehydrateStateUsing(static fn (self $component, mixed $state): array => $component->dehydrateValue($state));
        $this->rule(static fn (self $component): Closure => static fn (string $attribute, mixed $value, Closure $fail) => MatrixSelection::validate($component->getChoicePolicy(), $value, $fail));
    }
}
