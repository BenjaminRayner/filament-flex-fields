<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogBanner;
use Bjanczak\FilamentFlexFields\Support\Catalog\CatalogValue;
use InvalidArgumentException;

/** Remote data is plain metadata, never Blade, HTML, executable code or arbitrary CSS. */
final class PromotionDocument
{
    public const MAX_BYTES = 65536;

    public const MAX_BANNERS = 12;

    public const MAX_SPONSORS = 24;

    /** @return list<array<string, mixed>> */
    public static function decode(string $json): array
    {
        return self::parse($json)['banners'];
    }

    /** @return array<string, mixed> */
    public static function options(string $json): array
    {
        return self::parse($json)['options'];
    }

    /** Decode once and validate the complete document before replacing any cache fields. */
    /** @return array{banners: list<array<string, mixed>>, options: array<string, mixed>, sponsors: list<array<string, mixed>>} */
    public static function parse(string $json): array
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Promotion document exceeds 64 KiB.');
        }
        $document = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        if (! $document instanceof \stdClass || ($document->schemaVersion ?? null) !== 1 || array_diff(array_keys(get_object_vars($document)), ['schemaVersion', 'banners', 'slider', 'sponsors'])
            || ! isset($document->banners) || ! is_array($document->banners) || count($document->banners) > self::MAX_BANNERS) {
            throw new InvalidArgumentException('Invalid FLEX promotion document.');
        }
        $banners = [];
        foreach ($document->banners as $entry) {
            if (! $entry instanceof \stdClass) {
                throw new InvalidArgumentException('Invalid promotion entry.');
            }
            $data = get_object_vars($entry);
            if (array_diff(array_keys($data), ['id', 'title', 'description', 'url', 'action', 'image', 'backgroundImage', 'theme', 'backgroundPosition', 'overlayOpacity', 'eyebrow', 'alignment'])) {
                throw new InvalidArgumentException('Unknown promotion field.');
            }
            foreach (['title', 'description', 'action', 'eyebrow'] as $text) {
                if (($data[$text] ?? null) instanceof \stdClass) {
                    $data[$text] = get_object_vars($data[$text]);
                }
            }
            foreach (['title', 'description', 'action'] as $text) {
                if (! is_array($data[$text] ?? null) || ! isset($data[$text]['en'])) {
                    throw new InvalidArgumentException('Promotion text requires an English locale.');
                }
            }
            if (isset($data['eyebrow']) && (! is_array($data['eyebrow']) || ! isset($data['eyebrow']['en']))) {
                throw new InvalidArgumentException('Promotion eyebrow requires an English locale.');
            }
            $banner = new CatalogBanner(...$data);
            foreach ([$banner->image, $banner->backgroundImage] as $image) {
                if ($image !== null) {
                    CatalogValue::https($image);
                }
            }
            if (isset($banners[$banner->id])) {
                throw new InvalidArgumentException('Duplicate promotion identity.');
            }
            $banners[$banner->id] = get_object_vars($banner);
        }

        return ['banners' => array_values($banners), 'options' => self::validateOptions($document), 'sponsors' => self::validateSponsors($document)];
    }

    /** @return list<array<string, mixed>> */
    private static function validateSponsors(\stdClass $document): array
    {
        $entries = property_exists($document, 'sponsors') ? $document->sponsors : [];
        if (! is_array($entries) || count($entries) > self::MAX_SPONSORS) {
            throw new InvalidArgumentException('Sponsors must be a bounded list.');
        }
        $sponsors = [];
        $profiles = [];
        foreach ($entries as $entry) {
            if (! $entry instanceof \stdClass) {
                throw new InvalidArgumentException('Invalid sponsor entry.');
            }
            $sponsor = SponsorProfile::fromArray(get_object_vars($entry));
            $identity = rtrim($sponsor->profile, '/');
            if (isset($profiles[$identity])) {
                throw new InvalidArgumentException('Duplicate sponsor profile.');
            }
            $profiles[$identity] = true;
            $sponsors[] = $sponsor;
        }
        usort($sponsors, static fn (SponsorProfile $left, SponsorProfile $right): int => $left->rank->priority() <=> $right->rank->priority());

        return array_map(static fn (SponsorProfile $sponsor): array => $sponsor->toArray(), $sponsors);
    }

    /** Publisher-owned behavior, validated separately from localized presentation. */
    /** @return array<string, mixed> */
    private static function validateOptions(\stdClass $document): array
    {
        $options = isset($document->slider) ? $document->slider : new \stdClass;
        if (! $options instanceof \stdClass || array_diff(array_keys(get_object_vars($options)), ['autoplay', 'interval', 'arrows', 'dots'])) {
            throw new InvalidArgumentException('Invalid promotion slider options.');
        }
        $options = [...OfficialPromotions::sliderOptions(), ...get_object_vars($options)];
        foreach (['autoplay', 'arrows', 'dots'] as $flag) {
            if (! is_bool($options[$flag])) {
                throw new InvalidArgumentException('Slider flags must be boolean.');
            }
        }
        if (! is_int($options['interval']) || $options['interval'] < 3000 || $options['interval'] > 60000) {
            throw new InvalidArgumentException('Slider interval must be 3000–60000 milliseconds.');
        }

        return $options;
    }
}
