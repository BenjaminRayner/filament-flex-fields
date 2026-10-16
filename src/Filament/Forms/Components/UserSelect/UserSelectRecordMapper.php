<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UserSelectRecordMapper
{
    public function __construct(
        protected UserSelect $select,
    ) {}

    /**
     * @return array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }
     */
    public function recordToOptionArray(Model $record): array
    {
        return [
            'label' => $this->resolveName($record),
            'description' => $this->resolveEmail($record),
            'image' => $this->resolveAvatarUrl($record),
            'verified' => $this->resolveIsVerified($record),
        ];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     *     disabled?: bool,
     * }
     */
    public function recordToOptionArrayFromShape(string|int $value, array $shape): array
    {
        $label = OptionText::required($shape['label'] ?? $value, 'label');

        return [
            'label' => $label,
            'description' => OptionText::optional($shape['description'] ?? null, 'description'),
            'image' => OptionText::optional($shape['image'] ?? null, 'image'),
            'verified' => (bool) ($shape['verified'] ?? false),
            'disabled' => (bool) ($shape['disabled'] ?? false)
                || (bool) ($shape['isDisabled'] ?? false)
                || $this->select->isOptionDisabled($value, $label),
        ];
    }

    public function resolveModelKeyName(): string
    {
        if ($this->select->hasRelationship() && ($relationship = $this->select->getRelationship()) instanceof Relation) {
            $qualified = $this->select->getQualifiedRelatedKeyForRelationship($relationship);

            return Str::afterLast($qualified, '.');
        }

        $modelClass = $this->select->getUserModel();

        if ($modelClass === null) {
            return $this->select->hasRelationship()
                ? ($this->select->getRelationship()?->getRelated()->getKeyName() ?? 'id')
                : 'id';
        }

        /** @var Model $model */
        $model = new $modelClass;

        return $model->getKeyName();
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array<int|string, array<string, mixed>>
     */
    public function mapQueryRecordsToOptions(Collection $records): array
    {
        $keyName = $this->resolveModelKeyName();

        return $records
            ->mapWithKeys(fn (Model $record): array => [
                $record->getAttribute($keyName) => $this->recordToOptionArray($record),
            ])
            ->all();
    }

    public function resolveName(Model $record): string
    {
        $callback = $this->select->getNameResolver();

        if ($callback !== null) {
            return OptionText::required($this->select->evaluate($callback, ['record' => $record]), 'user name');
        }

        return OptionText::required(data_get($record, $this->select->getNameColumn()), 'user name');
    }

    public function resolveEmail(Model $record): ?string
    {
        $callback = $this->select->getEmailResolver();

        if ($callback !== null) {
            $email = $this->select->evaluate($callback, ['record' => $record]);

            return OptionText::optional($email, 'user email');
        }

        $column = $this->select->getEmailColumn();

        if ($column === null) {
            return null;
        }

        $email = data_get($record, $column);

        return OptionText::optional($email, 'user email');
    }

    public function resolveAvatarUrl(Model $record): ?string
    {
        $callback = $this->select->getAvatarUrlResolver();

        if ($callback !== null) {
            $url = $this->select->evaluate($callback, ['record' => $record]);

            return OptionText::optional($url, 'user avatar');
        }

        $column = $this->select->getAvatarColumn();

        if ($column === null) {
            return null;
        }

        $url = data_get($record, $column);

        return OptionText::optional($url, 'user avatar');
    }

    public function resolveIsVerified(Model $record): bool
    {
        $callback = $this->select->getVerifiedResolver();

        if ($callback !== null) {
            return (bool) $this->select->evaluate($callback, ['record' => $record]);
        }

        $column = $this->select->getVerificationColumn();

        if ($column === null) {
            return false;
        }

        $value = data_get($record, $column);

        if (is_bool($value)) {
            return $value;
        }

        return filled($value);
    }

    public function needsFullModelForResolvers(): bool
    {
        return $this->select->getNameResolver() !== null
            || $this->select->getEmailResolver() !== null
            || $this->select->getAvatarUrlResolver() !== null
            || $this->select->getVerifiedResolver() !== null;
    }
}
