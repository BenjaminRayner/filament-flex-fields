<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Support\Admin\HoldConfirmEnterprise;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;

class HoldConfirmPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('Hold confirm')
                ->description('Hold with a mouse, touch, Space or Enter. Loading stays visible until PHP returns a success or failure result.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    View::make('filament-flex-fields::partials.playground.hold-confirm-demo')
                        ->viewData([
                            'bulkHoldMs' => HoldConfirmEnterprise::bulkHoldMs(),
                            'requiresAuditReason' => HoldConfirmEnterprise::requiresAuditReason(),
                            'keyboard' => HoldConfirmEnterprise::keyboardContract(),
                        ]),
                    Actions::make([
                        Action::make('holdConfirmUpdate')
                            ->label('Hold to update')
                            ->color('primary')
                            ->holdConfirm(2000)
                            ->action(fn () => Notification::make()
                                ->title('Settings updated')
                                ->success()
                                ->send()),
                        Action::make('holdConfirmDeleteFast')
                            ->label('Hold to delete (800ms)')
                            ->icon(FlexIcon::TrashBin)
                            ->color('danger')
                            ->holdConfirm(800)
                            ->action(fn () => Notification::make()
                                ->title('Fast hold confirmed')
                                ->danger()
                                ->send()),
                        Action::make('holdConfirmDeleteSlow')
                            ->label('Hold to delete (4s, left)')
                            ->icon(FlexIcon::TrashBin)
                            ->color('danger')
                            ->holdConfirm(4000, 'left')
                            ->action(fn () => Notification::make()
                                ->title('Slow hold confirmed')
                                ->danger()
                                ->send()),
                        Action::make('holdConfirmBulk')
                            ->label('Hold to delete selected (bulk '.HoldConfirmEnterprise::bulkHoldMs().'ms)')
                            ->icon(FlexIcon::TrashBin)
                            ->color('danger')
                            ->holdConfirm(HoldConfirmEnterprise::bulkHoldMs())
                            ->action(fn () => Notification::make()
                                ->title('Bulk delete would fire')
                                ->danger()
                                ->send()),
                    ]),
                ]),
            Section::make('Confirm morph · click')
                ->description('A compact button expands into an inline question, shows the real action progress, then offers Undo. These demo actions show notifications only.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([Actions::make([
                    Action::make('morphArchive')
                        ->label('Archive project')->icon(FlexIcon::TrashBin)->color('danger')
                        ->confirmMorph('Archive this project?')
                        ->confirmMorphLabels(['confirm' => 'Archive', 'pending' => 'Archiving…', 'done' => 'Archived'])
                        ->confirmMorphUndo(fn () => Notification::make()->title('Project restored')->success()->send())
                        ->action(fn () => Notification::make()->title('Project archived')->success()->send()),
                ])]),
            Section::make('Confirm morph · hold')
                ->description('The second step requires a continuous hold. Release early, move outside the button or press Escape to cancel.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([Actions::make([
                    Action::make('morphHold')->label('Remove access')->icon(FlexIcon::TrashBin)->color('danger')
                        ->confirmMorph('Remove this access?', 'hold')->holdConfirmReleaseDuration(250)
                        ->confirmMorphLabels(['confirm' => 'Hold to remove', 'done' => 'Access removed'])
                        ->action(fn () => Notification::make()->title('Access removal confirmed')->success()->send()),
                ])]),
            Section::make('Confirm morph · countdown')
                ->description('Confirm starts a three-second countdown. Cancel or Escape stops it; Enter runs the action immediately.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([Actions::make([
                    Action::make('morphCountdown')->label('Publish changes')->icon(FlexIcon::Check)->color('primary')
                        ->confirmMorph('Publish these changes?', 'countdown')->confirmMorphCountdown(3)
                        ->confirmMorphLabels(['confirm' => 'Publish', 'pending' => 'Publishing…', 'done' => 'Published', 'countdown' => 'Publishing in'])
                        ->action(fn () => Notification::make()->title('Changes published')->success()->send()),
                ])]),
            Section::make('Failure, disabled state & audit reason')
                ->description('A failed operation offers Retry. A disabled action cannot open. Requiring an audit reason opens a validated form before the operation runs.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([Actions::make([
                    Action::make('morphFailure')->label('Try a failed operation')->color('danger')->confirmMorph('Run the failing demo?')
                        ->action(function (Action $action): void {
                            $action->failure();
                        }),
                    Action::make('holdFailure')->label('Hold to try a failure')->color('danger')->holdConfirm(800)
                        ->action(function (Action $action): void {
                            $action->failure();
                        }),
                    Action::make('morphDisabled')->label('Protected action')->confirmMorph()->disabled(),
                    Action::make('morphAudit')->label('Change with a reason')->color('primary')->confirmMorph('Continue with a reason?')
                        ->auditReasonRequired()->action(fn () => Notification::make()->title('Reason validated')->success()->send()),
                ])]),
        ];
    }
}
