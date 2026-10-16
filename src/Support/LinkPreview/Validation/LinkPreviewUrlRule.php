<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\LinkPreview\Validation;

use Bjanczak\FilamentFlexFields\Support\LinkPreview\LinkPreviewUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class LinkPreviewUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && LinkPreviewUrl::normalize($value) === null) {
            $fail('validation.url')->translate();
        }
    }
}
