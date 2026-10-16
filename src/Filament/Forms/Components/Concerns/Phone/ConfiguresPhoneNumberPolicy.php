<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Phone;

use Bjanczak\FilamentFlexFields\Enums\PhoneNationalFormat;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PhoneField;
use Bjanczak\FilamentFlexFields\Support\PhoneNumberInsights;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Closure;
use InvalidArgumentException;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Phone type, canonical display and enrichment policy.
 *
 * @mixin PhoneField
 */
trait ConfiguresPhoneNumberPolicy
{
    protected bool|Closure $mobileOnly = false;

    protected bool|Closure $fixedLineOnly = false;

    /**
     * Explicit libphonenumber types. When set, overrides mobileOnly/fixedLineOnly sugar.
     *
     * @var list<PhoneNumberType|string|int>|Closure|null
     */
    protected array|Closure|null $allowTypes = null;

    protected bool|Closure $strictTypes = false;

    protected bool|Closure $validateForRegion = false;

    protected PhoneNationalFormat|string|Closure $nationalFormat = PhoneNationalFormat::National;

    /**
     * Optional dehydrated format keys: international, rfc3966.
     *
     * @var list<string>|Closure
     */
    protected array|Closure $includeFormats = [];

    /**
     * Optional dehydrated metadata keys: carrier, geo, timezones, type.
     *
     * @var list<string>|Closure
     */
    protected array|Closure $includeMetadata = [];

    public function mobileOnly(bool|Closure $condition = true): static
    {
        $this->mobileOnly = $condition;

        return $this;
    }

    public function fixedLineOnly(bool|Closure $condition = true): static
    {
        $this->fixedLineOnly = $condition;

        return $this;
    }

    /**
     * Restrict accepted libphonenumber number types.
     *
     * @param  list<PhoneNumberType|string|int>|Closure|null  $types
     */
    public function allowTypes(array|Closure|null $types): static
    {
        $this->allowTypes = $types;

        return $this;
    }

    /**
     * When true, FIXED_LINE_OR_MOBILE is not auto-accepted alongside MOBILE / FIXED_LINE.
     */
    public function strictTypes(bool|Closure $condition = true): static
    {
        $this->strictTypes = $condition;

        return $this;
    }

    /**
     * Use {@see PhoneNumberUtil::isValidNumberForRegion()} against the selected country.
     */
    public function validateForRegion(bool|Closure $condition = true): static
    {
        $this->validateForRegion = $condition;

        return $this;
    }

    public function nationalFormat(PhoneNationalFormat|string|Closure $format): static
    {
        $this->nationalFormat = $format;

        return $this;
    }

    /**
     * Digits-only `national` after successful normalize (opt-in; default remains NATIONAL).
     */
    public function nationalDigitsOnly(bool|Closure $condition = true): static
    {
        if ($condition instanceof Closure) {
            $this->nationalFormat = static function (PhoneField $component) use ($condition): PhoneNationalFormat {
                return ((bool) $component->evaluate($condition))
                    ? PhoneNationalFormat::Digits
                    : PhoneNationalFormat::National;
            };

            return $this;
        }

        $this->nationalFormat = ((bool) $condition)
            ? PhoneNationalFormat::Digits
            : PhoneNationalFormat::National;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $formats  `international`, `rfc3966`
     */
    public function includeFormats(array|Closure $formats): static
    {
        $this->includeFormats = $formats;

        return $this;
    }

    /**
     * @param  list<string>|Closure  $metadata  `carrier`, `geo`, `timezones`, `type`
     */
    public function includeMetadata(array|Closure $metadata): static
    {
        $this->includeMetadata = $metadata;

        return $this;
    }

    public function isMobileOnly(): bool
    {
        return (bool) $this->evaluate($this->mobileOnly);
    }

    public function isFixedLineOnly(): bool
    {
        return (bool) $this->evaluate($this->fixedLineOnly);
    }

    /**
     * @return list<PhoneNumberType>|null
     */
    public function getAllowedTypes(): ?array
    {
        $explicit = $this->evaluate($this->allowTypes);

        if ($explicit !== null && ! is_array($explicit)) {
            throw new InvalidArgumentException('Phone types must resolve to an array or null.');
        }
        if (is_array($explicit) && $explicit !== []) {
            foreach ($explicit as $type) {
                if (! $type instanceof PhoneNumberType && ! is_int($type) && ! is_string($type)) {
                    throw new InvalidArgumentException('Phone types must use supported enum, integer or string identities.');
                }
            }

            return PhoneNumberInsights::normalizeAllowedTypes($explicit);
        }

        if ($this->isMobileOnly() && $this->isFixedLineOnly()) {
            throw new InvalidArgumentException('PhoneField cannot require both mobileOnly() and fixedLineOnly().');
        }

        if ($this->isMobileOnly()) {
            return [PhoneNumberType::MOBILE];
        }

        if ($this->isFixedLineOnly()) {
            return [PhoneNumberType::FIXED_LINE];
        }

        return null;
    }

    public function hasExplicitPhoneTypes(): bool
    {
        $types = $this->evaluate($this->allowTypes);

        return is_array($types) && $types !== [];
    }

    public function usesStrictTypes(): bool
    {
        return (bool) $this->evaluate($this->strictTypes);
    }

    public function shouldValidateForRegion(): bool
    {
        return (bool) $this->evaluate($this->validateForRegion);
    }

    public function getNationalFormat(): PhoneNationalFormat
    {
        $format = $this->evaluate($this->nationalFormat);

        if ($format instanceof PhoneNationalFormat) {
            return $format;
        }

        return PhoneNationalFormat::tryFrom(strtolower(PickerConfiguration::text($format, 'nationalFormat')))
            ?? throw new InvalidArgumentException("Unsupported phone national format [{$format}].");
    }

    public function storesNationalDigitsOnly(): bool
    {
        return $this->getNationalFormat() === PhoneNationalFormat::Digits;
    }

    /**
     * @return list<string>
     */
    public function getIncludedFormats(): array
    {
        return $this->resolvePhoneEnrichmentKeys($this->evaluate($this->includeFormats), ['international', 'rfc3966']);
    }

    /**
     * @return list<string>
     */
    public function getIncludedMetadata(): array
    {
        return $this->resolvePhoneEnrichmentKeys($this->evaluate($this->includeMetadata), ['carrier', 'geo', 'timezones', 'type']);
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function resolvePhoneEnrichmentKeys(mixed $values, array $allowed): array
    {
        $keys = array_map('strtolower', IdentifierList::normalize($values));
        foreach ($keys as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Unsupported phone enrichment [{$key}].");
            }
        }

        return array_values(array_unique($keys));
    }
}
