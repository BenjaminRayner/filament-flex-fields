<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Build;

use RuntimeException;

final readonly class BuildValidationReport
{
    /** @param list<array{code: string, subject: string, message: string}> $errors */
    public function __construct(public ?string $release, public int $artifacts, public int $packages, public array $errors, public int $errorCount) {}

    public function assertValid(): void
    {
        if ($this->errorCount !== 0) {
            $first = $this->errors[0];
            throw new RuntimeException(sprintf('FLEX build preflight failed (%d errors): %s: %s. Run fff:assets:doctor or php scripts/assets/validate-build.php.',
                $this->errorCount, $first['subject'], $first['message']));
        }
    }

    /** @return array{valid: bool, release: ?string, artifacts: int, packages: int, errorCount: int, errors: list<array{code: string, subject: string, message: string}>} */
    public function toArray(): array
    {
        return ['valid' => $this->errorCount === 0, 'release' => $this->release, 'artifacts' => $this->artifacts,
            'packages' => $this->packages, 'errorCount' => $this->errorCount, 'errors' => $this->errors];
    }
}
