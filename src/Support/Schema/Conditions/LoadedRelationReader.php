<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Schema\Conditions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Relation conditions never implicitly fetch another relationship. */
final class LoadedRelationReader
{
    public static function read(?Model $record, string $path): mixed
    {
        if ($record === null || $path === '') {
            return null;
        }

        $segments = explode('.', $path);
        $relation = array_shift($segments);

        if (! $record->relationLoaded($relation)) {
            return null;
        }

        $value = $record->getRelation($relation);

        foreach ($segments as $segment) {
            if ($value instanceof Model) {
                if ($value->relationLoaded($segment)) {
                    $value = $value->getRelation($segment);
                } elseif (array_key_exists($segment, $value->getAttributes()) || $value->hasCast($segment) || $value->hasGetMutator($segment) || $value->hasAttributeGetMutator($segment)) {
                    $value = $value->getAttribute($segment);
                } else {
                    return null;
                }
            } elseif ($value instanceof Collection) {
                $value = $value->get($segment);
            } elseif (is_array($value)) {
                $value = $value[$segment] ?? null;
            } else {
                return null;
            }
        }

        return $value;
    }
}
