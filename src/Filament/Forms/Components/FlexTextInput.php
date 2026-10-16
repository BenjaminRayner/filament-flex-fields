<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Enums\ControlSize;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputAdvanced;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputAppearance;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputEditing;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputFeedback;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputIconPicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputPassword;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputSuggestions;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputTools;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput\ConfiguresTextInputVerification;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputRenderState;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputValidationRule;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Forms\Components\TextInput\Actions\HidePasswordAction;
use Filament\Forms\Components\TextInput\Actions\ShowPasswordAction;

/** Native state/validation, FLEX presentation and opt-in browser tools. */
class FlexTextInput extends TextInput
{
    use ConfiguresTextInputAdvanced;
    use ConfiguresTextInputAppearance;
    use ConfiguresTextInputEditing;
    use ConfiguresTextInputFeedback;
    use ConfiguresTextInputIconPicker;
    use ConfiguresTextInputPassword;
    use ConfiguresTextInputSuggestions;
    use ConfiguresTextInputTools;
    use ConfiguresTextInputVerification;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;

    protected string $view = 'filament-flex-fields::forms.components.flex-text-input-field';

    protected function setUp(): void
    {
        parent::setUp();
        $this->size(config('filament-flex-fields.ui.flex_text_input_size', 'md'));
        $this->variant(config('filament-flex-fields.ui.flex_text_input_variant', 'primary'));
        $this->inlinePrefix();
        $this->inlineSuffix();
        $this->rule(fn (FlexTextInput $component): TextInputValidationRule => new TextInputValidationRule($component->getTextInputEditingOptions(), $component->isSensitiveTextInput()));
    }

    protected function defaultFocusOutline(): bool
    {
        return true;
    }

    public function copyable(bool|Closure $condition = true, string|Closure|null $copyMessage = null, int|Closure|null $copyMessageDuration = null): static
    {
        $this->copyFeedbackDuration = $copyMessageDuration ?? 2500;

        return parent::copyable($condition, $copyMessage, $copyMessageDuration ?? 2500);
    }

    public function getSuffixActions(): array
    {
        $actions = parent::getSuffixActions();
        foreach ($actions as $action) {
            if ($action instanceof CopyAction) {
                $action->icon($this->getCopyIcon());
            } elseif ($action instanceof ShowPasswordAction) {
                $action->icon($this->getShowPasswordIcon());
            } elseif ($action instanceof HidePasswordAction) {
                $action->icon($this->getHidePasswordIcon());
            }
        }

        return $actions;
    }

    public function getSize(): string
    {
        $size = $this->evaluate($this->size);
        $size = $size instanceof ControlSize ? $size->value : $size;
        if (! in_array($size, ['sm', 'md', 'lg'], true)) {
            throw new \InvalidArgumentException('Unsupported FlexTextInput size.');
        }

        return $size;
    }

    public function getRounding(): string
    {
        $value = $this->evaluate($this->rounding) ?? FlexFieldsConfig::getUiDefault('field_rounding', 'md');
        $value = $value === 'default' ? 'md' : $value;
        if (! in_array($value, ['native', 'none', 'sm', 'md', 'lg', 'xl', 'full'], true)) {
            throw new \InvalidArgumentException('Unsupported FlexTextInput rounding.');
        }

        return $value;
    }

    /** Security policy also covers native type('password') and attribute overrides. */
    public function isSensitiveTextInput(): bool
    {
        return $this->isPassword() || strtolower($this->getType()) === 'password'
            || strtolower((string) $this->getExtraInputAttributeBag()->get('type', '')) === 'password';
    }

    public function getTextInputRenderState(): TextInputRenderState
    {
        return TextInputRenderState::from($this);
    }
}
