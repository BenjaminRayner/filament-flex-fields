<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets\Rendering;

enum RuntimeEmission: string
{
    case DeliveryPolicy = 'delivery-policy';
    case CoreStyles = 'core-styles';
    case Bootstrap = 'bootstrap';
    case Injector = 'injector';
}
