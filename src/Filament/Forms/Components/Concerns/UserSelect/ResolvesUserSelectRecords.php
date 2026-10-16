<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin UserSelect
 *
 * @phpstan-import-type NormalizedSelectOption from InteractsWithUserSelectPresentation
 * @phpstan-import-type NormalizedUserSelectOption from InteractsWithUserSelectPresentation
 */
trait ResolvesUserSelectRecords
{
    /**
     * @param  array<int|string, mixed>  $values
     * @return array<string, array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }>
     */
    public function resolveOptionShapesForValues(array $values): array
    {
        return $this->shapeResolver()->resolveOptionShapesForValues($values);
    }

    /**
     * @return array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }
     */
    public function recordToOptionArray(Model $record): array
    {
        return $this->recordMapper()->recordToOptionArray($record);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, string>
     */
    public function resolveOptionLabelsForValues(array $values): array
    {
        return $this->shapeResolver()->resolveOptionLabelsForValues($values);
    }
}
