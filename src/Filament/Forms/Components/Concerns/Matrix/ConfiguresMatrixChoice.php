<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Matrix;

use Bjanczak\FilamentFlexFields\Support\Matrix\ChoicePolicy;
use Closure;
use Illuminate\Support\Arr;
use InvalidArgumentException;

trait ConfiguresMatrixChoice
{
    protected string|Closure $mode = 'radio';

    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $rows = [];

    /**
     * @var array<string | int, string | array<string, mixed>> | Closure
     */
    protected array|Closure $matrixColumns = [];

    /**
     * @var array<string | int, string> | Closure
     */
    protected array|Closure $columnIcons = [];

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $disabledRows = [];

    /**
     * @var array<string | int> | Closure
     */
    protected array|Closure $requiredRows = [];

    /**
     * @var array<string, list<string | int>> | Closure
     */
    protected array|Closure $disabledCells = [];

    /**
     * @var list<array{
     *     type: 'cell'|'row',
     *     row: string,
     *     column: ?string,
     *     when_row: string,
     *     when_columns: list<string>,
     * }>
     */
    protected array $conditionalDisableRules = [];

    protected string|Closure|null $color = 'primary';

    public function mode(string|Closure $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getMode(): string
    {
        $mode = (string) $this->evaluate($this->mode);

        if (! in_array($mode, ['radio', 'checkbox'], true)) {
            throw new InvalidArgumentException("Matrix choice mode [{$mode}] is not supported.");
        }

        return $mode;
    }

    public function isCheckboxMode(): bool
    {
        return $this->getMode() === 'checkbox';
    }

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $rows
     */
    public function rows(array|Closure $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @param  array<string | int, string | array<string, mixed>> | Closure  $columns
     */
    public function matrixColumns(array|Closure $columns): static
    {
        $this->matrixColumns = $columns;

        return $this;
    }

    /**
     * @param  array<string | int, string> | Closure  $icons
     */
    public function columnIcons(array|Closure $icons): static
    {
        $this->columnIcons = $icons;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function disabledRows(array|Closure $keys): static
    {
        $this->disabledRows = $keys;

        return $this;
    }

    /**
     * @param  array<string | int> | Closure  $keys
     */
    public function requiredRows(array|Closure $keys): static
    {
        $this->requiredRows = $keys;

        return $this;
    }

    /**
     * @param  array<string, list<string | int>> | Closure  $cells
     */
    public function disabledCells(array|Closure $cells): static
    {
        $this->disabledCells = $cells;

        return $this;
    }

    /**
     * Disable a cell when another row matches one of the given column keys.
     *
     *
     * @param  string|list<string>  $whenColumns
     */
    public function disableCellWhen(
        string $row,
        string $column,
        string $whenRow,
        string|array $whenColumns,
    ): static {
        $this->conditionalDisableRules[] = [
            'type' => 'cell',
            'row' => $row,
            'column' => $column,
            'when_row' => $whenRow,
            'when_columns' => array_values(array_map(strval(...), Arr::wrap($whenColumns))),
        ];

        return $this;
    }

    /**
     * Disable an entire row when another row matches one of the given column keys.
     *
     *
     * @param  string|list<string>  $whenColumns
     */
    public function disableRowWhen(
        string $row,
        string $whenRow,
        string|array $whenColumns,
    ): static {
        $this->conditionalDisableRules[] = [
            'type' => 'row',
            'row' => $row,
            'column' => null,
            'when_row' => $whenRow,
            'when_columns' => array_values(array_map(strval(...), Arr::wrap($whenColumns))),
        ];

        return $this;
    }

    /**
     * @return list<array{
     *     type: 'cell'|'row',
     *     row: string,
     *     column: ?string,
     *     when_row: string,
     *     when_columns: list<string>,
     * }>
     */
    public function getConditionalDisableRules(): array
    {
        return $this->conditionalDisableRules;
    }

    /**
     * @param array{type:string,row:string,column:?string,when_row:string,when_columns:list<string>} $rule
     * @param array<string,mixed> $state */
    public function matchesConditionalDisableRule(array $rule, array $state): bool
    {
        return ChoicePolicy::matches($rule, $state, $this->isCheckboxMode());
    }

    /**
     * @param array<string,mixed> $state */
    public function isRowConditionallyDisabled(string $rowKey, array $state): bool
    {
        return $this->getChoicePolicy()->conditional($rowKey, null, $state);
    }

    /**
     * @param array<string,mixed> $state */
    public function isCellConditionallyDisabled(string $rowKey, string $columnKey, array $state): bool
    {
        return $this->getChoicePolicy()->conditional($rowKey, $columnKey, $state);
    }

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->evaluate($this->color);
    }

    /**
     * @return array<string|int> */
    public function getDisabledRowKeys(): array
    {
        return Arr::wrap($this->evaluate($this->disabledRows));
    }

    /**
     * @return array<string|int> */
    public function getRequiredRowKeys(): array
    {
        return Arr::wrap($this->evaluate($this->requiredRows));
    }
}
