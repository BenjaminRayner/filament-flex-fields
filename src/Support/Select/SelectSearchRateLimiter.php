<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Select;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Rate-limits Select / Tags / IconPicker Livewire search endpoints.
 *
 * Key prefers authenticated user id (Filament panel). Falls back to
 * {@see Request::ip()}, which respects Laravel TrustedProxies — never read
 * X-Forwarded-For manually (would trust spoofed headers behind a misconfigured LB).
 */
final class SelectSearchRateLimiter
{
    public function __construct(
        private readonly ?Request $request = null,
    ) {}

    public function tooManyAttempts(string $fieldName): bool
    {
        return RateLimiter::tooManyAttempts($this->key($fieldName), $this->maxAttempts());
    }

    public function hit(string $fieldName): void
    {
        RateLimiter::hit($this->key($fieldName), 60);
    }

    /**
     * @return bool true when the caller may proceed
     */
    public function attempt(string $fieldName): bool
    {
        if ($this->tooManyAttempts($fieldName)) {
            return false;
        }

        $this->hit($fieldName);

        return true;
    }

    /** Throws a transport-level failure, never a successful empty result page. */
    public function requireAttempt(string $fieldName): void
    {
        if ($this->attempt($fieldName)) {
            return;
        }

        throw new TooManyRequestsHttpException(
            max(1, RateLimiter::availableIn($this->key($fieldName))),
            'Select search is temporarily rate limited.',
        );
    }

    public function key(string $fieldName): string
    {
        $request = $this->request ?? request();
        $user = $request->user();
        $actor = $user?->getAuthIdentifier() ?? $request->ip() ?? 'guest';
        $field = $fieldName !== '' ? $fieldName : 'field';

        return 'fff-select-search:'.hash('sha256', serialize([
            Auth::getDefaultDriver(), $user !== null ? $user::class : 'guest', $actor, $field,
        ]));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('filament-flex-fields.select.search_rate_limit_per_minute', 60));
    }
}
