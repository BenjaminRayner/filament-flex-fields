<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('exports a clean installed build as redacted JSON without extra flags', function (): void {
    expect(Artisan::call('fff:assets:doctor', ['--share' => true]))->toBe(0);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);

    expect($report['valid'])->toBeTrue()
        ->and($report['artifacts'])->toBeGreaterThan(0)
        ->and($report['findings'])->toBe([])
        ->and($report)->not->toHaveKeys(['errors', 'registrations', 'subjects']);
});

it('keeps the failing exit status and gives a repair without exposing a supplied path', function (): void {
    $path = sys_get_temp_dir().'/fff-doctor-private-customer-path';
    expect(Artisan::call('fff:assets:doctor', ['--share' => true, '--public-root' => $path]))->toBe(1);
    $output = Artisan::output();
    $report = json_decode($output, true, 32, JSON_THROW_ON_ERROR);

    expect($report['valid'])->toBeFalse()
        ->and($report['findings'][0]['code'])->toBe('published_root_invalid')
        ->and($report['findings'][0]['action'])->toContain('--published')
        ->and($output)->not->toContain($path, 'private-customer')
        ->and(is_dir($path))->toBeFalse();
});

it('preserves local JSON error details and adds their corresponding advice', function (): void {
    config()->set('filament-flex-fields.assets.delivery', ['require_integrity' => 'invalid-secret-value']);
    expect(Artisan::call('fff:assets:doctor', ['--json' => true]))->toBe(1);
    $report = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);

    expect($report['errors'][0]['code'])->toBe('asset_delivery_invalid')
        ->and($report['diagnosis']['findings'][0]['code'])->toBe('asset_delivery_invalid')
        ->and($report['diagnosis']['findings'][0]['reference'])->toBe('issue-1');
});

it('prints a targeted next step in the terminal instead of prescribing a rebuild for every error', function (): void {
    expect(Artisan::call('fff:assets:doctor', ['--public-root' => 'unused-private-path']))->toBe(1);

    expect(Artisan::output())->toContain('Next step:', 'Use --public-root only with --published')
        ->not->toContain('Rebuild and rerun before deployment');
});

it('rejects source and definition inventories in a shareable export', function (string $option): void {
    expect(Artisan::call('fff:assets:doctor', ['--share' => true, $option => true]))->toBe(1)
        ->and(Artisan::output())->toContain('cannot include definition inventories or source locations');
})->with(['--definitions', '--locations']);
