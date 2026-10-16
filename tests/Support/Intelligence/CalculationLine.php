<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Intelligence;

use Illuminate\Database\Eloquent\Model;

final class CalculationLine extends Model
{
    public $timestamps = false;

    protected $table = 'fff_calculation_lines';

    protected $fillable = ['price', 'quantity', 'total'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'quantity' => 'integer', 'total' => 'integer'];
    }
}
