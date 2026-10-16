<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Components;

enum ComponentKind: string
{
    case Field = 'field';
    case Display = 'display';
    case Column = 'column';
    case Entry = 'entry';
    case Action = 'action';
    case Primitive = 'primitive';
}
