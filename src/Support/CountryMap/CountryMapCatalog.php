<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\CountryMap;

use Bjanczak\FilamentFlexFields\Support\Countries;
use Bjanczak\FilamentFlexFields\Support\Select\Configuration\PickerConfiguration;
use Bjanczak\FilamentFlexFields\Support\Select\Data\IdentifierList;
use Collator;
use InvalidArgumentException;

/** Immutable selection authority; never retains a field, model or callback. */
final readonly class CountryMapCatalog
{
    /**
     * @param  list<string>  $codes
     * @param  array<string, list<string>>  $groups
     */
    private function __construct(public array $codes, public array $groups) {}

    public static function resolve(mixed $codes, mixed $groups = []): self
    {
        $codes = $codes === null ? Countries::allCodes() : IdentifierList::normalize($codes, uppercase: true);
        $known = array_fill_keys(Countries::allCodes(), true);
        foreach ($codes as $code) {
            if (! isset($known[$code])) {
                throw new InvalidArgumentException('CountryMap requires ISO alpha-2 codes.');
            }
        }
        if (! is_array($groups) || count($groups) > 32) {
            throw new InvalidArgumentException('CountryMap supports at most 32 groups.');
        }
        $allowed = array_fill_keys($codes, true);
        $resolved = [];
        foreach ($groups as $label => $members) {
            $label = trim(PickerConfiguration::text($label, 'country group label'));
            if ($label === '' || strlen($label) > 256) {
                throw new InvalidArgumentException('Invalid country group label.');
            }
            if (isset($resolved[$label])) {
                throw new InvalidArgumentException('Country group labels must remain unique after normalization.');
            }
            $members = IdentifierList::normalize($members, uppercase: true);
            foreach ($members as $code) {
                if (! isset($allowed[$code])) {
                    throw new InvalidArgumentException('Group country is outside the allowed country set.');
                }
            }
            $resolved[$label] = $members;
        }

        return new self($codes, $resolved);
    }

    /** @return array<string, array{label: string, image: string}> */
    public function options(string $locale): array
    {
        $options = array_map(static fn (array $country): array => ['label' => $country['label'], 'image' => $country['image']], Countries::selectOptions($this->codes));
        $collator = class_exists(Collator::class) ? new Collator($locale) : null;
        uasort($options, static function (array $a, array $b) use ($collator): int {
            $order = $collator?->compare($a['label'], $b['label']);

            return is_int($order) && $order !== 0 ? $order : strnatcasecmp($a['label'], $b['label']);
        });

        return $options;
    }

    public function accepts(mixed $state, bool $multiple): bool
    {
        if ($state === null || $state === []) {
            return true;
        }
        $values = $multiple ? $state : [$state];
        if (! is_array($values) || ! array_is_list($values) || count($values) > count($this->codes)) {
            return false;
        }
        $allowed = array_fill_keys($this->codes, true);
        $seen = [];
        foreach ($values as $code) {
            if (! is_string($code) || ! isset($allowed[$code]) || isset($seen[$code])) {
                return false;
            }
            $seen[$code] = true;
        }

        return true;
    }
}
