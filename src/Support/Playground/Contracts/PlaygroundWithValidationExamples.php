<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground\Contracts;

use Bjanczak\FilamentFlexFields\Support\Playground\DateTime\ValidationExample;

interface PlaygroundWithValidationExamples
{
    /** @return array<string, ValidationExample> */
    public function validationExamples(): array;
}
