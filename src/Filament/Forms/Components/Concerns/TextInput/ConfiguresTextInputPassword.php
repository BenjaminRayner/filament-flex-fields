<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Bjanczak\FilamentFlexFields\Support\TextInput\PasswordPolicy;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

trait ConfiguresTextInputPassword
{
    protected bool|Closure $passwordStrength = false;

    /** @var array<int|string, string>|Closure|null */
    protected array|Closure|null $passwordStrengthLabels = null;

    protected string|BackedEnum|Htmlable|Closure|null $copyIcon = null;

    protected int|Closure|null $copyFeedbackDuration = null;

    protected string|BackedEnum|Htmlable|Closure|null $showPasswordIcon = null;

    protected string|BackedEnum|Htmlable|Closure|null $hidePasswordIcon = null;

    public function passwordStrength(bool|Closure $condition = true): static
    {
        $this->passwordStrength = $condition;

        return $this;
    }

    /** @param array<int|string, string>|Closure $labels */
    public function passwordStrengthLabels(array|Closure $labels): static
    {
        $this->passwordStrengthLabels = $labels;

        return $this;
    }

    /** @return list<string> */
    public function getPasswordStrengthLabels(): array
    {
        $defaults = self::defaultPasswordStrengthLabels();
        $custom = $this->evaluate($this->passwordStrengthLabels);

        if (! is_array($custom)) {
            return $defaults;
        }

        if ($custom === []) {
            return $defaults;
        }

        $resolved = $defaults;

        if (array_is_list($custom)) {
            foreach ($custom as $index => $label) {
                if ($index >= 0 && $index <= 4) {
                    $resolved[$index] = (string) $label;
                }
            }

            return $resolved;
        }

        $namedKeys = [
            'very_weak' => 0,
            'weak' => 1,
            'fair' => 2,
            'good' => 3,
            'strong' => 4,
        ];

        foreach ($custom as $key => $label) {
            if (is_int($key) && $key >= 0 && $key <= 4) {
                $resolved[$key] = (string) $label;

                continue;
            }

            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));

            if (isset($namedKeys[$normalizedKey])) {
                $resolved[$namedKeys[$normalizedKey]] = (string) $label;

                continue;
            }

            foreach ($defaults as $index => $defaultLabel) {
                if (strtolower($defaultLabel) === strtolower($key)) {
                    $resolved[$index] = (string) $label;

                    break;
                }
            }
        }

        return $resolved;
    }

    /** @return list<string> */
    public static function defaultPasswordStrengthLabels(): array
    {
        return array_values((array) __('filament-flex-fields::text-input.strength'));
    }

    public function shouldShowPasswordStrength(): bool
    {
        if (! $this->isSensitiveTextInput()) {
            return false;
        }

        return (bool) $this->evaluate($this->passwordStrength);
    }

    /** @return array{score: int, label: string, percent: int} */
    public function calculatePasswordStrength(string $password): array
    {
        $options = $this->getTextInputEditingOptions();

        return PasswordPolicy::strength(
            $password, $this->getPasswordStrengthLabels(), $options['passwordStrengthAlgorithm'], $options['passwordRequirements'],
        );
    }

    public function copyIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->copyIcon = $icon;

        return $this;
    }

    public function showPasswordIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->showPasswordIcon = $icon;

        return $this;
    }

    public function hidePasswordIcon(string|BackedEnum|Htmlable|Closure|null $icon): static
    {
        $this->hidePasswordIcon = $icon;

        return $this;
    }

    public function getCopyIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->copyIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_text_input_copy_icon', FlexIcon::Copy);
    }

    public function getShowPasswordIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->showPasswordIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_text_input_show_password_icon', FlexIcon::View);
    }

    public function getHidePasswordIcon(): string|BackedEnum|Htmlable
    {
        $icon = $this->evaluate($this->hidePasswordIcon);

        if (filled($icon)) {
            return $icon;
        }

        return FlexIcon::configured('flex_text_input_hide_password_icon', FlexIcon::ViewOff);
    }

    public function getCopySuccessIcon(): string|BackedEnum|Htmlable
    {
        return FlexIcon::configured('flex_text_input_copy_success_icon', FlexIcon::Check);
    }

    public function getCopyFeedbackDurationMs(): int
    {
        $duration = $this->copyFeedbackDuration;

        if ($duration instanceof Closure) {
            $duration = $this->evaluate($duration);
        }

        if (! is_int($duration) || $duration < 1) {
            return 2500;
        }

        return max(500, $duration);
    }
}
