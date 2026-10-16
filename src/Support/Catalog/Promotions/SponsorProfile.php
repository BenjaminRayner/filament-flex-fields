<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use Bjanczak\FilamentFlexFields\Support\Translations;
use InvalidArgumentException;

final readonly class SponsorProfile
{
    public function __construct(public string $name, public string $profile, public string $avatar, public SponsorRank $rank)
    {
        CatalogValue::text($name, 80);
        CatalogValue::https($profile);
        CatalogValue::https($avatar);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (array_diff(array_keys($data), ['name', 'profile', 'avatar', 'rank']) || count($data) !== 4) {
            throw new InvalidArgumentException('Invalid sponsor fields.');
        }
        foreach ($data as $value) {
            if (! is_string($value)) {
                throw new InvalidArgumentException('Sponsor fields must be strings.');
            }
        }
        $rank = SponsorRank::tryFrom($data['rank']) ?? throw new InvalidArgumentException('Unknown sponsor rank.');

        return new self($data['name'], $data['profile'], $data['avatar'], $rank);
    }

    /** @return array{name: string, profile: string, avatar: string, rank: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'profile' => $this->profile, 'avatar' => $this->avatar, 'rank' => $this->rank->value];
    }

    /** @return array{name: string, profile: string, avatar: string, rank: string, initials: string, rankLabel: string} */
    public function presentation(): array
    {
        $words = preg_split('/\s+/u', trim($this->name)) ?: [$this->name];
        $initials = mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr($words[array_key_last($words)], 0, 1) : '');

        return [...$this->toArray(), 'initials' => mb_strtoupper($initials), 'rankLabel' => Translations::get('filament-flex-fields::catalog.sponsor_ranks.'.$this->rank->value)];
    }
}
