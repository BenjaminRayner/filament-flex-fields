<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Catalog\Promotions;

final readonly class PromotionSnapshot
{
    /**
     * Null means no usable remote document; [] intentionally hides every promotion.
     *
     * @param  list<array<string, mixed>>|null  $banners
     * @param  array<string, mixed>|null  $options
     * @param  list<array<string, mixed>>  $sponsors
     */
    public function __construct(public ?array $banners = null, public bool $refreshDue = false, public ?array $options = null, public array $sponsors = []) {}
}
