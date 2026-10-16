<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Values\Submission;

use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEncryption;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/** Prepares only submitted, authorized values. The application owns transaction and persistence. */
final class FieldValueSubmission
{
    public const MAX_FIELDS = 256;

    /**
     * @param  mixed  $values  Untrusted JSON input; only an array of field values is accepted.
     * @param  list<FieldValueContract>  $contracts
     * @param  Closure(FieldValueContract): bool  $authorize  Resolve actor, tenant and operation in trusted PHP.
     * @return array<string, mixed>
     */
    public function prepare(mixed $values, array $contracts, Closure $authorize): array
    {
        if (! is_array($values)) {
            throw new InvalidArgumentException('Submitted field values must be an array.');
        }
        // Detach PHP references as well as checking the wire budget, before any
        // trusted validation callback can observe the input.
        $values = FieldValueCodec::copy($values);
        $tree = $this->paths($contracts);
        $tree->assertKnownKeys($values);
        $rules = [];
        $nested = new NestedValidationRules;
        foreach ($contracts as $contract) {
            $allowed = $authorize($contract);
            if (! is_bool($allowed)) {
                throw new InvalidArgumentException('Field write authorization must return a boolean.');
            }
            if (! $allowed) {
                if (Arr::has($values, $contract->path)) {
                    throw new AuthorizationException('Submitted field is not writable.');
                }

                continue;
            }
            $nested->append($contract, data_get($values, $contract->path), $rules);
        }
        $validated = Validator::make($values, $rules)->validate();
        $prepared = [];
        foreach ($contracts as $contract) {
            if (! array_key_exists($contract->path, $rules) || ! Arr::has($validated, $contract->path)) {
                continue;
            }
            $value = data_get($validated, $contract->path);
            // Client input is always plaintext, even when it resembles a stored envelope.
            data_set($prepared, $contract->path, $contract->encrypted && $value !== null ? FlexFieldEncryption::encrypt($value) : $value);
        }
        FieldValueCodec::assertCompatible($prepared);

        return $prepared;
    }

    /**
     * @param  list<FieldValueContract>  $contracts
     */
    private function paths(array $contracts): SubmissionPath
    {
        if (! array_is_list($contracts) || count($contracts) > self::MAX_FIELDS) {
            throw new InvalidArgumentException('Submission requires a bounded list of field contracts.');
        }
        $tree = new SubmissionPath;
        foreach ($contracts as $contract) {
            if (! $contract instanceof FieldValueContract) {
                throw new InvalidArgumentException('Submission contracts must be typed.');
            }
            $tree->add($contract->path);
        }

        return $tree;
    }
}
