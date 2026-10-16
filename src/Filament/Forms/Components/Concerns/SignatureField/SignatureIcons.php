<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\SignatureField;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/** @mixin SignatureField */
trait SignatureIcons
{
    public function undoIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->undoIcon = $icon;

        return $this;
    }

    public function clearIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->clearIcon = $icon;

        return $this;
    }

    public function downloadIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->downloadIcon = $icon;

        return $this;
    }

    public function fullscreenIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->fullscreenIcon = $icon;

        return $this;
    }

    public function pdfPreviewIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->pdfPreviewIcon = $icon;

        return $this;
    }

    public function closeIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->closeIcon = $icon;

        return $this;
    }

    public function getUndoIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->undoIcon, 'signature_undo_icon', FlexIcon::ArrowRotateLeft);
    }

    public function getClearIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->clearIcon, 'signature_clear_icon', FlexIcon::ArrowsRotateRight);
    }

    public function getDownloadIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->downloadIcon, 'signature_download_icon', FlexIcon::ArrowDownToSquare);
    }

    public function getFullscreenIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->fullscreenIcon, 'signature_fullscreen_icon', FlexIcon::ChevronsExpandUpRight);
    }

    public function getPdfPreviewIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->pdfPreviewIcon, 'signature_pdf_preview_icon', FlexIcon::FileText);
    }

    public function getCloseIcon(): string|BackedEnum|Htmlable
    {
        return $this->resolveFieldIcon($this->closeIcon, 'signature_close_icon', FlexIcon::Xmark);
    }
}
