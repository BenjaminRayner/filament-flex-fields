<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

it('measures a native form, enabled empty plugin, one field and repeated fields in separate PHP boots', function (): void {
    $root = dirname(__DIR__, 2);
    $cases = [];
    foreach (['native', 'enabled-empty', 'one', 'twenty'] as $case) {
        $process = (new Process([PHP_BINARY, 'tests/Support/adoption-cost-fixture.php', $case], $root))->setTimeout(30)->mustRun();
        $cases[$case] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
    expect($cases['native']['consumerBoundaries'])->toBe(0)
        ->and($cases['enabled-empty']['consumerBoundaries'])->toBe(0)
        ->and($cases['one']['consumerBoundaries'])->toBe(1)
        ->and($cases['twenty']['consumerBoundaries'])->toBe(20)
        ->and($cases['one']['declaredFieldResources'])->not->toBeEmpty()
        ->and($cases['twenty']['declaredFieldResources'])->toBe($cases['one']['declaredFieldResources']);
    $directory = $root.'/build/ai-adoption';
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    file_put_contents($directory.'/minimal-cost.json', json_encode([
        'format' => 1, 'measuredAt' => gmdate(DATE_ATOM),
        'environment' => ['php' => PHP_VERSION, 'filament' => InstalledVersions::getPrettyVersion('filament/filament'),
            'livewire' => InstalledVersions::getPrettyVersion('livewire/livewire'), 'os' => PHP_OS_FAMILY],
        'scope' => 'Separate Testbench PHP boots and real schema rendering. Native case omits the FLEX provider; Composer packages remain installed. Declared field resources exclude transitive JS imports and global runtime; global markup is reported separately. Gzip is calculated locally, not an observed HTTP transfer. No recording overrides.',
        'limitations' => ['One sample per case, not a latency distribution.', 'Not a browser interaction/FPS, production TTFB or cold-disk benchmark.',
            'Does not measure Composer download size or optional services.', 'No AI/native implementation-time comparison has been performed.'],
        'cases' => $cases,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
});
