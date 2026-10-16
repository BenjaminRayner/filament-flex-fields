<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Sluggable\Actions\GenerateSlugAction;
use Spatie\Sluggable\Support\Config;

/** The tested Spatie v4 bridge is confined here, away from field/rendering code. */
final class NativeActions
{
    public static function generator(Model $model): GenerateSlugAction
    {
        if (! method_exists($model, 'generateSlugAction')) {
            return Config::getAction(Config::ACTION_GENERATE_SLUG, GenerateSlugAction::class);
        }
        // HasSlug's protected factory is an extension point for model overrides.
        $action = (new ReflectionMethod($model, 'generateSlugAction'))->invoke($model);
        if (! $action instanceof GenerateSlugAction) {
            throw new LogicException('The model slug action must extend Spatie GenerateSlugAction.');
        }

        return $action;
    }

    public static function translatedCandidate(PreviewContext $context): string
    {
        // Reuse the trait's exact customization/original-title/locale semantics.
        // Calling public generateSlug() would force overwrite and generate every
        // locale; the editor deliberately requests only one locale on its clone.
        (new ReflectionProperty($context->model, 'slugOptions'))->setValue($context->model, $context->options);
        $value = (new ReflectionMethod($context->model, 'generateNonUniqueSlug'))->invoke($context->model);
        if (! is_string($value)) {
            throw new LogicException('The translated slug generator must return a string.');
        }

        return $value;
    }
}
