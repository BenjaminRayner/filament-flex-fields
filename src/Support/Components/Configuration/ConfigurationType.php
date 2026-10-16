<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components\Configuration;

enum ConfigurationType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Number = 'number';
    case String = 'string';
    case List = 'list';
    case Map = 'map';
}
