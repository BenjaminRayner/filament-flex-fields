<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Support\TextInput\AssociatedInputSchema;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use InvalidArgumentException;

trait ConfiguresTextInputIconPicker
{
    protected ?string $associatedIconStatePath = null;

    protected ?Closure $configureAssociatedIconPicker = null;

    protected bool|Closure $associatedIconPickerEnabled = false;

    /** The icon is a native, independently validated sibling value, never injected into text. */
    public function iconPicker(string $statePath, ?Closure $configure = null, bool|Closure $condition = true): static
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $statePath) || strlen($statePath) > 255) {
            throw new InvalidArgumentException('The associated icon needs a relative form state path.');
        }
        $this->associatedIconStatePath = $statePath;
        $this->configureAssociatedIconPicker = $configure;
        $this->associatedIconPickerEnabled = $condition;
        $this->childComponents(static function (FlexTextInput $component): ?AssociatedInputSchema {
            if (! $component->hasIconPicker()) {
                return null;
            }
            $path = $component->associatedIconStatePath;
            if ($path === null) {
                return null;
            }
            if ($path === $component->getName() || str_starts_with($path.'.', $component->getName().'.')
                || str_starts_with($component->getName().'.', $path.'.')) {
                throw new InvalidArgumentException('Associated icon state cannot overlap its text input.');
            }
            $picker = IconPickerField::make($path)->label(fn () => Translations::get('filament-flex-fields::default.icon_picker.placeholder'))
                ->clearable()->size($component->getSize());
            if ($component->configureAssociatedIconPicker !== null) {
                $component->evaluate($component->configureAssociatedIconPicker, ['picker' => $picker], [IconPickerField::class => $picker]);
            }
            // Presentation and ownership cannot be overridden by the configuration callback.
            $picker->statePath($path)->inlineTrigger();

            return AssociatedInputSchema::make()->key('associated-icon')->disabled($component->isDisabled() || $component->isReadOnly())->components([$picker]);
        }, 'iconPicker');

        return $this;
    }

    public function hasIconPicker(): bool
    {
        return $this->associatedIconStatePath !== null && (bool) $this->evaluate($this->associatedIconPickerEnabled);
    }
}
