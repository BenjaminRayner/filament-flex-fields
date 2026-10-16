<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Slug\Spatie;

use Closure;
use LogicException;

/** Native generation is authoritative; disabled policies never invent a fallback. */
final class PreviewGenerator
{
    public static function run(PreviewContext $context, bool $force = false, ?string $currentSlug = null): PreviewResult
    {
        $model = $context->model;
        $options = $context->options;
        $action = NativeActions::generator($model);
        $action->ensureValidOptions($options);
        $reason = null;
        if (! $force) {
            $reason = (! $model->exists && ! $options->generateSlugsOnCreate) ? 'create-disabled'
                : (($model->exists && ! $options->generateSlugsOnUpdate) ? 'update-disabled' : null);
            if ($reason === null && $options->skipGenerateWhen instanceof Closure && $action->shouldSkipBasedOnSkipWhen($options)) {
                $reason = 'skip-condition';
            }
            if ($reason === null && $options->preventOverwrite && ($context->translated ? $context->existing() !== '' : $model->getAttribute($options->slugField) !== null)) {
                $reason = 'prevent-overwrite';
            }
        }
        if ($reason !== null) {
            return new PreviewResult($context->existing() !== '' ? $context->existing() : ($currentSlug ?? ''), false, $reason);
        }
        if (! $context->translated) {
            // Spatie owns multi-source/language/length, custom slug detection,
            // scoped collision queries and both numeric/custom suffix policies.
            $action->generate($model, $options);

            return new PreviewResult($context->existing(), true);
        }
        if ($force) {
            $options->preventOverwrite = false;
        }
        if (! method_exists($model, 'withLocale')) {
            throw new LogicException('A translated slug model must provide native withLocale().');
        }
        $slug = '';
        $model->withLocale($context->locale, function () use ($context, $action, &$slug): void {
            $slug = NativeActions::translatedCandidate($context);
            if ($context->options->generateUniqueSlugs) {
                $localized = clone $context->options;
                $localized->slugField .= '->'.$context->locale;
                $slug = $action->makeUnique($slug, $context->model, $localized);
            }
        });

        return new PreviewResult($slug, true);
    }
}
