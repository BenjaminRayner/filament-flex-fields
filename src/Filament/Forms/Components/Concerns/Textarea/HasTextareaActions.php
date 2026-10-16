<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\Textarea;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

trait HasTextareaActions
{
    /** @var list<string> */
    protected array $submitActionNames = [];

    /** @var array<int, array<string, mixed>>|Closure */
    protected array|Closure $toolbarSelects = [];

    public function toolbarAction(Action|Closure $action): static
    {
        return $this->prefixAction($action);
    }

    /**
     * Pill-style toolbar dropdown that keeps the active option visible on the trigger.
     *
     * @param  array<string, string> | Closure  $options
     */
    public function toolbarSelect(
        string $statePath,
        array|Closure $options,
        string|BackedEnum|Htmlable|Closure|null $icon = null,
        string|Closure|null $placeholder = null,
    ): static {
        $this->toolbarSelects = [
            ...Arr::wrap($this->evaluate($this->toolbarSelects)),
            [
                'statePath' => $statePath,
                'options' => $options,
                'icon' => $icon,
                'placeholder' => $placeholder ?? 'Select',
            ],
        ];

        return $this;
    }

    /**
     * @param  array<Action | ActionGroup | Closure>  $actions
     */
    public function toolbarActions(array $actions): static
    {
        return $this->prefixActions($actions);
    }

    public function cachePrefixActions(): array
    {
        $this->cachedPrefixActions = [];

        foreach ($this->prefixActions as $prefixAction) {
            foreach (Arr::wrap($this->evaluate($prefixAction)) as $action) {
                if ($action instanceof ActionGroup) {
                    $this->cachedPrefixActions['group_'.spl_object_id($action)] = $this->prepareActionGroup(
                        $action
                            ->defaultSize(Size::Small)
                            ->defaultTriggerView(Action::ICON_BUTTON_VIEW),
                    );

                    continue;
                }

                if (! $action instanceof Action) {
                    continue;
                }

                $this->cachedPrefixActions[$action->getName()] = $this->prepareAction(
                    $action
                        ->defaultSize(Size::Small)
                        ->defaultView(Action::ICON_BUTTON_VIEW),
                );
            }
        }

        return $this->cachedPrefixActions;
    }

    public function cacheSuffixActions(): array
    {
        $this->cachedSuffixActions = [];

        foreach ($this->suffixActions as $suffixAction) {
            foreach (Arr::wrap($this->evaluate($suffixAction)) as $action) {
                if (! $action instanceof Action) {
                    continue;
                }

                $this->cachedSuffixActions[$action->getName()] = $this->prepareSubmitAction(
                    $this->prepareAction(
                        $action
                            ->defaultSize(Size::Small)
                            ->defaultView(Action::ICON_BUTTON_VIEW)
                            ->color('primary'),
                    ),
                );
            }
        }

        return $this->cachedSuffixActions;
    }

    public function submitAction(Action|Closure|null $action): static
    {
        if ($action === null) {
            return $this;
        }

        if ($action instanceof Action) {
            $this->submitActionNames[] = $action->getName();
        }

        return $this->suffixAction(function (FlexTextareaField $component) use ($action) {
            $resolved = $component->evaluate($action);

            if ($resolved instanceof Action) {
                $component->submitActionNames[] = $resolved->getName();
            }

            return $resolved;
        });
    }

    public function prepareAction(Action $action): Action
    {
        $prepared = parent::prepareAction($action);

        $target = $this->resolveActionLivewireTarget($prepared);

        if (filled($target)) {
            $prepared->livewireTarget($target);
        }

        return $prepared;
    }

    protected function resolveActionLivewireTarget(Action $action): ?string
    {
        if (! $this->isMountedInContainer()) {
            return "mountAction('{$action->getName()}')";
        }

        return $action->getLivewireClickHandler() ?? "mountAction('{$action->getName()}')";
    }

    public function prepareActionGroup(ActionGroup $group): ActionGroup
    {
        $preparedGroup = parent::prepareActionGroup($group)
            ->dropdownTeleport(false)
            ->extraDropdownAttributes([
                'class' => 'fff-flex-textarea-action-dropdown',
            ], merge: true)
            ->extraAttributes([
                'wire:loading.attr' => null,
            ], merge: true);

        $preparedGroup->actions(
            collect($preparedGroup->getActions())
                ->map(fn (Action $action): Action => $this->prepareAction($action))
                ->all(),
        );

        return $preparedGroup;
    }

    protected function prepareSubmitAction(Action $action): Action
    {
        $wasDisabled = $action->hasDisabled() && (! $this->isMountedInContainer() || $action->isDisabled());

        return $action
            ->disabled(fn (FlexTextareaField $component): bool => $wasDisabled || $component->isSubmitDisabled())
            ->extraAttributes([
                'x-bind:disabled' => $wasDisabled ? '!canSubmit || true' : '!canSubmit',
            ], merge: true);
    }

    public function isSubmitDisabled(?string $state = null): bool
    {
        if ($this->evaluate($this->isDisabled) || $this->isReadOnly() || ($this->isMountedInContainer() && $this->getContainer()->isDisabled())) {
            return true;
        }
        if ($state !== null) {
            return blank(trim($state));
        }

        $value = $this->getState();

        return ! is_string($value) || blank(trim($value));
    }

    /**
     * @return list<string>
     */
    public function getSubmitActionNames(): array
    {
        return array_values(array_unique($this->submitActionNames));
    }

    /**
     * @return list<array{statePath: string, options: array<string, string>, placeholder: string, icon: string|BackedEnum|Htmlable|null, initialValue: mixed, initialLabel: string}>
     */
    public function getToolbarSelects(): array
    {
        return collect(Arr::wrap($this->evaluate($this->toolbarSelects)))
            ->map(function (array $select): array {
                $configuredStatePath = (string) $select['statePath'];
                $statePath = $this->isMountedInContainer()
                    ? $this->resolveRelativeStatePath($configuredStatePath)
                    : $configuredStatePath;

                /** @var array<string, string> $options */
                $options = $this->evaluate($select['options']);

                $placeholder = (string) $this->evaluate($select['placeholder'] ?? 'Select');
                $value = $this->resolveToolbarSelectValue($statePath);

                return [
                    'statePath' => $statePath,
                    'options' => $options,
                    'placeholder' => $placeholder,
                    'icon' => $this->evaluate($select['icon'] ?? null),
                    'initialValue' => $value,
                    'initialLabel' => (is_string($value) || is_int($value)) ? ($options[$value] ?? $placeholder) : $placeholder,
                ];
            })
            ->values()
            ->all();
    }

    protected function isMountedInContainer(): bool
    {
        return isset($this->container);
    }

    protected function resolveToolbarSelectValue(string $statePath): mixed
    {
        if (! $this->isMountedInContainer()) {
            return null;
        }

        return data_get($this->getLivewire(), $statePath);
    }
}
