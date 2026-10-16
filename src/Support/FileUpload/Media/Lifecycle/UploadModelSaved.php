<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle;

use Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events\UploadNotifications;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** One stateless listener; field participation is request-scoped and weakly referenced. */
final class UploadModelSaved
{
    public function __construct(private readonly UploadRecordLifecycle $lifecycle) {}

    /** @param array<int, mixed> $payload */
    public function handle(string $event, array $payload): void
    {
        $record = $payload[0] ?? null;
        if (! $record instanceof Model) {
            return;
        }
        try {
            $this->lifecycle->acceptForRecord($record);
        } catch (Throwable $error) {
            // A lifecycle or reporting failure must never invalidate an already saved model.
            UploadNotifications::report($error);
        }
    }
}
