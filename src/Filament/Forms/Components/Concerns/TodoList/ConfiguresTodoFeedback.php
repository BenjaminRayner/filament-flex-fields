<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList;

use Bjanczak\FilamentFlexFields\Support\TodoListFieldAudio;
use Closure;
use Filament\Forms\Components\Field;

trait ConfiguresTodoFeedback
{
    protected string|Closure|null $color = 'primary';

    protected bool|Closure $sounds = true;

    protected string|Closure|null $checkSound = null;

    protected string|Closure|null $accentSound = null;

    protected string|Closure|null $createSound = null;

    protected string|Closure|false|null $celebration = 'fireworks';

    protected int|Closure $celebrationDurationMs = 5500;

    protected string|Closure|null $celebrationSound = null;

    protected string|Closure|null $celebrationStartSound = null;

    protected bool|Closure $celebrationFullscreen = false;

    protected string|Closure $strikethroughStyle = 'hand';

    protected int|Closure $doneSettleMs = 500;

    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function sounds(bool|Closure $sounds = true): static
    {
        $this->sounds = $sounds;

        return $this;
    }

    public function checkSound(string|Closure|null $url): static
    {
        $this->checkSound = $url;

        return $this;
    }

    public function accentSound(string|Closure|null $url): static
    {
        $this->accentSound = $url;

        return $this;
    }

    public function createSound(string|Closure|null $url): static
    {
        $this->createSound = $url;

        return $this;
    }

    public function celebration(string|Closure|false|null $celebration): static
    {
        $this->celebration = $celebration;

        return $this;
    }

    public function celebrationDurationMs(int|Closure $ms): static
    {
        $this->celebrationDurationMs = $ms;

        return $this;
    }

    public function celebrationSound(string|Closure|null $url): static
    {
        $this->celebrationSound = $url;

        return $this;
    }

    public function celebrationStartSound(string|Closure|null $url): static
    {
        $this->celebrationStartSound = $url;

        return $this;
    }

    public function celebrationFullscreen(bool|Closure $fullscreen = true): static
    {
        $this->celebrationFullscreen = $fullscreen;

        return $this;
    }

    public function strikethroughStyle(string|Closure $style): static
    {
        $this->strikethroughStyle = $style;

        return $this;
    }

    public function doneSettleMs(int|Closure $ms): static
    {
        $this->doneSettleMs = $ms;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->evaluate($this->color);
    }

    public function soundsEnabled(): bool
    {
        return (bool) $this->evaluate($this->sounds);
    }

    public function getCheckSoundUrl(): string
    {
        return (string) ($this->evaluate($this->checkSound) ?: TodoListFieldAudio::url('todo-check.mp3'));
    }

    public function getAccentSoundUrl(): string
    {
        return (string) ($this->evaluate($this->accentSound) ?: TodoListFieldAudio::url('todo-accent.mp3'));
    }

    public function getCreateSoundUrl(): string
    {
        return (string) ($this->evaluate($this->createSound) ?: TodoListFieldAudio::url('todo-create.mp3'));
    }

    public function getCelebration(): string|false|null
    {
        $value = $this->evaluate($this->celebration);

        if ($value === false || $value === null || $value === '') {
            return $value === false ? false : null;
        }

        return (string) $value;
    }

    public function getCelebrationDurationMs(): int
    {
        return max(500, (int) $this->evaluate($this->celebrationDurationMs));
    }

    public function getCelebrationSoundUrl(): string
    {
        return (string) ($this->evaluate($this->celebrationSound) ?: TodoListFieldAudio::url('todo-fireworks-burst.mp3'));
    }

    public function getCelebrationStartSoundUrl(): string
    {
        return (string) ($this->evaluate($this->celebrationStartSound) ?: TodoListFieldAudio::url('todo-fireworks-start.mp3'));
    }

    /**
     * Per-celebration audio packs (start / burst). Field-level celebrationStartSound /
     * celebrationSound still override the fireworks pack used for complete-all.
     *
     * @return array<string, array{start?: string, burst?: string}>
     */
    public function getCelebrationAudio(): array
    {
        $fireworksStart = $this->getCelebrationStartSoundUrl();
        $fireworksBurst = $this->getCelebrationSoundUrl();

        return [
            'fireworks' => [
                'start' => $fireworksStart,
                'burst' => $fireworksBurst,
            ],
            'confetti' => [
                'start' => TodoListFieldAudio::url('todo-confetti.mp3'),
            ],
            'sparkles' => [
                'start' => TodoListFieldAudio::url('todo-sparkles.mp3'),
            ],
            'bloom' => [
                'start' => TodoListFieldAudio::url('todo-checks.mp3'),
            ],
        ];
    }

    public function isCelebrationFullscreen(): bool
    {
        return (bool) $this->evaluate($this->celebrationFullscreen);
    }

    public function getStrikethroughStyle(): string
    {
        $style = (string) $this->evaluate($this->strikethroughStyle);

        return in_array($style, ['hand', 'straight'], true) ? $style : 'hand';
    }

    public function getDoneSettleMs(): int
    {
        return max(0, (int) $this->evaluate($this->doneSettleMs));
    }
}
