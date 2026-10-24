<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Currency\CurrencySelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Phone\PhoneCountrySelect;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Support\Assets\Rendering\LivewirePlanProjection;
use Illuminate\Support\HtmlString;

/** Request-local public catalog transport; never retains fields or selected state. */
final class CatalogPayloadTransport
{
    /** @var array<string, array{id: ?string, json: string}> */
    private array $payloads = [];

    private int $bytes = 0;

    /** @param list<array<string, mixed>> $options */
    public function select(string $key, array $options): HtmlString
    {
        $payload = $this->payload('select-catalog', $key, ['catalogKey' => $key, 'options' => $options]);
        if ($payload['id'] !== null) {
            return new HtmlString('<div hidden data-fff-control-configuration-id="'.$payload['id'].'" data-fff-select-catalog-ref="'.$key.'"></div>');
        }

        return new HtmlString('<template data-fff-select-catalog="'.$key.'">'.$payload['json'].'</template>');
    }

    /** @param array<string, mixed> $configuration
     * @return array<string, mixed>
     */
    public function configuration(SelectField $field, array $configuration): array
    {
        if (! in_array($field::class, [CountryField::class,
            TimezoneField::class,
            PhoneCountrySelect::class,
            CurrencySelect::class], true)) {
            return $configuration;
        }
        $local = array_intersect_key($configuration, array_flip(['state', 'statePath', 'initialState', 'initialOptionLabel',
            'initialOptionLabels', 'initialSelectedUserEntries', 'componentKey', 'livewireId', 'menuDomId',
            'listboxDomId', 'searchDomId', 'menuLabel', 'dependencyStatePaths']));
        $shared = array_diff_key($configuration, $local);
        $payload = $this->payload('select-configuration', hash('sha256', serialize($shared)), $shared);

        return $payload['id'] === null ? $configuration : ['presentationConfigurationId' => $payload['id'], ...$local];
    }

    /** @param list<array<string, mixed>> $countries
     * @return array{id: ?string, options: array<string, mixed>}
     */
    public function phone(array $countries, bool $share): array
    {
        if (! $share) {
            return ['id' => null, 'options' => ['countries' => $countries]];
        }
        $payload = $this->payload('phone-catalog', hash('sha256', serialize($countries)), ['countries' => $countries]);

        return ['id' => $payload['id'], 'options' => $payload['id'] === null
            ? ['countries' => $countries] : ['countriesConfigurationId' => $payload['id']]];
    }

    /** @param array<string, mixed> $options
     * @return array{id: ?string, json: string}
     */
    private function payload(string $component, string $key, array $options): array
    {
        $identity = $component.':'.$key.':'.(LivewirePlanProjection::dictionaryForRender() === null ? 'inline' : 'native');
        if (isset($this->payloads[$identity])) {
            return $this->payloads[$identity];
        }
        $definition = app(ControlConfigurationDictionary::class)->metadata($component, $options);
        $payload = ['id' => $definition?->id, 'json' => $definition === null
            ? json_encode($options['options'] ?? $options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) : ''];
        $bytes = strlen($payload['json']) + strlen($payload['id'] ?? '');
        if (count($this->payloads) < 512 && $this->bytes + $bytes <= 4 * 1024 * 1024) {
            $this->payloads[$identity] = $payload;
            $this->bytes += $bytes;
        }

        return $payload;
    }
}
