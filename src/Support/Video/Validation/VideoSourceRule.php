<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Video\Validation;

use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class VideoSourceRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || SafeMediaUrl::sanitize($value) === null) {
            $fail('filament-flex-fields::default.validation.media.invalid_url')->translate();
        }
    }
}
