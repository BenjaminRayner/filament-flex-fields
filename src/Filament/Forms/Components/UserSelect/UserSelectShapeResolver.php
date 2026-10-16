<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Bjanczak\FilamentFlexFields\Support\Select\Data\OptionValue;
use Illuminate\Database\Eloquent\Model;

class UserSelectShapeResolver
{
    public function __construct(
        protected UserSelect $select,
        protected UserSelectRecordMapper $mapper,
        protected UserSelectQueryEngine $queryEngine,
        protected UserSelectRuntimeState $state,
    ) {}

    /**
     * @return ?array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }
     */
    public function resolveOptionShapeForValue(mixed $value): ?array
    {
        $value = OptionValue::normalize($value);
        if ($value === null) {
            return null;
        }

        return $this->resolveOptionShapesForValues([$value])[(string) $value] ?? null;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<string, array{
     *     label: string,
     *     description: ?string,
     *     image: ?string,
     *     verified: bool,
     * }>
     */
    public function resolveOptionShapesForValues(array $values): array
    {
        $this->state->synchronizeContext($this->select->getUserSelectProviderContextKey());
        $shapes = [];
        $missingValues = [];
        $options = $this->select->getOptions();
        $labels = $this->select->findOptionLabelsForStates($options, $values);

        foreach ($values as $value) {
            $value = OptionValue::normalize($value);
            if ($value === null) {
                continue;
            }

            $key = (string) $value;

            if (isset($this->state->resolvedOptionShapeCache[$key])) {
                $shapes[$key] = $this->state->resolvedOptionShapeCache[$key];

                continue;
            }

            $label = $labels[$key] ?? null;

            if (is_array($label)) {
                $mapped = $this->mapper->recordToOptionArrayFromShape($value, $label);
                $shape = array_intersect_key($mapped, array_flip(['label', 'description', 'image', 'verified']));

                $shapes[$key] = $shape;
                $this->state->resolvedOptionShapeCache[$key] = $shape;

                continue;
            }

            if (is_string($label) && filled($label)) {
                $shape = [
                    'label' => $label,
                    'description' => null,
                    'image' => null,
                    'verified' => false,
                ];

                $shapes[$key] = $shape;
                $this->state->resolvedOptionShapeCache[$key] = $shape;

                continue;
            }

            $missingValues[$key] = $value;
        }

        if ($missingValues !== [] && ($this->select->hasRelationship() || $this->select->getUserModel() !== null)) {
            $this->queryEngine->resolveRecordsForValues($missingValues);

            foreach ($missingValues as $value) {
                $key = (string) $value;
                $record = $this->state->resolvedRecordCache[$key] ?? null;

                if (! $record instanceof Model) {
                    continue;
                }

                $shape = $this->mapper->recordToOptionArray($record);
                $shapes[$key] = $shape;
                $this->state->resolvedOptionShapeCache[$key] = $shape;
            }
        }

        return $shapes;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<int|string, string>
     */
    public function resolveOptionLabelsForValues(array $values): array
    {
        $labels = [];
        $shapes = $this->resolveOptionShapesForValues($values);

        foreach ($values as $value) {
            $value = OptionValue::normalize($value);
            if ($value === null) {
                continue;
            }

            $shape = $shapes[(string) $value] ?? null;

            if ($shape === null) {
                // Filament also uses this callback for membership validation.
                // A display fallback must never certify a missing/inaccessible key.
                continue;
            }

            $labels[$value] = $shape['label'];
        }

        return $labels;
    }
}
