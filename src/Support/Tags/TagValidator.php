<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Tags;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Closure;
use InvalidArgumentException;

/** Validate submitted data against the authoritative server catalog, not client menu rows. */
final class TagValidator
{
    public static function rule(TagsField $field): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            try {
                $tags = $field->normalizeTagsState($value);
            } catch (InvalidArgumentException) {
                $fail(__('validation.array', ['attribute' => $field->getLabel()]));

                return;
            }
            if (($maximum = $field->getMaxTags()) !== null && count($tags) > $maximum) {
                $fail(__('filament-flex-fields::default.validation.tags.max', ['max' => $maximum]));

                return;
            }
            if (! $field->isSuggestionsOnly()) {
                return;
            }
            $insensitive = $field->isDuplicateInsensitive();
            try {
                $allowed = array_fill_keys(array_map(
                    static fn (string $tag): string => TagState::identity($tag, $insensitive),
                    $field->getAllowedTagSuggestionsForValidation(array_values(array_unique($tags))),
                ), true);
            } catch (InvalidArgumentException) {
                $fail(__('filament-flex-fields::default.validation.tags.suggestions_only'));

                return;
            }
            foreach (array_unique($tags) as $tag) {
                if (! isset($allowed[TagState::identity($tag, $insensitive)])) {
                    $fail(__('filament-flex-fields::default.validation.tags.suggestions_only'));

                    return;
                }
            }
        };
    }
}
