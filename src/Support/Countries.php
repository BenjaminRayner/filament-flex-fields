<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Bjanczak\FilamentFlexFields\Support\Runtime\RequestState;
use Bjanczak\FilamentFlexFields\Support\Select\Catalog\PickerCatalogState;
use libphonenumber\PhoneNumberUtil;

class Countries
{
    /** Reserved or user-assigned regions, explicitly enabled rather than presented as ISO entries. */
    public const ADDITIONAL_REGION_CODES = ['AC', 'DG', 'EA', 'IC', 'TA', 'XK'];

    /**
     * @var list<string>|null
     */
    private static ?array $allCodesCache = null;

    /**
     * @var array<string, int>|null
     */
    private static ?array $phoneRegionsCache = null;

    /**
     * @var array<string, string>
     */
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
    public static function allCodes(): array
    {
        if (self::$allCodesCache === null) {
            /** @var list<string> $codes */
            $codes = require __DIR__.'/../Data/iso3166-alpha2-codes.php';
            self::$allCodesCache = $codes;
        }

        return self::$allCodesCache;
    }

    public static function flagUrl(string $countryCode): string
    {
        return CountryFlags::url($countryCode);
    }

    public static function name(string $countryCode, ?string $locale = null): string
    {
        $code = strtoupper($countryCode);
        $locale ??= app()->getLocale();
        $cache = RequestState::resolve(PickerCatalogState::class);
        $cache->touchLocale($locale);

        if (isset($cache->countryNames[$locale][$code])) {
            return $cache->countryNames[$locale][$code];
        }

        $translationKey = "filament-flex-fields::countries.{$code}";
        $translated = app('translator')->get($translationKey, [], $locale, false);

        if (is_string($translated) && $translated !== $translationKey) {
            return $cache->countryNames[$locale][$code] = $translated;
        }

        $fallback = function_exists('locale_get_display_region') ? locale_get_display_region('-'.$code, $locale) : null;

        if (is_string($fallback) && $fallback !== '' && $fallback !== $code) {
            return $cache->countryNames[$locale][$code] = $fallback;
        }

        $english = app('translator')->get($translationKey, [], 'en', false);

        return $cache->countryNames[$locale][$code] = is_string($english) && $english !== $translationKey ? $english : $code;
    }

    public static function dialCode(string $countryCode): ?string
    {
        $code = strtoupper($countryCode);

        if (self::$phoneRegionsCache === null) {
            self::$phoneRegionsCache = array_flip(PhoneNumberUtil::getInstance()->getSupportedRegions());
        }

        if (! isset(self::$phoneRegionsCache[$code])) {
            return null;
        }

        return PhoneCountries::dialCode($code);
    }

    /**
     * @param  list<string>|null  $allowed
     */
    public static function fromBrowserLocale(?array $allowed = null, ?string $locale = null): ?string
    {
        if ($locale !== null) {
            return self::matchLocaleToCountry($locale, $allowed);
        }

        if (! app()->runningInConsole()) {
            $languages = request()->getLanguages();

            foreach ($languages as $language) {
                $matched = self::matchLocaleToCountry($language, $allowed);

                if ($matched !== null) {
                    return $matched;
                }
            }
        }

        $appLocale = app()->getLocale();

        if (filled($appLocale)) {
            return self::matchLocaleToCountry($appLocale, $allowed);
        }

        return null;
    }

    /**
     * @param  list<string>  $languages
     * @param  list<string>|null  $allowed
     */
    public static function fromBrowserLanguages(array $languages, ?array $allowed = null): ?string
    {
        foreach ($languages as $language) {
            $matched = self::matchLocaleToCountry((string) $language, $allowed);

            if ($matched !== null) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function browserLanguageCountryMap(): array
    {
        return self::LANGUAGE_COUNTRY_MAP;
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @param  list<string>  $additional
     * @return list<string>
     */
    public static function resolve(?array $only = null, array $except = [], array $additional = []): array
    {
        $additional = array_values(array_intersect(self::ADDITIONAL_REGION_CODES, array_map(strtoupper(...), $additional)));
        $catalog = [...self::allCodes(), ...$additional];
        $supported = array_flip($catalog);
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
            $catalog,
            fn (string $code): bool => ! in_array($code, $except, true),
        ));

        sort($codes);

        return $codes;
    }

    /**
     * @param  list<array{code: string, name: string, dial_code: string|null, flag_url: string}>  $countries
     * @return list<array{code: string, name: string, dial_code: string|null, flag_url: string}>
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
     * @param  list<string>  $additional
     * @return list<array{code: string, name: string, dial_code: string|null, flag_url: string}>
     */
    public static function metadata(?array $only = null, array $except = [], ?string $locale = null, array $additional = []): array
    {
        $locale ??= app()->getLocale();
        $cache = RequestState::resolve(PickerCatalogState::class);
        $cache->touchLocale($locale);

        if (! isset($cache->countryMetadata[$locale])) {
            $cache->countryMetadata[$locale] = [];
        }

        $resolved = self::resolve($only, $except, $additional);
        $results = [];

        foreach ($resolved as $code) {
            if (! isset($cache->countryMetadata[$locale][$code])) {
                $cache->countryMetadata[$locale][$code] = [
                    'code' => $code,
                    'name' => self::name($code, $locale),
                    'dial_code' => self::dialCode($code),
                    'flag_url' => self::flagUrl($code),
                ];
            }

            $results[] = $cache->countryMetadata[$locale][$code];
        }

        return $results;
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>  $except
     * @param  list<string>  $additional
     * @return array<string, array{label: string, image: string, description: string}>
     */
    public static function selectOptions(?array $only = null, array $except = [], ?string $locale = null, array $additional = []): array
    {
        $options = [];

        foreach (self::metadata($only, $except, $locale, $additional) as $country) {
            $options[$country['code']] = [
                'label' => $country['name'],
                'image' => $country['flag_url'],
                'description' => $country['dial_code'] ?? '',
            ];
        }

        return $options;
    }

    /**
     * @param  list<string>|null  $allowed
     */
    protected static function matchLocaleToCountry(string $locale, ?array $allowed = null): ?string
    {
        $allowedSet = array_flip(self::resolve($allowed, additional: $allowed === null ? [] : self::ADDITIONAL_REGION_CODES));

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
}
