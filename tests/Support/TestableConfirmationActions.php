<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

class TestableConfirmationActions extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public bool $allowed = true;

    public bool $blocked = false;

    public bool $fail = false;

    public bool $reasonRequired = false;

    public int $confirmed = 0;

    public int $restored = 0;

    public int $beforeCount = 0;

    public ?string $reason = null;

    public function confirmAction(): Action
    {
        return Action::make('confirm')->label('Archive project')->confirmMorph('Archive this project?')
            ->authorize(fn (): bool => $this->allowed)->disabled(fn (): bool => $this->blocked)
            ->auditReasonRequired(fn (): bool => $this->reasonRequired)
            ->before(function (): void {
                $this->beforeCount++;
            })
            ->action(function (Action $action, array $data): void {
                $this->confirmed++;
                $this->reason = $data['_fff_confirmation_reason'] ?? null;
                if ($this->fail) {
                    $action->failure();
                }
            })
            ->confirmMorphUndo(function (): void {
                $this->restored++;
            });
    }

    public function render(): string
    {
        return '<div>{{ $this->confirmAction }}<x-filament-actions::modals /></div>';
    }

    public function holdAction(): Action
    {
        return Action::make('hold')->holdConfirm(800)
            ->action(function (Action $action): void {
                $this->confirmed++;
                if ($this->fail) {
                    $action->failure();
                }
            });
    }
}
