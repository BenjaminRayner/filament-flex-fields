<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

/** Publisher-owned transport policy. Never read advertisement settings from host config or env. */
final readonly class PromotionFeed
{
    public const SPONSORSHIP = 'https://github.com/sponsors/janczakb';

    public const WEBSITE = 'https://github.com/janczakb/filament-flex-fields';

    public string $url;

    public string $key;

    public int $freshSeconds;

    public int $staleSeconds;

    public int $retrySeconds;

    public int $timeoutSeconds;

    private function __construct()
    {
        $this->url = 'https://raw.githubusercontent.com/janczakb/filament-flex-fields/main/.github/ads.json';
        $this->freshSeconds = 900;
        $this->staleSeconds = 86400;
        $this->retrySeconds = 300;
        $this->timeoutSeconds = 3;
        $this->key = 'flex.catalog.promotions.v4.'.hash('sha256', $this->url);
    }

    public static function official(): self
    {
        return new self;
    }
}
