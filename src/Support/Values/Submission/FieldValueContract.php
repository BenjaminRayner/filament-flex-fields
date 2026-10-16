<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Submission;

use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use Bjanczak\FilamentFlexFields\Support\Values\FieldStoragePath;

/** Trusted PHP declaration; request data cannot select a rule, class or callback. */
final readonly class FieldValueContract
{
    /** @var list<string|object> */
    public array $rules;

    /** @var array<string, list<string|object>> */
    public array $nestedRules;

    /**
     * @param  list<string|object>  $rules
     * @param  array<string, list<string|object>>  $nestedRules  Relative paths; e.g. '*.title'.
     */
    public function __construct(public string $path, array $rules, public bool $encrypted = false, array $nestedRules = [])
    {
        $this->rules = ArraySnapshot::copy($rules);
        $this->nestedRules = ArraySnapshot::copy($nestedRules);

        FieldStoragePath::assert($path);
        ValidationRuleList::assert($rules);
        NestedValidationRules::assert($nestedRules);
    }
}
