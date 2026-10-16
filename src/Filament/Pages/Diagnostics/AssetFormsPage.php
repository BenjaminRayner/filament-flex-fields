<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

final class AssetFormsPage extends AssetDiagnosticsPage
{
    public const SCENARIO = 'forms';

    protected static ?string $slug = 'asset-diagnostics-forms';

    public function scenario(): string
    {
        return self::SCENARIO;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            $this->sharedSelect(),
            Tabs::make('Asset scopes')->persistTabInQueryString()->tabs([
                Tab::make('Input')->schema([
                    FlexTextInput::make('name')->label('Name')->default('FLEX'),
                    CompositeField::make('contact')->label($this->compositeLabel())->schema([
                        SelectField::make('status')->label('Status')->options(['ready' => 'Ready', 'draft' => 'Draft'])->default('ready'),
                        FlexTextInput::make('name')->label('Contact name')->default('FLEX'),
                    ]),
                ]),
                Tab::make('Color')->schema([FlexColorPickerField::make('color')->label('Color')->default('#6366F1')->showSwatches()->alpha()]),
                Tab::make('Social links')->schema([SocialLinksField::make('social')->label('Social links')]),
                Tab::make('Schedule')->schema([ScheduleField::make('schedule')->label('Schedule')->timezone('UTC')]),
            ]),
            Section::make(fn () => __('filament-flex-fields::diagnostics.collapsible'))->collapsed()->collapsible()->schema([
                FlexTextInput::make('optional')->label('Optional input'),
            ]),
            Wizard::make([
                Step::make('First step')->schema([FlexTextInput::make('wizard_name')->label('Wizard name')->default('First step')]),
                Step::make('Second step')->schema([FlexColorPickerField::make('wizard_color')->label('Wizard color')->default('#84CC16')]),
            ])->skippable(),
        ]);
    }
}
