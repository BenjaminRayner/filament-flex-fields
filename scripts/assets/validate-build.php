<?php

declare(strict_types=1);

// Recovery/predeployment path: does not boot Laravel or touch application data.
require __DIR__.'/../../vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Support\Assets\Build\AssetBuildValidator;

$report = (new AssetBuildValidator)->validate();
echo json_encode($report->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL;
exit($report->errorCount === 0 ? 0 : 1);
