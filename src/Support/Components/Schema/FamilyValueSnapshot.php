<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;

/** A detached stored value and its exact schema versions. Not a client submission. */
final readonly class FamilyValueSnapshot
{
    public FamilySchemaDefinition $definition;

    public mixed $value;

    public function __construct(FamilySchemaDefinition $definition, mixed $value)
    {
        $budget = new JsonDataBudget(FieldValueCodec::MAX_BYTES, FieldValueCodec::MAX_NODES, 60);
        $this->value = $budget->copy($value);
        $this->definition = FamilySchemaDefinition::fromArray($budget->copy($definition->export()));
    }
}
