<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TextInput;

use Bjanczak\FilamentFlexFields\Support\Select\SelectSearchRateLimiter;
use Bjanczak\FilamentFlexFields\Support\Select\Transport\SearchQueryInput;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputMessages;
use Closure;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use InvalidArgumentException;
use Livewire\Attributes\Renderless;

/** Advisory checks never replace native validation or submit authorization. */
trait ConfiguresTextInputVerification
{
    protected ?Closure $textInputVerifier = null;

    public function verifyUsing(?Closure $callback): static
    {
        $this->textInputVerifier = $callback;

        return $this;
    }

    public function hasTextInputVerifier(): bool
    {
        return $this->textInputVerifier !== null;
    }

    public function verificationDebounce(int|Closure $milliseconds): static
    {
        return $this->textInputOption('verificationDebounce', $milliseconds);
    }

    public function verificationMinLength(int|Closure $length): static
    {
        return $this->textInputOption('verificationMinLength', $length);
    }

    /** @return array{state: string, message: string} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function verifyTextInputForJs(string $value): array
    {
        SearchQueryInput::assert($value);
        if (! $this->hasTextInputVerifier() || $this->isDisabled() || $this->isReadOnly() || $this->isSensitiveTextInput() || $this->isHidden()
            || mb_strlen($value) < $this->getTextInputEditingOptions()['verificationMinLength']) {
            return ['state' => 'idle', 'message' => ''];
        }
        if (! app(SelectSearchRateLimiter::class)->attempt('text-input-verification:'.$this->getKey())) {
            return ['state' => 'error', 'message' => TextInputMessages::get('verification_throttled')];
        }
        $result = $this->evaluate($this->textInputVerifier, ['value' => $value]);
        if (! is_array($result) || ! in_array($result['state'] ?? null, ['success', 'error', 'warning'], true)
            || ! is_string($result['message'] ?? null) || mb_strlen($result['message']) > 512) {
            throw new InvalidArgumentException('Verification must return a bounded state/message result.');
        }

        return ['state' => $result['state'], 'message' => $result['message']];
    }
}
