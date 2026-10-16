<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Lifecycle;

use Filament\Resources\Events\RecordSaved;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

/** Supports Filament's native class-name payload and object dispatch without per-instance listeners. */
final class UploadRecordSaved
{
    public function __construct(private readonly UploadRecordLifecycle $lifecycle) {}

    /** @param array<string, mixed> $data */
    public function handle(Model|RecordSaved $record, array $data = [], ?Page $page = null): void
    {
        if ($record instanceof RecordSaved) {
            $page = $record->getPage();
            $record = $record->getRecord();
        }
        if ($page !== null) {
            $this->lifecycle->acceptFor($page, $record);
        }
    }
}
