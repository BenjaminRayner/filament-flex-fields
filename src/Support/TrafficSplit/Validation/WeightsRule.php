<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\TrafficSplit\Validation;

use Bjanczak\FilamentFlexFields\Support\TrafficSplit\Distribution;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class WeightsRule implements ValidationRule
{
    public function __construct(private readonly int $count, private readonly int $minimum, private readonly bool $linkedCountValid = true)
    {
        Distribution::assertBounds($count, $minimum);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $weights = Distribution::integers($value);
        if (! $this->linkedCountValid || $weights === null || count($weights) !== $this->count) {
            $fail('validation.array')->translate();

            return;
        }
        if (min($weights) < $this->minimum) {
            $fail('validation.between.numeric')->translate(['min' => $this->minimum, 'max' => 100]);

            return;
        }
        if (array_sum($weights) !== 100) {
            $fail('validation.size.numeric')->translate(['size' => 100]);
        }
    }
}
