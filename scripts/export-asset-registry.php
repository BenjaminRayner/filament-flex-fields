<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use Bjanczak\FilamentFlexFields\Support\Assets\PortableAssetRegistry;

require_once __DIR__.'/../src/Support/Assets/ComponentAssetCatalog.php';
require_once __DIR__.'/../src/Support/Assets/StylesheetDependencyGraph.php';
require_once __DIR__.'/../src/Support/FlexFieldAssets.php';
require_once __DIR__.'/../src/Support/Assets/PortableAssetRegistry.php';

echo json_encode(PortableAssetRegistry::export(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
