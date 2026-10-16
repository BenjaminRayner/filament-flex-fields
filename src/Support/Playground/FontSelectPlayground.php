<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FontSelect;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

final class FontSelectPlayground
{
    /** @return array<string, mixed> */
    public function defaultState(): array
    {
        return ['font__catalog' => 'google:inter', 'font__empty' => null, 'font__shared' => 'google:inter',
            'font__brand' => 'google:lora', 'font__sample' => 'google:roboto', 'font__serif' => 'google:lora',
            'font__sm' => 'google:inter', 'font__lg' => 'google:roboto', 'font__locked' => 'google:inter'];
    }

    /** @return list<Component> */
    public function components(): array
    {
        return [
            Section::make('Google Fonts · real previews')
                ->description('Search the local metadata catalog. Font previews load only for visible menu rows. Selected values keep the normal input typography.')
                ->headerActions([Action::make('fontPreviewModal')->label('Open font picker modal')->schema([
                    FontSelect::make('modal_font')->previewSelectedFont(false)->default('google:inter')->required(),
                ])->action(static fn (): null => null)])
                ->columns(['default' => 1, 'lg' => 3])->schema([
                    FontSelect::make('font__catalog')->previewSelectedFont(false)->label('Full catalog')->required(),
                    FontSelect::make('font__empty')->previewSelectedFont(false)->label('No initial font'),
                    FontSelect::make('font__shared')->previewSelectedFont(false)->label('Shared selection')->helperText('The saved family stays Inter; the input keeps the normal application font.'),
                ]),
            Section::make('Preview and catalog policies')->columns(['default' => 1, 'lg' => 3])->schema([
                FontSelect::make('font__brand')->previewSelectedFont(false)->label('Approved brand fonts')->fonts(['google:inter', 'google:roboto', 'google:lora'])->categoryTabs(false),
                FontSelect::make('font__sample')->previewSelectedFont(false)->label('Custom specimen')->previewText('Beautiful type, thoughtfully chosen.')->previewWeight(600),
                FontSelect::make('font__serif')->previewSelectedFont(false)->label('Variable serif · Latin extended')->categories(['serif'])->subsets(['latin-ext'])->variableOnly()->sortFonts('alpha')->categoryTabs(['serif']),
            ]),
            Section::make('Sizes and read-only state')->columns(['default' => 1, 'lg' => 3])->schema([
                FontSelect::make('font__sm')->previewSelectedFont(false)->label('Small · secondary')->size('sm')->variant('secondary'),
                FontSelect::make('font__lg')->previewSelectedFont(false)->label('Large')->size('lg'),
                FontSelect::make('font__locked')->previewSelectedFont(false)->label('Read-only')->readOnly(),
            ]),
        ];
    }
}
