<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\FlexFileUpload;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarPlaceholder;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use InvalidArgumentException;
use Throwable;

/** @mixin BaseFileUpload */
trait FlexFileUploadAvatar
{
    protected AvatarPlaceholder|string|Closure|null $flexDefaultAvatar = null;

    protected ?Closure $flexAvatarFallback = null;

    protected bool|Closure $flexDefaultAvatarEnabled = true;

    public function defaultAvatar(AvatarPlaceholder|string|Closure|null $provider): static
    {
        $this->flexDefaultAvatar = $provider;

        return $this;
    }

    public function defaultAvatarUsing(?Closure $provider): static
    {
        return $this->defaultAvatar($provider);
    }

    public function defaultAvatarEnabled(bool|Closure $condition = true): static
    {
        $this->flexDefaultAvatarEnabled = $condition;

        return $this;
    }

    public function defaultAvatarFallbackUsing(?Closure $provider): static
    {
        $this->flexAvatarFallback = $provider;

        return $this;
    }

    public function getDefaultAvatar(): ?AvatarPlaceholder
    {
        if (! $this->isAvatar() || ! $this->evaluate($this->flexDefaultAvatarEnabled)) {
            return null;
        }
        try {
            return $this->resolveAvatarPlaceholder($this->evaluate($this->flexDefaultAvatar));
        } catch (Throwable $error) {
            UploadNotifications::report($error);
            try {
                return $this->resolveAvatarPlaceholder($this->evaluate($this->flexAvatarFallback, ['error' => $error]));
            } catch (Throwable $fallbackError) {
                UploadNotifications::report($fallbackError);

                return null;
            }
        }
    }

    private function resolveAvatarPlaceholder(mixed $value): ?AvatarPlaceholder
    {
        if ($value === null || $value instanceof AvatarPlaceholder) {
            return $value;
        }
        if (is_string($value)) {
            return new AvatarPlaceholder($value);
        }
        throw new InvalidArgumentException('Default avatar provider must return a URL, AvatarPlaceholder or null.');
    }
}
