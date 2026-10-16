<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

// Retain the asset alias without registering a catalog entry or playground route.
return new ComponentDefinition(id: 'admin-columns', kind: ComponentKind::Column, uses: ['progress-column']);
