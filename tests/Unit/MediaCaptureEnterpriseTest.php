<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CreditCardField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
use Bjanczak\FilamentFlexFields\Support\Media\MediaCaptureOs;
use Bjanczak\FilamentFlexFields\Support\Media\SpatieMediaCaptureAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    MediaCaptureOs::resetRuntimeState();
});

it('tokenizes credit card pan when callback is registered', function () {
    MediaCaptureOs::registerTokenizeCreditCardCallback(fn (string $pan): string => 'tok_'.substr($pan, -4));

    $field = CreditCardField::make('payment');

    expect($field->dehydrateStateForStorage([
        'number' => '4111 1111 1111 1111',
        'name' => 'Jane Doe',
        'expiry' => '12/28',
        'cvv' => '123',
    ]))->toMatchArray([
        'token' => 'tok_1111',
        'last4' => '1111',
        'name' => 'Jane Doe',
        'expiry' => '12/28',
    ])->and($field->dehydrateStateForStorage([
        'number' => '4111 1111 1111 1111',
        'name' => 'Jane Doe',
        'expiry' => '12/28',
        'cvv' => '123',
    ]))->not->toHaveKey('number');
});

it('fails closed when a registered tokenizer returns no token', function () {
    config(['filament-flex-fields.media_capture.pci.require_tokenization' => true]);
    MediaCaptureOs::registerTokenizeCreditCardCallback(fn (string $pan): ?string => null);
    expect(fn () => CreditCardField::make('payment')->dehydrateStateForStorage([
        'number' => '4111111111111111', 'name' => 'Jane Doe', 'expiry' => '12/28', 'cvv' => '123',
    ]))->toThrow(ValidationException::class);
});

it('fails pci validation when never_store_pan is enabled without tokenizer', function () {
    config(['filament-flex-fields.media_capture.pci.never_store_pan' => true]);
    MediaCaptureOs::registerTokenizeCreditCardCallback(null);

    $field = CreditCardField::make('payment');
    $failMessage = null;
    $fail = function (string $message) use (&$failMessage): void {
        $failMessage = $message;
    };

    expect($field->passesPciTokenizationRules([
        'number' => '4111111111111111',
        'name' => 'Jane Doe',
        'expiry' => '12/28',
        'cvv' => '123',
    ], $fail))->toBeFalse()
        ->and($failMessage)->toBe(__('filament-flex-fields::default.validation.credit_card.tokenization_required'));
});

it('merges flex capture metadata for spatie uploads', function () {
    $field = new FlexSpatieMediaLibraryFileUpload('attachment');
    $field->collection('documents');

    expect(SpatieMediaCaptureAdapter::enterpriseCustomProperties($field))->toMatchArray([
        'flex_capture' => [
            'field' => 'attachment',
            'collection' => 'documents',
        ],
    ]);
});

it('prunes expired disk files by directory age', function () {
    Storage::fake('local');
    Storage::disk('local')->put('voice-notes/old.webm', 'audio');
    Storage::disk('local')->put('voice-notes/new.webm', 'audio');

    touch(Storage::disk('local')->path('voice-notes/old.webm'), now()->subDays(10)->getTimestamp());
    touch(Storage::disk('local')->path('voice-notes/new.webm'), now()->subDays(1)->getTimestamp());

    $deleted = SpatieMediaCaptureAdapter::pruneDiskDirectories('local', ['voice-notes'], 5);

    expect($deleted)->toBe(['voice-notes/old.webm'])
        ->and(Storage::disk('local')->exists('voice-notes/new.webm'))->toBeTrue()
        ->and(Storage::disk('local')->exists('voice-notes/old.webm'))->toBeFalse();
});
