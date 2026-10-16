<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\LinkPreview;

use Illuminate\Support\Facades\URL;

final class MetadataPresentation
{
    /** @param array{title?: string, description?: string, image?: string} $data
     * @return array{title?: string, description?: string, image?: string}
     */
    public static function forBrowser(array $data): array
    {
        if (isset($data['image'])) {
            $expires = now();
            $expires->setTimestamp(intdiv($expires->getTimestamp(), 300) * 300 + 600);
            $data['image'] = URL::temporarySignedRoute('filament-flex-fields.url-meta.image', $expires, ['url' => $data['image']], absolute: false);
        }

        return $data;
    }
}
