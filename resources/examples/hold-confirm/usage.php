<?php

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;

// Ordinary hold: progress continues until the actual PHP operation completes.
Action::make('apply')
    ->label('Hold to apply')
    ->color('primary')
    ->holdConfirm(1200)
    ->action(fn () => $this->applySettings());

// Direction and early-release animation.
Action::make('purge')
    ->label('Hold to purge')
    ->color('danger')
    ->holdConfirm(4000, 'left')
    ->holdConfirmReleaseDuration(150)
    ->action(fn () => $this->purgeCache());

// Inline question, genuine server result and a separately defined Undo operation.
Action::make('archive')
    ->label('Archive project')
    ->color('danger')
    ->confirmMorph('Archive this project?')
    ->confirmMorphLabels([
        'confirm' => 'Archive',
        'pending' => 'Archiving…',
        'done' => 'Archived',
        'undoing' => 'Restoring…',
    ])
    ->action(fn () => $this->archiveProject())
    ->confirmMorphUndo(fn () => $this->restoreProject());

// The question's confirm button requires a one-second hold.
Action::make('removeAccess')
    ->label('Remove access')
    ->color('danger')
    ->holdConfirm(1000)
    ->confirmMorph('Remove this access?', 'hold')
    ->confirmMorphLabels(['confirm' => 'Hold to remove'])
    ->action(fn () => $this->removeAccess());

// Countdown can be cancelled; Enter runs the operation immediately.
Action::make('publish')
    ->label('Publish changes')
    ->confirmMorph('Publish these changes?', 'countdown')
    ->confirmMorphCountdown(3)
    ->confirmMorphLabels(['countdown' => 'Publishing in'])
    ->action(fn () => $this->publishChanges());

// Explicit failure shows Retry instead of a successful result.
Action::make('process')
    ->label('Process request')
    ->confirmMorph('Process this request?')
    ->confirmMorphResultTimeout(0)
    ->action(function (Action $action): void {
        if (! $this->processRequest()) {
            $action->failure();
        }
    });

// The native modal validates the required reason before running the operation.
Action::make('archiveWithReason')
    ->label('Archive with a reason')
    ->confirmMorph('Continue with a reason?')
    ->auditReasonRequired()
    ->action(fn (array $data) => $this->archiveProject($data['_fff_confirmation_reason']));
