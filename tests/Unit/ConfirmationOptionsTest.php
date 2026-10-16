<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Support\Admin\HoldConfirmEnterprise;
use Bjanczak\FilamentFlexFields\Support\Confirmation\AuditReasonSchema;
use Bjanczak\FilamentFlexFields\Support\Confirmation\ConfirmationRenderState;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableConfirmationActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

it('configures all confirmation modes with bounded labels and timeouts', function (string $mode) {
    $action = Action::make('confirm')->confirmMorph('Continue?', $mode)->holdConfirm(900)->confirmMorph('Continue?', $mode)
        ->confirmMorphCountdown(5)->confirmMorphTimeout(0)->confirmMorphResultTimeout(0)->confirmMorphLabels(['confirm' => 'Archive'])
        ->confirmMorphUndo(fn () => null);
    expect($action->getConfirmationConfiguration())->toMatchArray([
        'variant' => 'morph', 'duration' => 900, 'mode' => $mode, 'confirmTimeout' => 0, 'resultTimeout' => 0, 'countdown' => 5, 'hasUndo' => true,
    ])->and($action->getConfirmMorphConfiguration()['labels']['confirm'])->toBe('Archive');
})->with(['press', 'hold', 'countdown']);

it('rejects invalid time budgets and modes without silently clamping', function (string $method, mixed $value) {
    $action = Action::make('test')->confirmMorph();
    $action->{$method}($value);
    $action->getConfirmationConfiguration();
})->with([
    ['holdConfirm', 0], ['holdConfirm', 60_001], ['holdConfirmReleaseDuration', -1], ['holdConfirmReleaseDuration', 5001],
    ['confirmMorphCountdown', 0], ['confirmMorphCountdown', 31], ['confirmMorphMode', 'unsafe'],
    ['confirmMorphTimeout', -1], ['confirmMorphResultTimeout', 60_001],
])->throws(InvalidArgumentException::class);

it('rejects malformed label overrides and unsupported undo operations', function () {
    expect(fn () => Action::make('test')->confirmMorph()->confirmMorphLabels(['unknown' => 'x'])->getConfirmationConfiguration())->toThrow(InvalidArgumentException::class)
        ->and(fn () => Action::make('test')->confirmMorph()->confirmMorphLabels(['confirm' => str_repeat('x', 241)])->getConfirmationConfiguration())->toThrow(InvalidArgumentException::class)
        ->and(fn () => Action::make('test')->confirmMorph()->arguments(['_fff_confirmation_operation' => 'undo'])->getActionFunction())->toThrow(ValidationException::class);
});

it('keeps consumer identities independent even when the same action renders twice', function () {
    $action = Action::make('test')->confirmMorph();
    $first = ConfirmationRenderState::from($action);
    $second = ConfirmationRenderState::from($action);
    expect($first->consumer)->not->toBe($second->consumer)->and($first->key)->not->toBe($second->key);
    $html = $action->toHtml();
    expect($html)->toContain('data-fff-asset', 'data-variant="morph"', 'data-face="confirming"', 'data-face="error"')
        ->not->toContain('rel="modulepreload"', 'x-init=', 'wire:loading');
});

it('validates global bulk hold configuration', function () {
    expect(fn () => HoldConfirmEnterprise::bulkHoldMs(-1))->toThrow(InvalidArgumentException::class);
});

it('does not permit a disabled schema or a reserved field collision to bypass the audit reason', function () {
    expect(fn () => Action::make('test')->confirmMorph()->auditReasonRequired()->disabledSchema()->getSchema(Schema::make()))
        ->toThrow(LogicException::class)
        ->and(fn () => AuditReasonSchema::append(Schema::make(new TestableConfirmationActions)->components([TextInput::make(AuditReasonSchema::FIELD)])))
        ->toThrow(LogicException::class);
});
