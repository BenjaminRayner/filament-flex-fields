<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events;

enum UploadTransportEventType: string
{
    case Added = 'added';
    case Started = 'started';
    case Progress = 'progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Removed = 'removed';
    case Retried = 'retried';
    case Reordered = 'reordered';
    case QueuePaused = 'queue_paused';
    case QueueResumed = 'queue_resumed';
}
