<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Audio;

use Bjanczak\FilamentFlexFields\Support\Security\SafeMediaUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AudioSourceRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value) || strlen($value) > 8192 || SafeMediaUrl::sanitize($value) === null) {
            $message = __('filament-flex-fields::default.validation.media.invalid_url');
            $fail(is_string($message) ? $message : 'Invalid audio URL.');
        }
    }
}
