<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\UserSelect;

use BackedEnum;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin UserSelect
 *
 * @phpstan-import-type NormalizedSelectOption from InteractsWithUserSelectPresentation
 * @phpstan-import-type NormalizedUserSelectOption from InteractsWithUserSelectPresentation
 */
trait ResolvesUserSelectDisplay
{
    /**
     * @return array{
     *     triggerHtml: ?string,
     *     tagsHtml: ?string,
     *     entries: list<array{value: string, user: array{name: string, email: ?string, avatarUrl: ?string, verified: bool, initials: string}}>,
     * }
     */
    public function getInitialUserSelectDisplay(): array
    {
        return $this->optionPresenter()->initialDisplay();
    }

    public function getInitialMultipleTriggerHtml(): ?string
    {
        return $this->getInitialUserSelectDisplay()['triggerHtml'];
    }

    public function getInitialSelectedUserTagsHtml(): ?string
    {
        return $this->getInitialUserSelectDisplay()['tagsHtml'];
    }

    /**
     * @return list<array{value: string, user: array{name: string, email: ?string, avatarUrl: ?string, verified: bool, initials: string}}>
     */
    public function getInitialSelectedUserEntriesForJs(): array
    {
        if ($this->isMultiple()) {
            return $this->getInitialUserSelectDisplay()['entries'];
        }

        $state = $this->resolveStateForItemCardTrigger();

        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if (blank($state)) {
            return [];
        }

        $shape = $this->shapeResolver()->resolveOptionShapeForValue($state);

        if ($shape === null) {
            return [];
        }

        return [
            $this->optionPresenter()->buildSelectedUserDisplayEntry($state, $shape),
        ];
    }

    /**
     * @return list<array{value: string, user: array{name: string, email: ?string, avatarUrl: ?string, verified: bool, initials: string}}>
     */
    public function resolveSelectedUsersForDisplay(): array
    {
        $state = $this->resolveStateForItemCardTrigger();
        $identity = hash('sha256', serialize([$this->isMultiple(), $state, $this->getUserSelectProviderContextKey()]));
        if ($this->runtimeState()->cachedSelectedUsersIdentity !== $identity) {
            $this->runtimeState()->cachedSelectedUsersIdentity = $identity;
            $this->runtimeState()->cachedSelectedUsersForDisplay = null;
        }

        if ($this->runtimeState()->cachedSelectedUsersForDisplay !== null) {
            return $this->runtimeState()->cachedSelectedUsersForDisplay;
        }

        if (! $this->isMultiple()) {
            $this->runtimeState()->cachedSelectedUsersForDisplay = [];

            return $this->runtimeState()->cachedSelectedUsersForDisplay;
        }

        if (! is_array($state) || $state === []) {
            $this->runtimeState()->cachedSelectedUsersForDisplay = [];

            return $this->runtimeState()->cachedSelectedUsersForDisplay;
        }

        $shapes = $this->resolveOptionShapesForValues($state);
        $users = [];

        foreach ($state as $value) {
            $value = OptionValue::normalize($value);
            if ($value === null) {
                continue;
            }

            $shape = $shapes[(string) $value] ?? null;

            if ($shape === null) {
                continue;
            }

            $users[] = $this->optionPresenter()->buildSelectedUserDisplayEntry($value, $shape);
        }

        $this->runtimeState()->cachedSelectedUsersForDisplay = $users;

        return $this->runtimeState()->cachedSelectedUsersForDisplay;
    }

    public function getInitialTriggerLabel(): ?string
    {
        if ($this->isMultiple()) {
            return $this->getInitialMultipleTriggerHtml();
        }

        if ($this->isNative() || $this->getVariant() === 'item-card') {
            return null;
        }

        $state = $this->resolveStateForItemCardTrigger();

        if ($state instanceof BackedEnum) {
            $state = $state->value;
        }

        if (blank($state)) {
            return (string) ($this->getPlaceholder() ?? '');
        }

        $label = $this->findOptionLabel($this->getOptions(), $state);

        if (is_array($label)) {
            return $this->renderUserOption(
                $this->recordMapper()->recordToOptionArrayFromShape($state, $label),
                layout: 'trigger',
            );
        }

        if ($this->getUserModel() !== null || $this->hasRelationship()) {
            $record = $this->queryEngine()->resolveRecordForValue($state);

            if ($record instanceof Model) {
                return $this->renderUserOption(
                    $this->recordToOptionArray($record),
                    layout: 'trigger',
                );
            }
        }

        return parent::getInitialTriggerLabel();
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
     */
    public function getInitialTriggerBadges(): array
    {
        return [];
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
     */
    public function getInitialTriggerBadgesLegacy(): array
    {
        return [];
    }

    protected function resolveDisplayNameForValue(mixed $value): ?string
    {
        $shape = $this->shapeResolver()->resolveOptionShapeForValue($value);

        return $shape['label'] ?? null;
    }
}
