<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDiagnosticReport;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions;
use Symfony\Component\Process\Process;

it('keeps unknown checks distinct from known configuration failures', function (): void {
    $report = new MediaDiagnosticReport(new MediaDoctorOptions);
    $report->add('storage_unverified', 'unverified', 'Not inspected', 'Verify storage');
    expect($report->toArray()['configurationValid'])->toBeTrue()
        ->and($report->toArray()['operationallyVerified'])->toBeFalse();
    $report->add('missing_disk', 'failed', 'Missing disk', 'Configure disk');
    expect($report->toArray()['configurationValid'])->toBeFalse()
        ->and($report->toArray()['counts'])->toMatchArray(['failed' => 1, 'unverified' => 1]);
});

it('bounds diagnostic output and rejects invalid check states', function (): void {
    $report = new MediaDiagnosticReport(new MediaDoctorOptions);
    expect(fn () => $report->add('invalid', 'healthy', 'Invalid', 'Fix'))->toThrow(LogicException::class);
    for ($index = 0; $index < 128; $index++) {
        $report->add('bounded', 'passed', 'Checked', 'Continue');
    }
    expect($report->toArray()['checks'])->toHaveCount(128)
        ->and(fn () => $report->add('overflow', 'passed', 'Overflow', 'Fix'))->toThrow(LogicException::class);
});

it('rejects malformed format collections without invoking objects', function (): void {
    $object = new class
    {
        public function __toString(): string
        {
            throw new RuntimeException('User objects must never be cast');
        }
    };
    expect(fn () => new MediaDoctorOptions(images: true, formats: [$object]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MediaDoctorOptions(images: true, formats: ['private' => 'webp']))->toThrow(InvalidArgumentException::class);
});

it('detects a genuinely disabled GD encoder function in a separate PHP process', function (): void {
    $code = <<<'PHP'
require $argv[1];
$options = new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions(images: true);
$report = new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDiagnosticReport($options);
(new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\GdCapabilityCheck)->inspect($report, $options);
echo json_encode($report->toArray(), JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-d', 'disable_functions=imagewebp', '-r', $code, dirname(__DIR__, 3).'/vendor/autoload.php']);
    $process->mustRun();
    $report = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    $check = array_values(array_filter($report['checks'], fn ($row) => $row['code'] === 'gd_output_webp'))[0];
    expect($check['status'])->toBe('failed')->and($check['action'])->toContain('requested output format');
});

it('detaches caller reference aliases from validated format options', function (): void {
    $format = 'webp';
    $options = new MediaDoctorOptions(images: true, formats: [&$format]);
    $format = 'private-customer-token';
    expect($options->formats)->toBe(['webp'])
        ->and((new MediaDiagnosticReport($options))->toArray()['profile']['formats'])->toBe(['webp']);
});

it('does not claim GD format support when its local support flags cannot be inspected', function (): void {
    $code = <<<'PHP'
require $argv[1];
$options = new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDoctorOptions(images: true);
$report = new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaDiagnosticReport($options);
(new Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\GdCapabilityCheck)->inspect($report, $options);
echo json_encode($report->toArray(), JSON_THROW_ON_ERROR);
PHP;
    $process = new Process([PHP_BINARY, '-d', 'disable_functions=gd_info', '-r', $code, dirname(__DIR__, 3).'/vendor/autoload.php']);
    $process->mustRun();
    $report = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    $check = array_values(array_filter($report['checks'], fn ($row) => $row['code'] === 'gd_output_webp'))[0];
    expect($check['status'])->toBe(function_exists('imagewebp') ? 'unverified' : 'failed');
});
