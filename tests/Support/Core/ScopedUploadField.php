<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support\Core;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

final class ScopedUploadField extends FlexFileUpload
{
    public int $recordReads = 0;

    public ?Model $subject = null;

    public Page $owner;

    public function getLivewire(): Component&HasSchemas
    {
        return $this->owner;
    }

    public function getRecord(bool $withContainerRecord = true): Model|array|null
    {
        $this->recordReads++;

        return $this->subject;
    }
}
