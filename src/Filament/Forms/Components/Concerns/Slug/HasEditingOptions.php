<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Slug;

use Closure;

trait HasEditingOptions
{
    protected string|Closure|null $slugOptionAutoUpdateDisabledField = null;

    protected string|Closure|null $slugOptionInlineEditPendingField = null;

    protected bool|Closure $slugOptionAutoGenerate = true;

    protected bool|Closure $slugOptionPreserveSlugOnEdit = true;

    protected bool|Closure $slugOptionInlineEditing = true;

    protected bool|Closure $slugOptionAllowHomepageSlug = false;

    protected int|Closure $slugOptionGenerationDebounce = 400;

    protected string|Closure $slugOptionVariant = 'primary';

    protected bool|Closure $slugOptionShowVisitLink = true;

    protected bool|Closure $slugOptionShowCopyButton = true;

    protected bool|Closure $slugOptionShowRegenerateButton = true;

    public function autoUpdateDisabledField(string|Closure|null $value): static
    {
        $this->slugOptionAutoUpdateDisabledField = $value;

        return $this;
    }

    public function inlineEditPendingField(string|Closure|null $value): static
    {
        $this->slugOptionInlineEditPendingField = $value;

        return $this;
    }

    public function autoGenerate(bool|Closure $value = true): static
    {
        $this->slugOptionAutoGenerate = $value;

        return $this;
    }

    public function shouldAutoGenerate(): bool
    {
        return (bool) $this->evaluate($this->slugOptionAutoGenerate);
    }

    public function preserveSlugOnEdit(bool|Closure $value = true): static
    {
        $this->slugOptionPreserveSlugOnEdit = $value;

        return $this;
    }

    public function shouldPreserveSlugOnEdit(): bool
    {
        return (bool) $this->evaluate($this->slugOptionPreserveSlugOnEdit);
    }

    public function inlineEditing(bool|Closure $value = true): static
    {
        $this->slugOptionInlineEditing = $value;

        return $this;
    }

    public function shouldUseInlineEditing(): bool
    {
        return (bool) $this->evaluate($this->slugOptionInlineEditing);
    }

    public function allowHomepageSlug(bool|Closure $value = true): static
    {
        $this->slugOptionAllowHomepageSlug = $value;

        return $this;
    }

    public function allowsHomepageSlug(): bool
    {
        return (bool) $this->evaluate($this->slugOptionAllowHomepageSlug);
    }

    public function generationDebounce(int|Closure $value = 400): static
    {
        $this->slugOptionGenerationDebounce = $value;

        return $this;
    }

    public function variant(string|Closure $value): static
    {
        $this->slugOptionVariant = $value;

        return $this;
    }

    public function showVisitLink(bool|Closure $value = true): static
    {
        $this->slugOptionShowVisitLink = $value;

        return $this;
    }

    public function shouldShowVisitLink(): bool
    {
        return (bool) $this->evaluate($this->slugOptionShowVisitLink);
    }

    public function showCopyButton(bool|Closure $value = true): static
    {
        $this->slugOptionShowCopyButton = $value;

        return $this;
    }

    public function shouldShowCopyButton(): bool
    {
        return (bool) $this->evaluate($this->slugOptionShowCopyButton);
    }

    public function showRegenerateButton(bool|Closure $value = true): static
    {
        $this->slugOptionShowRegenerateButton = $value;

        return $this;
    }

    public function shouldShowRegenerateButton(): bool
    {
        return (bool) $this->evaluate($this->slugOptionShowRegenerateButton);
    }

    protected bool|Closure|null $slugActionLabels = null;

    public function actionButtonLabels(bool|Closure $condition = true): static
    {
        $this->slugActionLabels = $condition;

        return $this;
    }

    public function actionButtonsIconOnly(bool|Closure $condition = true): static
    {
        return $this->actionButtonLabels(fn (): bool => ! $this->evaluate($condition));
    }

    public function shouldShowActionButtonLabels(): bool
    {
        return (bool) $this->evaluate($this->slugActionLabels ?? config('filament-flex-fields.slug.action_button_labels', true));
    }

    public function getAutoUpdateDisabledStatePath(): ?string
    {
        $name = $this->evaluate($this->slugOptionAutoUpdateDisabledField);

        return is_string($name) && $name !== '' ? $this->resolveSiblingStatePath($name) : null;
    }

    public function getInlineEditPendingFieldName(): string
    {
        $name = $this->evaluate($this->slugOptionInlineEditPendingField);

        return is_string($name) && $name !== '' ? $name : $this->getName().'_inline_edit_pending';
    }

    public function getInlineEditPendingStatePath(): ?string
    {
        return $this->shouldUseInlineEditing() ? $this->resolveSiblingStatePath($this->getInlineEditPendingFieldName()) : null;
    }

    public function getInitialAutoSyncDisabled(): bool
    {
        return $this->shouldPreserveSlugOnEdit() && filled($this->getRecordSlug());
    }

    public function getDebounceMilliseconds(): int
    {
        return max(0, (int) $this->evaluate($this->slugOptionGenerationDebounce));
    }

    public function getVariant(): string
    {
        return (string) $this->evaluate($this->slugOptionVariant);
    }
}
