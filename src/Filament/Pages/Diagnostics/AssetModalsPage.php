<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics;

use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;

final class AssetModalsPage extends AssetDiagnosticsPage
{
    public const SCENARIO = 'modals';

    protected static ?string $slug = 'asset-diagnostics-modals';

    public function scenario(): string
    {
        return self::SCENARIO;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([$this->sharedSelect(), Actions::make([$this->nestedModal()])]);
    }
}
