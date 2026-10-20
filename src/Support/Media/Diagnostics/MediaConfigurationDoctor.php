<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Media\Diagnostics;

use Bjanczak\FilamentFlexFields\Support\Media\Images\ImageLimits;
use Bjanczak\FilamentFlexFields\Support\Media\Images\Queue\ConversionQueueOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaDefaults;
use Bjanczak\FilamentFlexFields\Support\Media\Runtime\MediaExtensionState;
use Bjanczak\FilamentFlexFields\Support\Media\Staging\StageOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Storage\MediaAddress;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Encryption\Encrypter;
use Throwable;

/** Configuration inspection only; no adapter, scanner, queue or storage is invoked. */
final class MediaConfigurationDoctor
{
    public function __construct(
        private readonly Repository $config,
        private readonly DiskConfigurationCheck $disks,
        private readonly GdCapabilityCheck $gd,
        private readonly MediaExtensionState $extensions,
        private readonly Schedule $schedule,
    ) {}

    public function inspect(MediaDoctorOptions $options): MediaDiagnosticReport
    {
        $report = new MediaDiagnosticReport($options);
        $this->policy($report);
        $this->encryption($report);
        $this->disks->inspect($report, 'original', $this->config->get('filament-flex-fields.media_capture.disk'),
            $this->config->get('filament-flex-fields.media_capture.visibility', 'private') !== 'public');
        $this->disks->inspect($report, 'staging', $this->config->get('filament-flex-fields.media_capture.staging.disk', 'local'), true);
        $this->disks->inspect($report, 'receipts', $this->config->get('filament-flex-fields.media_capture.uploads.manifest_disk', 'local'), true);
        try {
            StageOptions::fromConfig();
            $report->add('staging_options', 'passed', 'The configured staging bounds and relative directory are valid.',
                'Keep the same staging configuration on processes that create, accept or clean up these operations.');
        } catch (Throwable) {
            $report->add('staging_options_invalid', 'failed', 'The staging directory, expiry, lock or prune bounds are invalid.',
                'Review media_capture.staging: use a safe relative directory, TTLs at least 60, lock_seconds at least 30, a shorter nonnegative lock_wait_seconds and prune_batch_size 1–10000.');
            $this->directory($report, 'staging_directory', 'filament-flex-fields.media_capture.staging.directory', 'media-os-staging');
            $this->integerBounds($report, 'staging', ['ttl_seconds' => [60, PHP_INT_MAX, 86400], 'receipt_ttl_seconds' => [60, PHP_INT_MAX, 604800], 'lock_seconds' => [30, PHP_INT_MAX, 300], 'lock_wait_seconds' => [0, PHP_INT_MAX, 10], 'prune_batch_size' => [1, 10000, 1000]]);
            $lock = $this->config->get('filament-flex-fields.media_capture.staging.lock_seconds', 300);
            $wait = $this->config->get('filament-flex-fields.media_capture.staging.lock_wait_seconds', 10);
            if (is_int($lock) && is_int($wait) && $wait >= $lock) {
                $report->add('staging_lock_wait_invalid', 'failed', 'The staging lock wait reaches or exceeds its lease.', 'Set media_capture.staging.lock_wait_seconds below lock_seconds.');
            }
        }
        foreach (['manifest_directory', 'lease_directory'] as $key) {
            $this->directory($report, 'uploads_'.$key, 'filament-flex-fields.media_capture.uploads.'.$key,
                $key === 'manifest_directory' ? 'media-os-upload-receipts' : 'media-os-upload-leases');
        }
        $this->cache($report);
        $this->schedules($report, $options);
        if ($options->checksImages()) {
            try {
                ImageLimits::fromConfig();
                $report->add('image_limits', 'passed', 'The declared image budgets are valid.',
                    'Test representative source dimensions against real worker memory; valid limits alone do not guarantee every image fits.');
            } catch (Throwable) {
                $report->add('image_limits_invalid', 'failed', 'The image pixel, variant or working-memory limits are invalid.',
                    'Correct media_capture.images source_pixels, output_pixels, variants and working_bytes using the image configuration guide.');
                $this->integerBounds($report, 'images', ['source_pixels' => [1, 100000000, 16000000], 'output_pixels' => [1, 100000000, 16000000], 'variants' => [1, 64, 16], 'working_bytes' => [1048576, PHP_INT_MAX, 268435456]]);
            }
            $this->gd->inspect($report, $options);
        }
        if ($options->queued) {
            $this->disks->inspect($report, 'journal', $this->config->get('filament-flex-fields.media_capture.images.queue.journal_disk', 'local'), true);
            $this->directory($report, 'journal_directory', 'filament-flex-fields.media_capture.images.queue.journal_directory', 'media-os-conversions');
            $this->queue($report, $options);
        }
        $report->add('storage_access_unverified', 'unverified', 'Disk declarations do not prove credentials, permissions, network access or private serving.',
            'Verify reading, writing and private access in your deployment checks. No files or requests were created by this command.');
        if ($options->queued || $options->distributed) {
            $report->add('shared_context_unverified', 'unverified', 'A local inspection cannot prove that processes share disks, locks, signing keys and compatible releases.',
                'Run this profile on web, worker and scheduler hosts; verify shared recovery storage, atomic locks, application keys and package/config releases across them.');
        }

        return $report;
    }

    private function policy(MediaDiagnosticReport $report): void
    {
        try {
            $policy = (new MediaDefaults)->policy();
            $report->add('media_policy', 'passed', 'The default media policy can be constructed.',
                'Per-field or per-operation overrides still require their own validation.');
            if ($policy->quarantineDisk !== null) {
                $this->disks->inspect($report, 'quarantine', $policy->quarantineDisk, true);
            }
            if ($policy->requireScanner) {
                $report->add('required_scanner', $this->extensions->scanners === [] ? 'failed' : 'unverified',
                    'Required scanning needs a registered scanner that can certify the file.',
                    'Register a Media OS scanner in application bootstrap, then verify its availability separately. The doctor never constructs or invokes scanner factories.');
            }
        } catch (Throwable) {
            $report->add('media_policy_invalid', 'failed', 'The default byte limits, MIME policy, visibility or scanning boundaries are invalid.',
                'Correct media_capture policy values. Required scanning must remain enabled before and after writes.');
            $this->integerBounds($report, '', ['max_bytes' => [1, PHP_INT_MAX - 1, 268435456], 'min_bytes' => [0, PHP_INT_MAX - 1, 0]]);
            $max = $this->config->get('filament-flex-fields.media_capture.max_bytes', 268435456);
            $min = $this->config->get('filament-flex-fields.media_capture.min_bytes', 0);
            if (is_int($max) && is_int($min) && $min > $max) {
                $report->add('media_byte_order_invalid', 'failed', 'The minimum file size exceeds the maximum.', 'Set media_capture.min_bytes no higher than max_bytes.');
            }
        }
    }

    /** @param array<string, array{int, int, int}> $bounds */
    private function integerBounds(MediaDiagnosticReport $report, string $section, array $bounds): void
    {
        foreach ($bounds as $key => [$minimum, $maximum, $default]) {
            $option = 'media_capture.'.($section === '' ? '' : $section.'.').$key;
            $raw = $this->config->get('filament-flex-fields.'.$option, $default);
            $value = $section === 'images' ? filter_var($raw, FILTER_VALIDATE_INT) : $raw;
            if (! is_int($value) || $value < $minimum || $value > $maximum) {
                $report->add(($section === '' ? 'media' : $section).'_'.$key.'_invalid', 'failed',
                    'A numeric media configuration boundary is invalid.',
                    'Set '.$option.' to an integer from '.$minimum.' through '.$maximum.'.');
            }
        }
    }

    private function queueFailure(MediaDiagnosticReport $report, MediaDoctorOptions $options): void
    {
        $connection = $options->connection ?? $this->config->get('queue.default');
        $connections = $this->config->get('queue.connections', []);
        $configuration = is_string($connection) && is_array($connections) ? ($connections[$connection] ?? null) : null;
        if (! is_array($configuration)) {
            $report->add('queue_connection_missing', 'failed', 'The selected queue connection is not declared.',
                'Declare the connection in queue.connections and select it through queue.default or the explicit ConversionQueueOptions connection.');

            return;
        }
        $driver = $configuration['driver'] ?? null;
        if (! is_string($driver) || $driver === '' || in_array($driver, ['sync', 'null', 'deferred', 'background'], true)) {
            $report->add('queue_driver_not_durable', 'failed', 'The selected connection does not declare a durable worker queue driver.',
                'Change the selected queue.connections entry driver to a configured durable worker queue, such as database or redis. Start its worker separately.');
        }
        $retry = $configuration['retry_after'] ?? null;
        if (is_numeric($retry) && (int) $retry <= $options->timeout) {
            $report->add('queue_retry_window', 'failed', 'The queue reservation expires no later than the conversion job timeout.',
                'Set retry_after on the selected queue.connections entry above the conversion job timeout shown in profile.jobTimeout; verify provider visibility and actual worker settings separately.');
        }
    }

    private function encryption(MediaDiagnosticReport $report): void
    {
        $key = $this->config->get('app.key');
        $cipher = $this->config->get('app.cipher', 'AES-256-CBC');
        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }
        $valid = is_string($key) && is_string($cipher) && Encrypter::supported($key, $cipher);
        $report->add('recovery_encryption', $valid ? 'passed' : 'failed', 'Upload receipts and queued conversion journals require a valid Laravel encryption key.',
            'Configure the existing application key and supported cipher consistently on all processes. Do not rotate an existing key merely to clear this check; preserve keys needed to read existing journals.');
    }

    private function directory(MediaDiagnosticReport $report, string $code, string $key, string $default): void
    {
        try {
            $value = $this->config->get($key, $default);
            if (! is_string($value)) {
                throw new \InvalidArgumentException;
            }
            MediaAddress::path($value);
            $report->add($code, 'passed', 'The recovery directory declaration is a safe relative path.',
                'Keep recovery directories private and consistent across participating processes.');
        } catch (Throwable) {
            $report->add($code.'_invalid', 'failed', 'A recovery metadata directory is missing or unsafe.',
                'Set a nonempty relative directory without traversal, absolute paths, URLs or control characters.');
        }
    }

    private function cache(MediaDiagnosticReport $report): void
    {
        $name = $this->config->get('cache.default');
        $stores = $this->config->get('cache.stores', []);
        $store = is_string($name) && is_array($stores) ? ($stores[$name] ?? null) : null;
        $driver = is_array($store) ? ($store['driver'] ?? null) : null;
        if (! is_string($driver) || $driver === '' || in_array($driver, ['array', 'null'], true)) {
            $report->add('cache_locks_invalid', 'failed', 'Process-local or missing cache storage cannot coordinate Media OS locks between requests or workers.',
                'Select a persistent Laravel cache store supporting atomic locks, such as file on one host or a shared Redis/database store.');

            return;
        }
        if ($driver === 'file' && (! is_string($store['path'] ?? null) || $store['path'] === '')) {
            $report->add('cache_path_invalid', 'failed', 'The file cache has no configured storage path.',
                'Configure the file cache path and ensure all participating processes can access its lock files.');

            return;
        }
        $report->add('cache_locks_declared', in_array($driver, ['file', 'redis', 'database', 'memcached', 'dynamodb'], true) ? 'passed' : 'unverified',
            'The cache declaration was inspected without acquiring locks or connecting to the backend.',
            'Verify atomic locks, shared configuration and access in deployment checks; a custom cache driver must implement Laravel locking.');
    }

    private function queue(MediaDiagnosticReport $report, MediaDoctorOptions $options): void
    {
        try {
            $connection = (new ConversionQueueOptions($options->connection, $options->queue, timeout: $options->timeout))->connectionName();
        } catch (Throwable) {
            $report->add('conversion_queue_invalid', 'failed', 'The selected connection, durable queue driver or retry window does not satisfy conversion job requirements.',
                'Select an existing durable queue connection. Use retry_after greater than the configured conversion job timeout; sync, null, deferred and background are not worker queues.');
            $this->queueFailure($report, $options);

            return;
        }
        $connections = $this->config->get('queue.connections', []);
        $configuration = is_array($connections) ? ($connections[$connection] ?? []) : [];
        $driver = is_array($configuration) ? ($configuration['driver'] ?? null) : null;
        $report->add('conversion_queue_declared', 'passed', 'The selected queue satisfies the conversion options contract.',
            'Use this same connection and timeout in the actual ConversionQueueOptions passed by your application.');
        if (! in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            $report->add('queue_driver_unverified', 'unverified', 'A custom queue driver has not been connected or inspected.',
                'Verify the custom driver is registered, durable and implements the expected reservation and retry semantics.');
        }
        if ($driver === 'sqs') {
            $report->add('sqs_visibility_unverified', 'unverified', 'SQS retry visibility is configured at the provider, not proven by Laravel retry_after.',
                'Set the SQS visibility timeout above the conversion job timeout and verify the worker configuration in AWS.');
        } else {
            $retry = is_array($configuration) ? ($configuration['retry_after'] ?? null) : null;
            $integer = filter_var($retry, FILTER_VALIDATE_INT);
            $status = $retry === null ? 'unverified' : ($integer !== false && $integer > $options->timeout ? 'passed' : 'failed');
            $report->add('queue_retry_window', $status, 'A conversion must finish or time out before the same job becomes available for retry.',
                'Set an explicit integer retry_after greater than the conversion job timeout; inspect custom drivers and their effective retry semantics separately.');
        }
        $report->add('worker_liveness_unverified', 'unverified', 'A configured queue is not evidence that a worker is running or consumes the conversion queue.',
            'Verify a worker consumes the selected connection and conversion queue (media-conversions by default), including failed jobs, worker timeout and supervisor/Horizon limits.');
        $report->add('worker_timeout_capability', function_exists('pcntl_alarm') ? 'passed' : 'unverified',
            'Laravel worker job timeout enforcement depends on the PHP process and its process-control support.',
            'Check timeout enforcement on the real worker, including PCNTL and supervisor/Horizon termination limits.');
    }

    private function schedules(MediaDiagnosticReport $report, MediaDoctorOptions $options): void
    {
        $tasks = ['staging' => ['staging.schedule_enabled', 'flex-fields:prune-media-staging'],
            'capture' => ['retention.schedule_enabled', 'flex-fields:prune-capture-media']];
        if ($options->queued) {
            $tasks['conversions'] = ['images.queue.prune_schedule_enabled', 'flex-fields:prune-media-conversions'];
        }
        $events = $this->schedule->events();
        foreach ($tasks as $name => [$key, $command]) {
            $enabled = $this->config->get('filament-flex-fields.media_capture.'.$key, true);
            if (! is_bool($enabled)) {
                $report->add($name.'_schedule_invalid', 'failed', 'A cleanup schedule flag is not a boolean.',
                    'Set the relevant media_capture schedule_enabled or prune_schedule_enabled option to true or false.');

                continue;
            }
            if (! $enabled) {
                $report->add($name.'_schedule_external', 'unverified', 'The built-in cleanup schedule is disabled.',
                    'Confirm your alternative cleanup workflow runs the corresponding prune command with suitable retention and overlap protection.');

                continue;
            }
            $matches = 0;
            $expected = ConsoleApplication::formatCommandString($command);
            foreach ($events as $event) {
                if (is_string($event->command) && ($event->command === $expected || str_starts_with($event->command, $expected.' '))) {
                    $matches++;
                }
            }
            $report->add($name.'_schedule_registered', $matches > 0 ? 'passed' : 'failed', 'An enabled cleanup task must be registered in Laravel scheduling.',
                'Check package provider registration and the effective configuration, then inspect php artisan schedule:list. Registration does not prove scheduler execution.');
            if ($matches > 1) {
                $report->add($name.'_schedule_duplicate', 'failed', 'The same built-in cleanup command is registered more than once.',
                    'Remove the duplicate schedule or disable the built-in task when the application manages its own schedule.');
            }
        }
        if ($this->config->get('filament-flex-fields.media_capture.retention.schedule_enabled', true) === true) {
            $frequency = $this->config->get('filament-flex-fields.media_capture.retention.schedule', 'daily');
            $report->add('capture_schedule_frequency', in_array($frequency, ['hourly', 'daily', 'weekly'], true) ? 'passed' : 'failed',
                'The built-in capture retention schedule supports hourly, daily or weekly execution.',
                'Select hourly, daily or weekly; other values currently fall back to daily and do not define a custom cron expression.');
        }
        $report->add('scheduler_liveness_unverified', 'unverified', 'Registered tasks do not prove that the system scheduler executes them.',
            'Verify your scheduler service or cron invokes Laravel schedule:run every minute and check its recent execution records.');
    }
}
