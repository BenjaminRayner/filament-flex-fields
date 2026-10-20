<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('reports real missing PHP runtime functions from an independent process without claiming conversion success', function (string $disabled, string $expected): void {
    $process = new Process([PHP_BINARY, '-d', 'disable_functions='.$disabled, dirname(__DIR__, 2).'/fixtures/media-image-probe.php']);
    $process->setTimeout(15);
    $process->run();
    expect($process->getExitCode())->toBe(1)->and($process->getErrorOutput())->toBe('');
    $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    expect(array_column($result['checks'], 'status', 'code')[$expected])->toBe('failed')
        ->and($result['passed'])->toBeFalse()->and($result['operationallyVerified'])->toBeFalse();
    if ($expected === 'image_webp') {
        expect(array_column($result['checks'], 'status', 'code')['image_cleanup'])->toBe('passed');
    }
})->with([
    ['imagewebp', 'image_webp'], ['imagecreatefrompng', 'image_webp'],
    ['tmpfile', 'image_source'], ['gzcompress', 'image_source'],
]);

it('passes the same standalone image fixture when runtime capabilities are available', function (): void {
    if (! function_exists('imagewebp') || ! function_exists('imagecreatefrompng')) {
        $this->markTestSkipped('Requires GD with PNG decoding and WebP encoding.');
    }
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/fixtures/media-image-probe.php']);
    $process->setTimeout(15);
    $process->run();
    expect($process->getExitCode())->toBe(0)->and($process->getErrorOutput())->toBe('');
    $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
    expect($result['passed'])->toBeTrue()->and($result['cleanupRequired'])->toBeFalse()
        ->and($result['counts'])->toBe(['passed' => 3, 'failed' => 0]);
});
