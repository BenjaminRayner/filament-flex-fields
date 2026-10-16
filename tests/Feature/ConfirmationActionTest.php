<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Tests\Support\TestableConfirmationActions;
use Livewire\Livewire;

it('runs the real action and returns a receipt only after completion', function () {
    $component = Livewire::test(TestableConfirmationActions::class)->call('mountAction', 'confirm');
    $component->assertSet('confirmed', 1)->assertSet('restored', 0);
    expect($component->effects['returns'][0])->toMatchArray(['fffConfirmation' => true, 'action' => 'confirm', 'operation' => 'confirm', 'success' => true]);
});

it('uses the separately defined undo callback in the authorized action lifecycle', function () {
    $component = Livewire::test(TestableConfirmationActions::class)
        ->call('mountAction', 'confirm', ['_fff_confirmation_operation' => 'undo']);
    $component->assertSet('confirmed', 0)->assertSet('restored', 1)->assertSet('beforeCount', 1);
    expect($component->effects['returns'][0])->toMatchArray(['operation' => 'undo', 'success' => true]);
});

it('never executes disabled or unauthorized actions including forged undo calls', function (string $property) {
    $component = Livewire::test(TestableConfirmationActions::class)->set($property, $property === 'blocked')
        ->call('mountAction', 'confirm', ['_fff_confirmation_operation' => 'undo']);
    $component->assertSet('confirmed', 0)->assertSet('restored', 0)->assertSet('beforeCount', 0);
    expect($component->effects['returns'][0])->not->toBeArray();
})->with(['allowed', 'blocked']);

it('rejects malformed operations before running application hooks', function () {
    Livewire::test(TestableConfirmationActions::class)
        ->call('mountAction', 'confirm', ['_fff_confirmation_operation' => ['undo']])
        ->assertSet('beforeCount', 0)->assertSet('confirmed', 0)->assertSet('restored', 0)->assertHasErrors(['confirmation']);
});

it('propagates an explicit action failure instead of a successful animation result', function () {
    $component = Livewire::test(TestableConfirmationActions::class)->set('fail', true)->call('mountAction', 'confirm');
    expect($component->effects['returns'][0])->toMatchArray(['success' => false]);
});

it('requires and validates audit reason through the actual native form', function () {
    $component = Livewire::test(TestableConfirmationActions::class)->set('reasonRequired', true)->call('mountAction', 'confirm');
    $component->assertSet('confirmed', 0)->call('callMountedAction')->assertHasErrors()->assertSet('confirmed', 0);
    $component->set('mountedActions.0.data._fff_confirmation_reason', 'No longer needed')->call('callMountedAction')
        ->assertHasNoErrors()->assertSet('confirmed', 1)->assertSet('reason', 'No longer needed');
});

it('returns the actual server outcome for ordinary hold actions as well', function (bool $fail) {
    $component = Livewire::test(TestableConfirmationActions::class)->set('fail', $fail)
        ->call('mountAction', 'hold', ['_fff_confirmation_operation' => 'confirm']);
    $component->assertSet('confirmed', 1);
    expect($component->effects['returns'][0])->toMatchArray([
        'fffConfirmation' => true, 'action' => 'hold', 'operation' => 'confirm', 'success' => ! $fail,
    ]);
})->with([true, false]);
