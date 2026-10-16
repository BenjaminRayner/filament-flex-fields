<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Intelligence\Calculation;

use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\CompiledFormulaMap;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaCatalog;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Formula\FormulaLexer;
use Bjanczak\FilamentFlexFields\Support\Intelligence\Validation\NumericInput;
use InvalidArgumentException;

/** An immutable, reusable server-side plan. No form values are cached. */
final readonly class CalculationPlan
{
    private CompiledFormulaMap $compiled;

    /**
     * @param  array<string, string>  $formulas
     * @param  array<string, NumericInput>  $inputs
     * @param  list<string>|null  $allowedFunctions
     */
    public function __construct(
        public array $formulas,
        public array $inputs,
        ?array $allowedFunctions = null,
    ) {
        if ($formulas === [] || count($inputs) > 512) {
            throw new InvalidArgumentException('A calculation requires outputs and at most 512 inputs.');
        }
        foreach ($inputs as $key => $input) {
            if (! is_string($key) || strlen($key) > 128 || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $key) || ! $input instanceof NumericInput || array_key_exists($key, $formulas)) {
                throw new InvalidArgumentException('Calculation inputs must have unique valid names and numeric contracts.');
            }
        }
        $allowedFunctions ??= FormulaCatalog::ALLOWED_FUNCTIONS;
        if (! array_is_list($allowedFunctions) || count($allowedFunctions) > count(FormulaCatalog::ALLOWED_FUNCTIONS) || array_unique($allowedFunctions) !== $allowedFunctions || array_diff($allowedFunctions, FormulaCatalog::ALLOWED_FUNCTIONS) !== []) {
            throw new InvalidArgumentException('Function policy must be a subset of the supported functions.');
        }
        $this->compiled = new CompiledFormulaMap($formulas);
        foreach ($formulas as $key => $expression) {
            foreach (FormulaLexer::fieldReferences($expression) as $reference) {
                if (! array_key_exists($reference, $inputs) && ! array_key_exists($reference, $formulas)) {
                    throw new InvalidArgumentException("Undeclared calculation input [{$reference}] in [{$key}].");
                }
            }
            if (array_diff(FormulaLexer::functionsUsed($expression), $allowedFunctions) !== []) {
                throw new InvalidArgumentException("Calculation [{$key}] uses a function excluded by its policy.");
            }
        }
    }

    /** Client-supplied computed outputs are never used as calculation inputs.
     * @param  array<string, mixed>  $values
     */
    public function evaluate(array $values): CalculationResult
    {
        $normalized = [];
        $errors = [];
        foreach ($this->inputs as $key => $contract) {
            try {
                $normalized[$key] = $contract->validate($values[$key] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors[$key] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            return new CalculationResult([], $errors);
        }
        try {
            return new CalculationResult($this->compiled->evaluate($normalized), evaluated: $this->compiled->computedKeys());
        } catch (InvalidArgumentException $exception) {
            return new CalculationResult([], ['calculation' => $exception->getMessage()]);
        }
    }

    /** @return list<string> */
    public function order(): array
    {
        return $this->compiled->computedKeys();
    }

    /** @return array<string, list<string>> */
    public function dependencies(): array
    {
        $dependencies = [];
        foreach ($this->formulas as $key => $expression) {
            $dependencies[$key] = FormulaLexer::fieldReferences($expression);
        }

        return $dependencies;
    }
}
