<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\FileUpload\Media;

use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class UploadAdmission
{
    public function validate(BaseFileUpload $field, TemporaryUploadedFile $file): void
    {
        // Run native per-file rules, including FLEX extensions/dimensions, for direct save calls.
        // Cardinality and aggregate size belong to the complete field, not one storage operation.
        $rules = array_values(array_filter($field->getValidationRules(), static fn (mixed $rule): bool => $rule instanceof \Closure));
        validator(['file' => [$file]], ['file' => $rules])->validate();
    }
}
