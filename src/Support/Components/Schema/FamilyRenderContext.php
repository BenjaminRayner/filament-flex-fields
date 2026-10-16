<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\Configuration\ConfigurationSchema;
use Bjanczak\FilamentFlexFields\Support\Locale\LocaleIdentifier;
use InvalidArgumentException;

/** Request-local context. Neither registry nor compiled cache owns tenant/record/configuration data. */
final readonly class FamilyRenderContext
{
    /** @var array<string, mixed> */
    public array $applicationDefaults;

    /** @var array<string, mixed> */
    public array $panelDefaults;

    /**
     * @param  array<string, mixed>  $applicationDefaults
     * @param  array<string, mixed>  $panelDefaults
     */
    public function __construct(
        public string $ownerKey,
        public string $statePathPrefix = '',
        public string $locale = 'en',
        public string $theme = 'light',
        array $applicationDefaults = [],
        array $panelDefaults = [],
        public ?string $userKey = null,
        public ?string $tenantId = null,
    ) {
        self::assertKey($ownerKey);
        if ($statePathPrefix !== '' && preg_match('/^[a-zA-Z0-9_:-]+(?:\.[a-zA-Z0-9_:-]+)*$/D', $statePathPrefix) !== 1) {
            throw new InvalidArgumentException('Invalid schema state path prefix.');
        }
        new LocaleIdentifier($locale);
        if (strlen($statePathPrefix) > 1024 || ! in_array($theme, ['light', 'dark', 'system'], true)) {
            throw new InvalidArgumentException('Invalid render locale, theme or state path.');
        }
        foreach ([$userKey, $tenantId] as $identity) {
            if ($identity !== null) {
                self::assertKey($identity);
            }
        }
        $this->applicationDefaults = ConfigurationSchema::copyArray($applicationDefaults);
        $this->panelDefaults = ConfigurationSchema::copyArray($panelDefaults);
    }

    public static function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 512 || preg_match('/[\x00-\x1f\x7f]/', $key) === 1 || ! mb_check_encoding($key, 'UTF-8')) {
            throw new InvalidArgumentException('Owner and instance keys must be bounded nonempty UTF-8.');
        }
    }

    public function statePath(string $slug): string
    {
        return $this->statePathPrefix === '' ? $slug : $this->statePathPrefix.'.'.$slug;
    }

    public function identity(string $component, string $key): string
    {
        self::assertKey($key);

        return 'fff-'.hash('sha256', json_encode([$this->ownerKey, $component, $key], JSON_THROW_ON_ERROR));
    }
}
