<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media\Events;

enum UploadEventType: string
{
    case Stored = 'stored';
    case Accepted = 'accepted';
    case Recovered = 'recovered';
    case Rejected = 'rejected';
    case Deleted = 'deleted';
}
