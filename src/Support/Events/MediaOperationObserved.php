<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Events;

use InvalidArgumentException;

/** Correlation only: deliberately excludes files, form values, URLs, record IDs and arbitrary error text. */
final readonly class MediaOperationObserved implements ComponentActivity
{
    public ?string $originalOperationId;

    public ?string $operationId;

    public ?string $journalId;

    public ?string $reason;

    public function __construct(public string $stage, ?string $originalOperationId, ?string $operationId = null, ?string $journalId = null, ?string $reason = null)
    {
        if (! in_array($stage, ['stored', 'rejected', 'conversion_queued', 'conversion_processing', 'conversion_stored', 'conversion_ready', 'conversion_failed', 'conversion_cancelled'], true)) {
            throw new InvalidArgumentException('Unknown media diagnostic stage.');
        }
        $this->originalOperationId = self::identity($originalOperationId);
        $this->operationId = self::identity($operationId);
        $this->journalId = self::identity($journalId);
        $this->reason = $reason === null ? null : (in_array($reason, ['image_conversion_failed', 'integrity_mismatch', 'denied',
            'conversion_record_missing', 'conversion_cancelled', 'invalid_receipt', 'operation_failed', 'finalizer_failed'], true) ? $reason : 'other');
    }

    public static function identity(?string $value): ?string
    {
        return $value !== null && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) ? strtolower($value) : null;
    }

    public function toArray(): array
    {
        return ['stage' => $this->stage, 'original_operation_id' => $this->originalOperationId,
            'operation_id' => $this->operationId, 'journal_id' => $this->journalId, 'reason' => $this->reason];
    }
}
