<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Components\Contracts\ComponentFamily;
use Bjanczak\FilamentFlexFields\Support\Runtime\ArraySnapshot;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\ValidationRuleList;
use InvalidArgumentException;

/** Detached request-local resolution shared by rendering and server-side submission. */
final readonly class ResolvedFamilySchema
{
    /** @var array<string, mixed> */
    public array $configuration;

    /** @param array<string, mixed> $configuration */
    public function __construct(
        public ComponentFamily $family,
        public FamilySchemaDefinition $definition,
        public FamilySchemaAdapter $adapter,
        public FamilyRenderContext $context,
        array $configuration,
    ) {
        $this->configuration = ArraySnapshot::copy($configuration);
    }

    /** @return list<string|object> */
    public function rules(bool $requireValueRules = false): array
    {
        $rules = $this->adapter->rules($this->definition, $this->context, $this->configuration);
        ValidationRuleList::assert($rules, allowEmpty: ! $requireValueRules);
        if ($this->definition->isRequired && ! in_array('required', $rules, true)) {
            array_unshift($rules, 'required');
        }
        if (count($rules) > 128) {
            throw new InvalidArgumentException('Family value rules including required exceed the rule budget.');
        }

        return ArraySnapshot::copy($rules);
    }
}
