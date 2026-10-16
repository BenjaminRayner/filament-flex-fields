<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\SocialLinks\Validation;

use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialLinksNormalizer;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialLinkValidator;
use Bjanczak\FilamentFlexFields\Support\SocialLinks\SocialPlatformDefinition;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SocialLinksRule implements ValidationRule
{
    public bool $implicit = true;

    /** @param array<string, SocialPlatformDefinition> $definitions */
    public function __construct(private readonly array $definitions, private readonly ?int $max, private readonly bool $required) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! SocialLinksNormalizer::isValidShape($value)) {
            $fail('validation.array')->translate();

            return;
        }
        $links = SocialLinksNormalizer::normalize($value);
        if ($links === []) {
            if ($this->required) {
                $fail('validation.required')->translate();
            }

            return;
        }
        if ($this->max !== null && count($links) > $this->max) {
            $fail('validation.max.array')->translate(['max' => $this->max]);

            return;
        }
        $seen = [];
        foreach ($links as $link) {
            if (isset($seen[$link['platform']])) {
                $fail('validation.distinct')->translate();

                return;
            }
            $seen[$link['platform']] = true;
            $message = SocialLinkValidator::validateLink($link['platform'], $link['url'], array_keys($this->definitions), $this->definitions);
            if ($message !== null) {
                $fail('filament-flex-fields::default.social_links.validation.row')->translate([
                    'platform' => $this->definitions[$link['platform']]->label ?? $link['platform'], 'message' => $message,
                ]);
            }
        }
    }
}
