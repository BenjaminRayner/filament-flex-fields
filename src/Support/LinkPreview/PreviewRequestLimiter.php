<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\LinkPreview;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class PreviewRequestLimiter
{
    public static function check(Request $request, string $purpose = 'metadata'): void
    {
        $max = max(1, min(600, (int) config('filament-flex-fields.link_preview.rate_limit_per_minute', 30)));
        $identity = $request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'anonymous';
        $key = 'fff-link-preview:'.$purpose.':'.hash('sha256', (string) $identity);
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
    }
}
