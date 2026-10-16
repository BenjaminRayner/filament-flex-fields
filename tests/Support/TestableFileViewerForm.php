<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;

class TestableFileViewerForm extends TestableTranslatableForm implements HasActions
{
    use InteractsWithActions;
}
