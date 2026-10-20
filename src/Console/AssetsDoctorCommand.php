<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Console;

use Bjanczak\FilamentFlexFields\Support\Assets\Build\AssetBuildValidator;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\BuildValidationReport;
use Bjanczak\FilamentFlexFields\Support\Assets\Build\PublishedBuildValidator;
use Bjanczak\FilamentFlexFields\Support\Assets\Delivery\AssetDeliveryContext;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\AssetDiagnosticAdvice;
use Bjanczak\FilamentFlexFields\Support\Assets\Diagnostics\ComponentRegistrationExplanation;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentRegistry;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fff:assets:doctor')]
final class AssetsDoctorCommand extends Command
{
    protected $signature = 'fff:assets:doctor {--json : Print a machine-readable report} {--share : Print redacted JSON with actionable advice for a support request} {--published : Also verify complete public release copies} {--public-root= : Existing shared public asset store; requires --published} {--definitions : Include installed definition origins and deprecations} {--locations : Include local source paths; requires --definitions}';

    protected $description = 'Validate FLEX build metadata, all sealed artifacts and installed package resources without publishing';

    public function handle(AssetBuildValidator $validator, ComponentRegistry $registry): int
    {
        if ($this->option('share') && ($this->option('definitions') || $this->option('locations'))) {
            $this->components->error('Shareable reports cannot include definition inventories or source locations.');

            return self::FAILURE;
        }
        if ($this->option('locations') && ! $this->option('definitions')) {
            $this->components->error('Local source locations require --definitions.');

            return self::FAILURE;
        }
        $report = $validator->validate($registry);
        if ($report->errorCount === 0) {
            try {
                app(AssetDeliveryContext::class)->policy()->json();
            } catch (InvalidArgumentException|JsonException $error) {
                $report = new BuildValidationReport($report->release, $report->artifacts, $report->packages,
                    [['code' => 'asset_delivery_invalid', 'subject' => 'assets.delivery',
                        'message' => 'Invalid application asset delivery policy; review its typed configuration.']], 1);
            }
        }
        $root = $this->option('public-root');
        if (($root !== null && ! $this->option('published')) || ($root !== null && (! is_string($root) || $root === ''))) {
            $report = new BuildValidationReport($report->release, $report->artifacts, $report->packages,
                [...$report->errors, ['code' => 'published_root_invalid', 'subject' => 'public-root', 'message' => 'An existing public root requires --published.']], $report->errorCount + 1);
        } elseif ($report->errorCount === 0 && $this->option('published')) {
            $prefix = config('filament.assets_path') ?? '';
            if (! is_string($prefix)) {
                $report = new BuildValidationReport($report->release, $report->artifacts, $report->packages,
                    [['code' => 'published_root_invalid', 'subject' => 'assets-path', 'message' => 'Asset prefix must be a safe relative directory.']], 1);
            } else {
                $report = app(PublishedBuildValidator::class)->validate($report, $registry, $root ?? public_path(), $prefix);
            }
        }
        $definitions = null;
        if ($this->option('definitions')) {
            try {
                $definitions = (new ComponentRegistrationExplanation)->inventory($registry, (bool) $this->option('locations'));
            } catch (InvalidArgumentException $error) {
                $this->components->error($error->getMessage());

                return self::FAILURE;
            }
        }
        $diagnosis = (new AssetDiagnosticAdvice)->explain($report);
        if ($this->option('share')) {
            $this->line(json_encode($diagnosis, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return $report->errorCount === 0 ? self::SUCCESS : self::FAILURE;
        }
        if ($this->option('json')) {
            $output = $report->toArray();
            $output['diagnosis'] = $diagnosis;
            if ($definitions !== null) {
                $output['registrations'] = $definitions;
            }
            $this->line(json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } elseif ($report->errorCount === 0) {
            $this->components->info(sprintf('Valid FLEX build %s: %d artifacts, %d external packages.', $report->release, $report->artifacts, $report->packages));
        } else {
            foreach ($report->errors as $index => $error) {
                $this->components->error($error['code'].' / '.$error['subject'].': '.$error['message']);
                if (isset($diagnosis['findings'][$index])) {
                    $this->line('Next step: '.$diagnosis['findings'][$index]['action']);
                }
            }
            $this->components->error(sprintf('Build rejected: %d errors. Follow the recommended steps and rerun this check.', $report->errorCount));
        }
        if ($definitions !== null && ! $this->option('json')) {
            $this->line(sprintf('Definition origins: %d known, %d undeclared. Deprecated components: %d.',
                $definitions['originsKnown'], $definitions['originsUnknown'], $definitions['deprecatedComponents']));
            foreach ($definitions['definitions'] as $id => $metadata) {
                if ($metadata['location'] !== null) {
                    $this->line($id.': '.$metadata['location']['file'].':'.$metadata['location']['line']);
                }
                if ($metadata['deprecation'] !== null) {
                    $this->components->warn($id.': '.$metadata['deprecation']['message'].' Replacement: '.$metadata['deprecation']['replacement'].'; removal not before '.$metadata['deprecation']['removalNotBefore'].'.');
                }
            }
        }

        return $report->errorCount === 0 ? self::SUCCESS : self::FAILURE;
    }
}
