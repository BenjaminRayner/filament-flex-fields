<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration\ConfiguresTodoCollection;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration\ConfiguresTodoConstraints;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration\ConfiguresTodoEditing;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\TodoList\Configuration\ConfiguresTodoTasks;

trait ConfiguresTodoInteraction
{
    use ConfiguresTodoCollection;
    use ConfiguresTodoConstraints;
    use ConfiguresTodoEditing;
    use ConfiguresTodoTasks;
}
