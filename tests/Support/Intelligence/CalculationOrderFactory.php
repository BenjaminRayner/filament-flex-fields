<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Intelligence;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CalculationOrder> */
final class CalculationOrderFactory extends Factory
{
    protected $model = CalculationOrder::class;

    public function definition(): array
    {
        return ['price' => 120, 'quantity' => 4, 'total' => 480, 'lines' => []];
    }
}
