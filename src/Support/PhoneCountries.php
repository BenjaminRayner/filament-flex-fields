<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Select\Catalog\PickerCatalogState;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneCountries
{
    /**
     * @var array<string, string>
     */
    /** @var list<string>|null */
    private static ?array $supportedCodes = null;

    private const LANGUAGE_COUNTRY_MAP = [
        'pl' => 'PL',
        'de' => 'DE',
        'fr' => 'FR',
        'es' => 'ES',
        'it' => 'IT',
        'pt' => 'PT',
        'nl' => 'NL',
        'sv' => 'SE',
        'nb' => 'NO',
        'nn' => 'NO',
        'da' => 'DK',
        'fi' => 'FI',
        'cs' => 'CZ',
        'sk' => 'SK',
        'uk' => 'UA',
        'ru' => 'RU',
        'ja' => 'JP',
        'ko' => 'KR',
        'zh' => 'CN',
        'en' => 'US',
    ];

    /**
     * @return list<string>
     */
    public static function allSupportedCodes(): array
    {
        if (self::$supportedCodes === null) {
            $regions = PhoneNumberUtil::getInstance()->getSupportedRegions();
            sort($regions);
            self::$supportedCodes = $regions;
        }

        return self::$supportedCodes;
    }

    public static function flagUrl(string $countryCode): string
    {
        return CountryFlags::url($countryCode);
    }

    public static function name(string $countryCode, ?string $locale = null): string
    {
        return Countries::name($countryCode, $locale);
    }

    public static function dialCode(string $countryCode): string
    {
        $util = PhoneNumberUtil::getInstance();
        $callingCode = $util->getCountryCodeForRegion(strtoupper($countryCode));

        return '+'.$callingCode;
    }

    public static function formatNationalDisplay(string $national, string $countryCode): string
    {
        $digits = preg_replace('/\D/', '', $national) ?? '';

        if ($digits === '') {
            return '';
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($digits, strtoupper($countryCode));

            return $util->format($parsed, PhoneNumberFormat::NATIONAL);
        } catch (NumberParseException) {
            return $digits;
        }
    }

    /**
     * @param  list<string>|null  $allowed
     */
    public static function fromBrowserLocale(?array $allowed = null, ?string $locale = null): ?string
    {
        $locale = $locale ?? self::resolveBrowserLocale();

        if ($locale === null) {
            return null;
        }

        $allowedSet = array_flip(self::resolve($allowed));

        if (preg_match('/[-_](?<region>[A-Za-z]{2})$/', $locale, $matches)) {
            $region = strtoupper($matches['region']);

            if (isset($allowedSet[$region])) {
                return $region;
            }
        }

        $language = strtolower(substr($locale, 0, 2));
        $mapped = self::LANGUAGE_COUNTRY_MAP[$language] ?? null;

        if ($mapped !== null && isset($allowedSet[$mapped])) {
            return $mapped;
        }

        return null;
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @return list<string>
     */
    public static function resolve(?array $only = null, array $except = []): array
    {
        $supported = array_flip(self::allSupportedCodes());
        $except = array_map(strtoupper(...), $except);

        if ($only !== null) {
            $codes = [];

            foreach ($only as $code) {
                $normalized = strtoupper((string) $code);

                if (strlen($normalized) !== 2 || ! isset($supported[$normalized])) {
                    continue;
                }

                if (in_array($normalized, $except, true)) {
                    continue;
                }

                $codes[] = $normalized;
            }

            sort($codes);

            return array_values(array_unique($codes));
        }

        $codes = array_values(array_filter(
            self::allSupportedCodes(),
            fn (string $code): bool => ! in_array($code, $except, true),
        ));

        sort($codes);

        return $codes;
    }

    /**
     * @param  list<array{code: string, name: string, dial_code: string, flag_url: string}>  $countries
     * @return list<array{code: string, name: string, dial_code: string, flag_url: string}>
     */
    public static function sortWithPreferredFirst(array $countries, ?string $preferredCode): array
    {
        if ($preferredCode === null) {
            return $countries;
        }

        $preferredCode = strtoupper($preferredCode);
        $preferred = null;
        $rest = [];

        foreach ($countries as $country) {
            if ($country['code'] === $preferredCode) {
                $preferred = $country;

                continue;
            }

            $rest[] = $country;
        }

        if ($preferred === null) {
            return $countries;
        }

        return [$preferred, ...$rest];
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @return list<array{code: string, name: string, dial_code: string, flag_url: string}>
     */
    public static function metadata(?array $only = null, array $except = [], ?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $cache = RequestState::resolve(PickerCatalogState::class);
        $cache->touchLocale($locale);

        if (! isset($cache->phoneMetadata[$locale])) {
            $cache->phoneMetadata[$locale] = [];
        }

        $resolved = self::resolve($only, $except);
        $results = [];

        foreach ($resolved as $code) {
            if (! isset($cache->phoneMetadata[$locale][$code])) {
                $cache->phoneMetadata[$locale][$code] = [
                    'code' => $code,
                    'name' => self::name($code, $locale),
                    'dial_code' => self::dialCode($code),
                    'flag_url' => self::flagUrl($code),
                ];
            }

            $results[] = $cache->phoneMetadata[$locale][$code];
        }

        return $results;
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @return array<string, array{label: string, image: string, description: string}>
     */
    public static function selectOptions(?array $only = null, array $except = [], ?string $locale = null): array
    {
        $options = [];

        foreach (self::metadata($only, $except, $locale) as $country) {
            $options[$country['code']] = [
                'label' => $country['name'],
                'image' => $country['flag_url'],
                'description' => $country['dial_code'],
            ];
        }

        return $options;
    }

    protected static function resolveBrowserLocale(): ?string
    {
        if (! app()->runningInConsole()) {
            $preferred = request()->getPreferredLanguage();

            if (filled($preferred)) {
                return $preferred;
            }
        }

        $appLocale = app()->getLocale();

        return filled($appLocale) ? $appLocale : null;
    }
}
