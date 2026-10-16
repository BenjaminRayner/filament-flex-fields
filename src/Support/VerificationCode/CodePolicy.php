<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\VerificationCode;

use InvalidArgumentException;

/** Shared server rules for configuration, hydration, validation and callbacks. */
final readonly class CodePolicy
{
    public function __construct(public int $length, public string $characters)
    {
        if ($length < 1 || $length > 16) {
            throw new InvalidArgumentException('Verification code length must be between 1 and 16.');
        }
        if (! in_array($characters, ['numeric', 'alphanumeric'], true)) {
            throw new InvalidArgumentException('Verification code supports numeric or alphanumeric characters.');
        }
    }

    public function characterClass(): string
    {
        return $this->characters === 'numeric' ? '[0-9]' : '[A-Za-z0-9]';
    }

    public function pattern(bool $complete = true): string
    {
        return '/\A'.$this->characterClass().($complete ? '{'.$this->length.'}' : '*').'\z/D';
    }

    public function accepts(mixed $value): bool
    {
        return is_string($value) && strlen($value) === $this->length && preg_match($this->pattern(), $value) === 1;
    }

    public function normalize(string $value): string
    {
        return strtoupper(substr(preg_replace('/[^'.($this->characters === 'numeric' ? '0-9' : 'A-Za-z0-9').']/', '', $value) ?? '', 0, $this->length));
    }

    /** @return list<int> */
    public function groups(mixed $groups): array
    {
        if ($groups === null || $groups === []) {
            return [$this->length];
        }
        if (! is_array($groups) || ! array_is_list($groups) || count($groups) > $this->length) {
            throw new InvalidArgumentException('Verification code groups must be a list of positive integers.');
        }
        foreach ($groups as $size) {
            if (! is_int($size) || $size < 1 || $size > $this->length) {
                throw new InvalidArgumentException('Verification code group sizes must be positive integers.');
            }
        }
        if (array_sum($groups) !== $this->length) {
            throw new InvalidArgumentException('Verification code groups must sum to its length.');
        }

        return $groups;
    }
}
