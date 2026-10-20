<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuildValidationReport;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\AssetDiagnosticAdvice;

it('maps each known build failure to a relevant repair without private details', function (string $code, string $area, string $repair): void {
    $report = new BuildValidationReport(str_repeat('a', 64), 580, 1, [
        ['code' => $code, 'subject' => '/private/customer/contracts', 'message' => 'https://private.test/file?signature=secret-token'],
    ], 1);
    $result = (new AssetDiagnosticAdvice)->explain($report);

    expect($result['valid'])->toBeFalse()
        ->and($result['findings'][0]['code'])->toBe($code)
        ->and($result['findings'][0]['area'])->toBe($area)
        ->and($result['findings'][0]['action'])->toMatch($repair)
        ->and($result['findings'][0]['impact'])->not->toBeEmpty()
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain('customer', 'private.test', 'secret-token');
})->with([
    ['release-seal', 'package-build', '/locked Composer release/'],
    ['metadata-parity', 'package-build', '/matching PHP sources/'],
    ['artifact-parity', 'package-build', '/filament:assets/'],
    ['missing-root', 'package-build', '/all declared entries and styles/'],
    ['first-paint-owner', 'package-build', '/Do not rename files/'],
    ['missing-entry', 'package-build', '/definition and Alpine build graph/'],
    ['php-projection', 'package-build', '/regenerate the index/'],
    ['alpine-graph', 'package-build', '/shared chunks and its Alpine manifest/'],
    ['package-parity', 'installed-extension', '/extension using its manifest/'],
    ['asset_delivery_invalid', 'asset-delivery', '/assets.delivery/'],
    ['published_root_invalid', 'publication', '/existing public directory/'],
    ['published_release_invalid', 'publication', '/each serving node/'],
]);

it('does not leak unknown codes, unverified release strings or exception text', function (): void {
    $private = 'private-customer-token';
    $report = new BuildValidationReport($private, 1, 0, [
        ['code' => $private, 'subject' => $private, 'message' => $private],
    ], 1);
    $result = (new AssetDiagnosticAdvice)->explain($report);

    expect($result['release'])->toBeNull()
        ->and($result['findings'][0]['code'])->toBe('unknown')
        ->and($result['findings'][0]['reference'])->toBe('issue-1')
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain($private);
});

it('bounds exported advice while preserving the total error count', function (): void {
    $errors = array_fill(0, 200, ['code' => 'artifact-parity', 'subject' => 'private', 'message' => 'private']);
    $report = new BuildValidationReport(null, 580, 0, $errors, 1000);
    $result = (new AssetDiagnosticAdvice)->explain($report);

    expect($result['findings'])->toHaveCount(128)
        ->and($result['errorCount'])->toBe(1000)
        ->and($result['omittedErrors'])->toBe(872)
        ->and($result['findings'][127]['reference'])->toBe('issue-128');
});

it('limits a clean report to the actual asset validation scope', function (): void {
    $result = (new AssetDiagnosticAdvice)->explain(new BuildValidationReport(str_repeat('b', 64), 580, 0, [], 0));

    expect($result['valid'])->toBeTrue()
        ->and($result['scope'])->toBe('asset-build-and-delivery')
        ->and($result['findings'])->toBe([])
        ->and($result['omittedErrors'])->toBe(0)
        ->and($result)->not->toHaveKeys(['media', 'storage', 'workers', 'formValues', 'registrations']);
});
