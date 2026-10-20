<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaImageProbe;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeOptions;
use Bjanczak\FilamentFlexFields\Support\Media\Diagnostics\MediaProbeReport;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$report = new MediaProbeReport(new MediaProbeOptions(allowWrites: true, formats: ['webp']));
(new MediaImageProbe)->inspect(['webp'], $report);
$result = $report->toArray();
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
exit($result['passed'] ? 0 : 1);
