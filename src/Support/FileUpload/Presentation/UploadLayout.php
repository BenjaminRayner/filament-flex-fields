<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation;

enum UploadLayout: string
{
    case List = 'list';
    case Grid = 'grid';
    case Compact = 'compact';
    case Avatar = 'avatar';
}
