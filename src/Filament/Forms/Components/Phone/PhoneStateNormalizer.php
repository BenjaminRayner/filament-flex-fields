<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\PhoneCountries;
use Bjanczak\FilamentFlexFields\Support\PhoneNumberInsights;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/** Server-owned canonical state. Submitted E.164 and enrichment never override the parsed number. */
final class PhoneStateNormalizer
{
    public function __construct(private readonly PhoneField $field) {}

    /** @return array<string, mixed> */
    public function normalize(mixed $input): array
    {
        $country = $this->field->getDefaultCountryCode();
        $allowed = $this->field->getResolvedCountryCodes();
        $util = PhoneNumberUtil::getInstance();
        $parsed = null;
        if (is_string($input) && strlen($input) <= 256) {
            $national = trim($input);
            if ($national !== '') {
                try {
                    $parsed = $util->parse($national, $country);
                    $region = $util->getRegionCodeForNumber($parsed);
                    if ($region !== null && in_array($region, $allowed, true)) {
                        $country = $region;
                    } else {
                        $parsed = null;
                    }
                } catch (NumberParseException) {
                    $parsed = null;
                }
            }
        } else {
            $input = is_array($input) ? $input : [];
            $submittedCountry = $input['country'] ?? $country;
            $requested = is_string($submittedCountry) ? strtoupper($submittedCountry) : $country;
            $country = in_array($requested, $allowed, true) ? $requested : $country;
            $submittedNational = $input['national'] ?? '';
            $national = is_string($submittedNational) && strlen($submittedNational) <= 256 ? $submittedNational : '';
        }
        $national = PhoneNumberInsights::nationalDigits($national);
        $state = ['country' => $country, 'national' => $national, 'e164' => ''];
        if ($national === '') {
            return $state;
        }
        try {
            $parsed ??= $util->parse($national, $country);
            if (! $util->isValidNumber($parsed)) {
                return [...$state, 'e164' => PhoneCountries::dialCode($country).$national];
            }

            return [
                'country' => $country,
                'national' => PhoneNumberInsights::formatNationalDisplay($parsed, $util, $this->field->storesNationalDigitsOnly()),
                'e164' => $util->format($parsed, PhoneNumberFormat::E164),
                ...PhoneNumberInsights::enrich($parsed, $util, $this->field->getIncludedFormats(), $this->field->getIncludedMetadata(), $this->field->getLocale()),
            ];
        } catch (NumberParseException) {
            return [...$state, 'e164' => PhoneCountries::dialCode($country).$national];
        }
    }

    /** @param array<string, mixed> $state */
    public function validationMessage(array $state): ?string
    {
        if (($state['national'] ?? '') === '') {
            return null;
        }
        $types = $this->field->getAllowedTypes();
        $key = null;
        try {
            $util = PhoneNumberUtil::getInstance();
            $number = $util->parse((string) ($state['e164'] ?: $state['national']), (string) $state['country']);
            if (! $util->isValidNumber($number)) {
                $key = 'invalid';
            } elseif ($this->field->shouldValidateForRegion() && ! $util->isValidNumberForRegion($number, (string) $state['country'])) {
                $key = 'invalid_for_region';
            } elseif ($types !== null && ! PhoneNumberInsights::typeIsAllowed($util->getNumberType($number), $types, $this->field->usesStrictTypes())) {
                $key = match (true) {
                    $this->field->isMobileOnly() && ! $this->field->hasExplicitPhoneTypes() => 'mobile_only',
                    $this->field->isFixedLineOnly() && ! $this->field->hasExplicitPhoneTypes() => 'fixed_line_only',
                    default => 'type_not_allowed',
                };
            }
        } catch (NumberParseException) {
            $key = 'invalid';
        }

        return $key === null ? null : OptionText::required(__('filament-flex-fields::default.validation.phone.'.$key), 'phone validation message');
    }
}
