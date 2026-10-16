<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Schema;

use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueContract;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueSubmission;
use Closure;
use InvalidArgumentException;

/** Explicit public save boundary. No rendering, migration of client values or persistence. */
final class FamilySchemaSubmission
{
    public function __construct(private readonly FamilySchemaRenderer $renderer, private readonly FieldValueSubmission $submission) {}

    /**
     * @param  list<FamilySchemaDefinition>  $definitions  Trusted server-side definitions.
     * @param  Closure(FamilySchemaDefinition, FamilyRenderContext): bool  $authorizeWrite  Application record/tenant/edit policy.
     * @return array<string, mixed>
     */
    public function prepare(mixed $values, array $definitions, FamilyRenderContext $context, Closure $authorizeWrite): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('Submitted family values must be an array.');
        }
        FieldValueCodec::assertCompatible($values);
        if (! array_is_list($definitions) || count($definitions) > 256) {
            throw new InvalidArgumentException('Family submission requires a bounded definition list.');
        }
        $contracts = [];
        $permissions = [];
        foreach ($definitions as $definition) {
            if (! $definition instanceof FamilySchemaDefinition) {
                throw new InvalidArgumentException('Family submission requires typed server definitions.');
            }
            $path = $context->statePath($definition->slug);
            if (array_key_exists($path, $permissions)) {
                throw new InvalidArgumentException('Family submission has duplicate field paths.');
            }
            $allowed = $authorizeWrite($definition, $context);
            if (! is_bool($allowed)) {
                throw new InvalidArgumentException('Family write policy must return a boolean.');
            }
            $resolved = $this->renderer->resolve($definition, $context);
            if ($definition->export() !== $resolved->definition->export()) {
                $targetAllowed = $authorizeWrite($resolved->definition, $context);
                if (! is_bool($targetAllowed)) {
                    throw new InvalidArgumentException('Family write policy must return a boolean.');
                }
                $allowed = $allowed && $targetAllowed;
            }
            $definition = $resolved->definition;
            $permissions[$path] = $allowed && $definition->isActive && ! $definition->isDisabled && ! $definition->isReadOnly;
            $contracts[] = new FieldValueContract($path, $resolved->rules(requireValueRules: true));
        }

        return $this->submission->prepare($values, $contracts, static fn (FieldValueContract $contract): bool => $permissions[$contract->path]);
    }
}
