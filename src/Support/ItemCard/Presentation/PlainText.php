<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\ItemCard\Presentation;

use Illuminate\Contracts\Support\Htmlable;

final class PlainText
{
    public static function from(string|Htmlable|null $value): string
    {
        return html_entity_decode(strip_tags($value instanceof Htmlable ? $value->toHtml() : ($value ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
