<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Concerns\RendersStockFieldView;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Textarea\ConfiguresTextarea;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Textarea\HasTextareaActions;
use Bjanczak\FilamentFlexFields\Support\Textarea\TextareaRenderState;
use Filament\Forms\Components\Concerns\HasAffixes;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Contracts\HasAffixActions;

/** Native Textarea state, casts and validation; instance-local FLEX presentation. */
class FlexTextareaField extends Textarea implements HasAffixActions
{
    use ConfiguresTextarea;
    use HasAffixes, HasTextareaActions {
        HasTextareaActions::cachePrefixActions insteadof HasAffixes;
        HasTextareaActions::cacheSuffixActions insteadof HasAffixes;
    }
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use RendersStockFieldView;

    protected string $view = 'filament-flex-fields::forms.components.flex-textarea-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->autosize()->rows(1)->disableGrammarly()->rule('string');
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    public function getTextareaPresentation(): TextareaRenderState
    {
        return TextareaRenderState::fromField($this);
    }
}
