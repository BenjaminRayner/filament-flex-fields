<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ChoiceLists\Validation;

use InvalidArgumentException;

final readonly class SelectionLimits
{
    public function __construct(public ?int $min, public ?int $max, public ?int $exact)
    {
        foreach ([$min, $max, $exact] as $count) {
            if ($count !== null && $count < 0) {
                throw new InvalidArgumentException('Selection limits cannot be negative.');
            }
        }
        if ($exact === null && $min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('Minimum selections exceeds maximum selections.');
        }
    }

    public function violation(int $count, bool $required): ?string
    {
        if ($this->exact !== null) {
            return $count === $this->exact ? null : 'exact';
        }
        if ($count < ($this->min ?? ($required ? 1 : 0))) {
            return 'min';
        }
        if ($this->max !== null && $count > $this->max) {
            return 'max';
        }

        return null;
    }

    public function countFor(string $violation): int
    {
        return match ($violation) {
            'exact' => $this->exact ?? 0,
            'max' => $this->max ?? 0,
            default => $this->min ?? 1,
        };
    }
}
