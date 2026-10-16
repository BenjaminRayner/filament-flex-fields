<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Barcode\Validation;

use Bjanczak\FilamentFlexFields\Enums\BarcodeFormat;
use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeStateNormalizer;
use Bjanczak\FilamentFlexFields\Support\Barcode\BarcodeValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BarcodeRule implements ValidationRule
{
    public bool $implicit = true;

    /** @param list<BarcodeFormat> $formats */
    public function __construct(
        private readonly array $formats,
        private readonly bool $structured,
        private readonly bool $checksum,
        private readonly Closure|string|null $custom = null,
        private readonly bool $required = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! BarcodeStateNormalizer::isValidShape($value) || (! $this->structured && is_array($value))) {
            $fail('filament-flex-fields::default.barcode_scanner.validation.invalid')->translate();

            return;
        }
        $normalized = BarcodeStateNormalizer::normalize($value, $this->structured);
        if ($normalized === null) {
            if ($this->required) {
                $fail('validation.required')->translate();
            }

            return;
        }
        $format = is_string($value) || $normalized['format'] === null ? null : BarcodeFormat::from($normalized['format']);
        $message = BarcodeValidator::validateValue($normalized['value'], $this->formats, $this->checksum, $format);
        if ($message !== null) {
            $fail($message);

            return;
        }
        if ($this->custom instanceof Closure) {
            ($this->custom)($attribute, $normalized['value'], $fail);
        } elseif (is_string($this->custom)) {
            $validator = validator(['barcode' => $normalized['value']], ['barcode' => $this->custom]);
            if ($validator->fails()) {
                $fail($validator->errors()->first('barcode'));
            }
        }
    }
}
