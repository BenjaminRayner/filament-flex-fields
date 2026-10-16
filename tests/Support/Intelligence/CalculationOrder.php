<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Intelligence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CalculationOrder extends Model
{
    public $timestamps = false;

    protected $table = 'fff_calculation_orders';

    protected $fillable = ['price', 'quantity', 'total', 'lines'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'quantity' => 'integer', 'total' => 'integer', 'lines' => 'array'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CalculationLine::class, 'order_id');
    }
}
