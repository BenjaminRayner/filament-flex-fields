<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Manual draft checks use the same scope/locale domain as native generation. */
final class UniquenessQuery
{
    public static function column(PreviewContext $context): string
    {
        return $context->options->slugField.($context->translated ? '->'.$context->locale : '');
    }

    /** @return Builder<Model> */
    public static function make(PreviewContext $context): Builder
    {
        // Spatie v4 includes soft-deleted and globally hidden records in slug
        // collisions. Consumer scopes are added on top by SlugField.
        $query = $context->model->newQuery()->withoutGlobalScopes();
        if ($context->options->extraScopeCallback !== null) {
            $query->where($context->options->extraScopeCallback);
        }

        return $query;
    }
}
