<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Concerns\Location;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\MapPickerField;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureFormAction;
use Bjanczak\FilamentFlexFields\Support\Location\FeatureProperties;
use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** @mixin MapPickerField */
trait ConfiguresFeatureEditor
{
    /** @var list<Component|Action>|Closure|null */
    protected array|Closure|null $featureFormSchema = null;

    protected ?Closure $modifyFeatureEditorAction = null;

    /** @var list<Action|Closure> */
    protected array $customFeatureActions = [];

    /**
     * Real Filament schema, mounted on demand in its native action modal.
     *
     * @param  list<Component|Action>|Closure|null  $schema
     */
    public function featureSchema(array|Closure|null $schema): static
    {
        $this->featureFormSchema = $schema;

        return $this;
    }

    /** @param list<string>|Closure $keys Additional bounded JSON properties stored in GeoJSON. */
    public function featurePropertyKeys(array|Closure $keys): static
    {
        $this->locationMapOptions['extraFeatureProperties'] = $keys;

        return $this;
    }

    public function quickFeatureEditing(bool|Closure $enabled = true): static
    {
        $this->locationMapOptions['quickFeatureEditing'] = $enabled;

        return $this;
    }

    public function modifyFeatureActionUsing(?Closure $callback): static
    {
        $this->modifyFeatureEditorAction = $callback;

        return $this;
    }

    /** @param list<Action|Closure> $actions Native authorized actions receive arguments.id and arguments.before. */
    public function featureActions(array $actions): static
    {
        $this->customFeatureActions = $actions;

        return $this;
    }

    /** @return list<Action> */
    public function getDefaultActions(): array
    {
        $action = FeatureFormAction::make();
        if ($this->modifyFeatureEditorAction !== null) {
            $this->evaluate($this->modifyFeatureEditorAction, ['action' => $action]);
        }

        return array_values([...parent::getDefaultActions(), $action, ...$this->getFeatureActions()]);
    }

    /** @return list<Action> */
    public function getFeatureActions(): array
    {
        $actions = [];
        foreach ($this->customFeatureActions as $configured) {
            $action = $this->evaluate($configured);
            if (! $action instanceof Action || $action->getName() === FeatureFormAction::NAME) {
                throw new InvalidArgumentException('featureActions requires uniquely named Filament actions.');
            }
            if (isset($actions[$action->getName()])) {
                throw new InvalidArgumentException('Duplicate feature action name.');
            }
            $actions[$action->getName()] = $action;
        }

        return array_values($actions);
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array<Component|Action>
     */
    public function getFeatureFormSchema(array $feature): array
    {
        $schema = $this->featureFormSchema === null ? FeatureFormAction::defaultSchema()
            : $this->evaluate($this->featureFormSchema, ['feature' => $feature, 'properties' => $feature['properties'], 'geometry' => $feature['geometry']]);
        if (! is_array($schema)) {
            throw new InvalidArgumentException('featureSchema must resolve to a Filament component array.');
        }

        return $schema;
    }

    /**
     * Server authority: always resolve by identity, never trust client-supplied properties.
     *
     * @return array<string, mixed>
     */
    public function getEditableFeature(string $id): array
    {
        if ($this->isDisabled() || $this->isReadOnly() || $this->getMapSelectionMode() === 'point') {
            abort(403);
        }
        try {
            $features = $this->normalizeMapGeometry($this->getState())['features'];
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['feature' => __('filament-flex-fields::location.invalid_properties')]);
        }
        foreach ($features as $feature) {
            if ($feature['id'] !== $id) {
                continue;
            }
            if ($feature['geometry']['type'] !== 'Point' && ! $this->getMapConfiguration()['editableZones']) {
                abort(403);
            }

            return $feature;
        }

        throw ValidationException::withMessages(['feature' => __('filament-flex-fields::location.feature_changed')]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeFeatureFormData(array $data): array
    {
        $keys = $this->getMapConfiguration()['extraFeatureProperties'];
        $allowed = [...FeatureProperties::TEXT_KEYS, 'clickable', ...$keys];
        if (array_diff(array_keys($data), $allowed) !== []) {
            throw new InvalidArgumentException('Declare custom schema property names with featurePropertyKeys().');
        }
        foreach (FeatureProperties::TEXT_KEYS as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null && $key !== 'color') {
                $data[$key] = '';
            }
        }

        return FeatureProperties::normalize($data, $keys);
    }
}
