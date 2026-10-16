<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Registration;

use DateTimeImmutable;
use InvalidArgumentException;

/** An explicit compatibility deadline; never disables a component by wall-clock time. */
final readonly class ComponentDeprecation
{
    public function __construct(
        public string $replacement,
        public string $removalNotBefore,
        public string $message,
    ) {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $replacement) !== 1 || strlen($replacement) > 96) {
            throw new InvalidArgumentException('A deprecation must identify a replacement component.');
        }
        if (strlen($removalNotBefore) !== 10 || preg_match('/^(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $removalNotBefore) !== 1) {
            throw new InvalidArgumentException('A compatibility deadline must be an exact ISO calendar date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $removalNotBefore);
        if ($date === false || $date->format('Y-m-d') !== $removalNotBefore) {
            throw new InvalidArgumentException('A compatibility deadline must be an exact ISO calendar date.');
        }
        if (trim($message) === '' || strlen($message) > 512 || ! mb_check_encoding($message, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f<>]/', $message)) {
            throw new InvalidArgumentException('A deprecation message must be bounded plain text.');
        }
    }

    /** @return array{replacement: string, removalNotBefore: string, message: string} */
    public function export(): array
    {
        return ['replacement' => $this->replacement, 'removalNotBefore' => $this->removalNotBefore, 'message' => $this->message];
    }
}
