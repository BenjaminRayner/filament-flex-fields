<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionTree;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

/**
 * @mixin UserSelect
 *
 * @phpstan-import-type NormalizedSelectOption from InteractsWithUserSelectPresentation
 * @phpstan-import-type NormalizedUserSelectOption from InteractsWithUserSelectPresentation
 */
trait TransportsUserSelectOptions
{
    /**
     * @return array<array<string, mixed>>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionsForJs(): array
    {
        if ($this->getUserModel() !== null) {
            return $this->transformOptionsForJs($this->getDefaultSuggestions());
        }

        return parent::getOptionsForJs();
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getInitialOptionsForJs(): array
    {
        if ($this->getUserModel() !== null) {
            return [];
        }

        return $this->getOptionsForJs();
    }

    /**
     * @return array<array<string, mixed>>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionLabelsForJs(): array
    {
        $state = $this->getState();

        $values = is_array($state) ? $state : (filled($state) ? [$state] : []);
        if ($values === []) {
            return [];
        }

        $shapes = $this->resolveOptionShapesForValues($values);
        $options = [];

        foreach ($values as $value) {
            $value = OptionValue::normalize($value);
            if ($value === null) {
                continue;
            }

            $shape = $shapes[(string) $value] ?? null;

            if ($shape === null) {
                continue;
            }

            $options[$value] = $shape;
        }

        return $this->transformOptionsForJs($options);
    }

    /**
     * @param  array<string | int, string | array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public function transformRichOptionsForJs(array $options): array
    {
        return (new OptionTree)->map(
            $options,
            fn (array $label): bool => $this->isOptionGroupArray($label),
            function (string|int $value, mixed $label): ?array {
                if (is_array($label) && $this->isUserOptionArray($label)) {
                    $shape = $this->recordMapper()->recordToOptionArrayFromShape($value, $label);
                } elseif (is_string($label)) {
                    $shape = ['label' => $label, 'description' => null, 'image' => null,
                        'verified' => false, 'disabled' => $this->isOptionDisabled($value, $label)];
                } else {
                    return null;
                }

                return $this->formatUserOptionForJs($value, $shape);
            },
        );
    }

    /**
     * @param  array{
     *     label: string,
     *     description?: ?string,
     *     image?: ?string,
     *     verified?: bool,
     *     disabled?: bool,
     * }  $shape
     * @return array{
     *     name: string,
     *     email: ?string,
     *     avatarUrl: ?string,
     *     verified: bool,
     *     initials: string,
     * }
     */
    public function userShapeToClientPayload(array $shape): array
    {
        return $this->optionPresenter()->userShapeToClientPayload($shape);
    }

    /**
     * @param  array{
     *     label: string,
     *     description?: ?string,
     *     image?: ?string,
     *     verified?: bool,
     *     disabled?: bool,
     * }  $shape
     * @return array{
     *     value: string,
     *     label: string,
     *     user: array{name: string, email: ?string, avatarUrl: ?string, verified: bool, initials: string},
     *     userName: string,
     *     isDisabled: bool,
     *     fffClientRender: true,
     * }
     */
    public function formatUserOptionForJs(string|int $value, array $shape): array
    {
        $user = $this->userShapeToClientPayload($shape);

        return [
            'value' => (string) $value,
            'label' => $user['name'],
            'user' => $user,
            'userName' => $user['name'],
            'isDisabled' => (bool) ($shape['disabled'] ?? false)
                || (bool) ($shape['isDisabled'] ?? false)
                || $this->isOptionDisabled($value, $user['name']),
            'fffClientRender' => true,
        ];
    }

    /**
     * @return NormalizedUserSelectOption
     */
    protected function normalizeOption(string|int $value, array|string $label): array
    {
        $normalized = parent::normalizeOption($value, $label);

        if (is_array($label)) {
            $normalized['verified'] = (bool) ($label['verified'] ?? false);
        } else {
            $normalized['verified'] = false;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $option
     */
    protected function isUserOptionArray(array $option): bool
    {
        return array_key_exists('label', $option)
            && (
                array_key_exists('verified', $option)
                || array_key_exists('image', $option)
                || array_key_exists('description', $option)
            );
    }
}
