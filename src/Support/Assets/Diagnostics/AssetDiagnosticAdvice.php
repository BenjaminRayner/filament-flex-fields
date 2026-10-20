<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuildValidationReport;

/** Bounded, allowlisted advice; never copy exception text or installation paths. */
final class AssetDiagnosticAdvice
{
    /** @var array<string, array{string, string, string}> */
    private const array ADVICE = [
        'release-seal' => ['package-build', 'The packaged browser release cannot be verified.',
            'Restore the complete package from the locked Composer release. Package authors must rebuild and seal the distribution before publishing.'],
        'metadata-parity' => ['package-build', 'PHP definitions and packaged build metadata no longer match.',
            'Restore matching PHP sources and build metadata from one package release. Authors changing definitions must regenerate the complete build.'],
        'artifact-parity' => ['package-build', 'At least one packaged browser file differs from its recorded bytes.',
            'Restore the installed package from its verified release, then run php artisan filament:assets and repeat the published check.'],
        'missing-root' => ['package-build', 'A required browser entry or stylesheet is missing from the sealed release.',
            'Install a complete package release. Authors must rebuild all declared entries and styles together rather than copying individual files.'],
        'first-paint-owner' => ['package-build', 'An early browser entry has an inconsistent content identity.',
            'Restore the matching generated first-paint entries and release manifest. Do not rename files or add timestamp cache parameters.'],
        'missing-entry' => ['package-build', 'A declared component cannot resolve its packaged JavaScript entry.',
            'Restore the component definition and Alpine build graph from the same release. Authors must include the entry when rebuilding.'],
        'php-projection' => ['package-build', 'The generated PHP component index differs from the typed definitions.',
            'Install matching package sources and generated files. Package authors must regenerate the index through the normal build.'],
        'alpine-graph' => ['package-build', 'The packaged JavaScript dependency graph cannot be verified.',
            'Restore a complete built distribution, including shared chunks and its Alpine manifest. Recheck the source build before publishing assets.'],
        'package-parity' => ['installed-extension', 'An installed extension has missing or modified declared assets.',
            'Restore or rebuild the affected extension using its manifest, then run php artisan filament:assets and repeat the published check.'],
        'asset_delivery_invalid' => ['asset-delivery', 'The configured browser delivery policy is invalid.',
            'Review filament-flex-fields.assets.delivery against the delivery guide, including allowed origins, integrity values and boolean settings.'],
        'published_root_invalid' => ['publication', 'The requested public asset root or asset prefix is invalid.',
            'Use --public-root only with --published and an existing public directory. Keep filament.assets_path a safe relative directory.'],
        'published_release_invalid' => ['publication', 'A published release is unavailable, incomplete, changed or inaccessible.',
            'Verify public-root access, publish the verified installed build with php artisan filament:assets, then rerun --published on each serving node.'],
    ];

    /**
     * @return array{format: int, scope: string, valid: bool, release: ?string, artifacts: int,
     *     packages: int, errorCount: int, omittedErrors: int,
     *     findings: list<array{reference: string, code: string, area: string, impact: string, action: string}>,
     *     documentation: string}
     */
    public function explain(BuildValidationReport $report): array
    {
        $findings = [];
        foreach (array_slice($report->errors, 0, 128) as $index => $error) {
            $known = isset(self::ADVICE[$error['code']]);
            [$area, $impact, $action] = $known ? self::ADVICE[$error['code']] : [
                'asset-validation', 'Asset validation reported an unrecognized failure.',
                'Review the local doctor report and the troubleshooting guide. Share this redacted report when requesting support.',
            ];
            $findings[] = ['reference' => 'issue-'.($index + 1), 'code' => $known ? $error['code'] : 'unknown',
                'area' => $area, 'impact' => $impact, 'action' => $action];
        }

        return ['format' => 1, 'scope' => 'asset-build-and-delivery', 'valid' => $report->errorCount === 0,
            'release' => is_string($report->release) && preg_match('/^[a-f0-9]{64}$/D', $report->release) === 1 ? $report->release : null,
            'artifacts' => $report->artifacts, 'packages' => $report->packages, 'errorCount' => $report->errorCount,
            'omittedErrors' => max(0, $report->errorCount - count($findings)), 'findings' => $findings,
            'documentation' => 'https://flex-fields.bjanczak.com/docs/troubleshooting'];
    }
}
