<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

use InvalidArgumentException;

/** Display-only artwork. It never becomes a native file or a persisted reference. */
final readonly class AvatarPlaceholder
{
    public function __construct(public string $url, public string $alt = '', public string $fit = 'cover', public ?string $referrerPolicy = 'no-referrer')
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($url === '' || strlen($url) > 16384 || preg_match('/[\x00-\x20]/', $url)
            || str_starts_with($url, '//') || ($scheme !== null && ! in_array(strtolower((string) $scheme), ['http', 'https'], true))
            || ! in_array($fit, ['cover', 'contain'], true)
            || ! in_array($referrerPolicy, [null, 'no-referrer', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin'], true)) {
            throw new InvalidArgumentException('Avatar placeholder requires a relative or HTTP(S) URL and supported presentation options.');
        }
    }
}
