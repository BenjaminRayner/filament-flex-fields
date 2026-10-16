<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\FlexIcon;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class SegmentTabsPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'segment_tabs__general_name' => 'Alex Rivera',
            'segment_tabs__general_email' => 'alex@example.com',
            'segment_tabs__advanced_api_key' => 'sk_live_xxx',
            'segment_tabs__advanced_webhook' => 'https://example.com/hooks',
            'segment_tabs__ghost_name' => 'Morgan Lee',
            'segment_tabs__ghost_bio' => 'Product designer',
        ];
    }

    public function section(): Section
    {
        return Section::make('Segment Tabs')
            ->description('Segment control header with tab panels and nested form schemas.')
            ->extraAttributes(['class' => 'fff-playground-section'])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 2])
                    ->extraAttributes(['class' => 'fff-playground-variants'])
                    ->schema([
                        SegmentTabs::make('Account')
                            ->tabs([
                                SegmentTab::make('General')
                                    ->icon(FlexIcon::Person)
                                    ->schema([
                                        FlexTextInput::make('segment_tabs__general_name')
                                            ->label('Name'),
                                        FlexTextInput::make('segment_tabs__general_email')
                                            ->label('Email'),
                                    ]),
                                SegmentTab::make('Advanced')
                                    ->icon(FlexIcon::Gear)
                                    ->schema([
                                        FlexTextInput::make('segment_tabs__advanced_api_key')
                                            ->label('API key'),
                                        FlexTextInput::make('segment_tabs__advanced_webhook')
                                            ->label('Webhook URL'),
                                    ]),
                            ]),
                        SegmentTabs::make('Profile')
                            ->variant('ghost')
                            ->separators(false)
                            ->fullWidth()
                            ->tabs([
                                SegmentTab::make('Details')
                                    ->schema([
                                        FlexTextInput::make('segment_tabs__ghost_name')
                                            ->label('Display name'),
                                    ]),
                                SegmentTab::make('Bio')
                                    ->schema([
                                        FlexTextInput::make('segment_tabs__ghost_bio')
                                            ->label('Short bio'),
                                    ]),
                            ]),
                    ]),
                SegmentTabs::make('Overflow · nine tabs')->activeTab(5)->tabs(
                    array_map(static fn (string $label): SegmentTab => SegmentTab::make($label)
                        ->schema([FlexTextInput::make('overflow_'.strtolower($label))->label($label.' content')]),
                        ['Overview', 'Analytics', 'Reports', 'Performance', 'Engagement', 'Audience', 'Acquisition', 'Retention', 'Settings']),
                ),
            ]);
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            PlaygroundRelatedHubs::view('segment-tabs'),
            $this->section(),
        ];
    }
}
