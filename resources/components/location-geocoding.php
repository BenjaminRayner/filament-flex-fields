<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Support\Components\ComponentDefinition;
use Bjanczak\FilamentFlexFields\Support\Components\ComponentKind;

return new ComponentDefinition(
    id: 'location-geocoding',
    kind: ComponentKind::Primitive,
    routes: require dirname(__DIR__).'/routes/location-geocoding.php',
);
