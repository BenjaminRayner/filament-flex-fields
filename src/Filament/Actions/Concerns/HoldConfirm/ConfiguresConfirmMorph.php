<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Actions\Concerns\HoldConfirm;

use Bjanczak\FilamentFlexFields\Support\Confirmation\ConfirmationOptions;
use Closure;
use Filament\Actions\Enums\ActionStatus;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

trait ConfiguresConfirmMorph
{
    protected string|Closure|null $confirmMorphPrompt = null;

    protected string|Closure $confirmMorphMode = 'press';

    protected int|Closure $confirmMorphCountdown = 3;

    protected int|Closure $confirmMorphTimeout = 6000;

    protected int|Closure $confirmMorphResultTimeout = 5000;

    protected bool|Closure $confirmMorphCancelOnOutsidePress = true;

    /** @var array<string, string>|Closure */
    protected array|Closure $confirmMorphLabels = [];

    protected ?Closure $confirmMorphUndo = null;

    public function confirmMorph(string|Closure|null $prompt = null, string|Closure $mode = 'press'): static
    {
        $this->confirmMorphPrompt = $prompt;
        $this->confirmMorphMode = $mode;
        $this->holdConfirmDuration ??= 1000;
        $this->enableConfirmation('morph');

        return $this;
    }

    public function confirmMorphMode(string|Closure $mode): static
    {
        $this->confirmMorphMode = $mode;

        return $this;
    }

    public function confirmMorphCountdown(int|Closure $seconds): static
    {
        $this->confirmMorphCountdown = $seconds;

        return $this;
    }

    public function confirmMorphTimeout(int|Closure $milliseconds): static
    {
        $this->confirmMorphTimeout = $milliseconds;

        return $this;
    }

    public function confirmMorphResultTimeout(int|Closure $milliseconds): static
    {
        $this->confirmMorphResultTimeout = $milliseconds;

        return $this;
    }

    public function confirmMorphCancelOnOutsidePress(bool|Closure $condition = true): static
    {
        $this->confirmMorphCancelOnOutsidePress = $condition;

        return $this;
    }

    /** @param array<string, string>|Closure $labels */
    public function confirmMorphLabels(array|Closure $labels): static
    {
        $this->confirmMorphLabels = $labels;

        return $this;
    }

    public function confirmMorphUndo(?Closure $callback): static
    {
        $this->confirmMorphUndo = $callback;

        return $this;
    }

    public function getConfirmMorphMode(): string
    {
        return ConfirmationOptions::choice($this->evaluate($this->confirmMorphMode), 'confirmation mode', ['press', 'hold', 'countdown']);
    }

    /** @return array<string, mixed> */
    public function getConfirmMorphConfiguration(): array
    {
        $seconds = $this->evaluate($this->confirmMorphCountdown);
        if (! is_int($seconds) || $seconds < 1 || $seconds > 30) {
            throw new InvalidArgumentException('Countdown requires 1–30 seconds.');
        }
        $labels = [];
        foreach (['confirm', 'cancel', 'pending', 'done', 'error', 'retry', 'undo', 'undoing', 'undone', 'countdown', 'hold', 'cancelled'] as $key) {
            $labels[$key] = ConfirmationOptions::text(__('filament-flex-fields::confirmation.'.$key), 'Confirmation label');
        }
        $overrides = $this->evaluate($this->confirmMorphLabels);
        if (! is_array($overrides) || array_diff(array_keys($overrides), array_keys($labels)) !== []) {
            throw new InvalidArgumentException('Unknown confirmation label keys.');
        }
        foreach ($overrides as $key => $value) {
            $labels[$key] = ConfirmationOptions::text($value, 'Confirmation label');
        }
        $label = $this->getLabel();
        $labelText = $label instanceof Htmlable ? $label->toHtml() : ($label ?? '');
        $prompt = $this->evaluate($this->confirmMorphPrompt) ?? strip_tags($labelText).'?';

        return [
            'prompt' => ConfirmationOptions::text($prompt, 'Confirmation prompt'),
            'mode' => $this->getConfirmMorphMode(), 'countdown' => $seconds,
            'confirmTimeout' => ConfirmationOptions::milliseconds($this->evaluate($this->confirmMorphTimeout), 'Confirmation timeout'),
            'resultTimeout' => ConfirmationOptions::milliseconds($this->evaluate($this->confirmMorphResultTimeout), 'Result timeout'),
            'cancelOnOutsidePress' => (bool) $this->evaluate($this->confirmMorphCancelOnOutsidePress),
            'hasUndo' => $this->confirmMorphUndo !== null && blank($this->getUrl()), 'labels' => $labels,
        ];
    }

    protected function resolveConfirmationCallback(?Closure $callback): ?Closure
    {
        if (! $this->hasHoldConfirm()) {
            return $callback;
        }
        $operation = $this->getArguments()['_fff_confirmation_operation'] ?? 'confirm';
        if ($operation === 'confirm') {
            return $callback;
        }
        if ($operation === 'undo' && $this->isConfirmMorph() && $this->confirmMorphUndo !== null) {
            return $this->confirmMorphUndo;
        }
        throw ValidationException::withMessages(['confirmation' => ['Invalid confirmation operation.']]);
    }

    /** A receipt is returned only after the real action and its after-hook execute. */
    protected function confirmationReceipt(mixed $result): mixed
    {
        if (! $this->hasHoldConfirm()) {
            return $result;
        }

        return [
            'fffConfirmation' => true, 'action' => $this->getName(),
            'operation' => $this->getArguments()['_fff_confirmation_operation'] ?? 'confirm',
            'success' => $this->getStatus() === ActionStatus::Success,
        ];
    }
}
