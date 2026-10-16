<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Playground;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Support\FileUpload\FileUploadMimePresets;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

class FlexFileUploadPlayground
{
    /**
     * @return array<string, mixed>
     */
    public function defaultState(): array
    {
        return [
            'file_upload__avatar' => null,
            'file_upload__avatar_small' => null,
            'file_upload__avatar_large' => null,
            'file_upload__documents' => null,
            'file_upload__images' => null,
            'file_upload__multi' => [],
            'file_upload__grid' => [],
            'file_upload__grid_small' => [],
            'file_upload__metadata' => null,
            'file_upload__metadata_meta' => null,
            'file_upload__metadata_editorial' => [],
            'file_upload__grid_editorial' => [],
            'file_upload__grid_small_editorial' => [],
            'file_upload__variant_primary' => null,
            'file_upload__variant_secondary' => null,
            'file_upload__variant_flat' => null,
            'file_upload__sources' => null,
        ];
    }

    /**
     * @return list<Component>
     */
    public function components(): array
    {
        return [
            Section::make('File upload')
                ->description('FlexFileUpload with security defaults, MIME presets, summaries, and optional metadata sidecars.')
                ->extraAttributes(['class' => 'fff-playground-section'])
                ->schema([
                    Grid::make(['default' => 1, 'sm' => 3])
                        ->schema([
                            FlexImageUpload::make('file_upload__avatar_small')
                                ->label('Avatar · small')
                                ->helperText('96 px · actions stay on the circle.')
                                ->withRecommendedDefaults()
                                ->avatar()
                                ->avatarSize('sm')
                                ->fileViewer()
                                ->imageEditor()
                                ->circleCropper()
                                ->disk('local')
                                ->directory('playground/uploads/avatars'),
                            FlexImageUpload::make('file_upload__avatar')
                                ->label('Avatar · medium')
                                ->helperText('128 px · manual upload, image editor and 1:1 crop.')
                                ->withRecommendedDefaults()
                                ->avatar()
                                ->avatarSize('md')
                                ->fileViewer()
                                ->instantUpload(false)
                                ->imageEditor()
                                ->circleCropper()
                                ->disk('local')
                                ->directory('playground/uploads/avatars')
                                ->optimizeImages()
                                ->maxImageWidth(512)
                                ->maxImageHeight(512),
                            FlexImageUpload::make('file_upload__avatar_large')
                                ->label('Avatar · large')
                                ->helperText('160 px · choose a custom pixel size through the API.')
                                ->withRecommendedDefaults()
                                ->avatar()
                                ->avatarSize('lg')
                                ->fileViewer()
                                ->imageEditor()
                                ->circleCropper()
                                ->disk('local')
                                ->directory('playground/uploads/avatars'),
                        ]),
                    Grid::make(['default' => 1, 'lg' => 2])
                        ->schema([
                            FlexFileUpload::make('file_upload__documents')
                                ->label('Documents')
                                ->documentsOnly()
                                ->helperText('Recommended defaults with documentsOnly(), replace confirmation, and upload summary.')
                                ->withRecommendedDefaults()
                                ->disk('local')
                                ->directory('playground/uploads/documents')
                                ->uploadSummary()
                                ->requireReplaceConfirmation()
                                ->remainingSlotsLabel()
                                ->multiple()
                                ->maxFiles(5)
                                ->showFileIcon()
                                ->compactList(),
                            FlexImageUpload::make('file_upload__images')
                                ->label('Images')
                                ->fileViewer()->fileViewerMetadata(false)
                                ->imageEditor()
                                ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1'])
                                ->imageEditorViewportWidth(1920)
                                ->imageEditorViewportHeight(1080)
                                ->helperText('Image preset with optimization hooks enabled.')
                                ->withRecommendedDefaults()
                                ->disk('local')
                                ->directory('playground/uploads/images')
                                ->imagePreviewHeight('150')
                                ->optimizeImages()
                                ->maxImageWidth(1600)
                                ->uploadSummary(),
                        ]),
                    FlexFileUpload::make('file_upload__multi')
                        ->label('Multiple files')
                        ->compactList()
                        ->helperText('Compact header · click an uploaded file to run a PHP action · adding disables at five files.')
                        ->compactHeader()
                        ->fileClickAction(fn (string $fileName) => Notification::make()->title('Selected: '.$fileName)->success()->send())
                        ->withRecommendedDefaults()
                        ->disk('local')
                        ->directory('playground/uploads/multi')
                        ->multiple()
                        ->reorderable()
                        ->pausable()
                        ->maxFiles(5)
                        ->maxTotalSizeKb(10240)
                        ->remainingSlotsLabel()
                        ->uploadSummary()
                        ->columnSpanFull(),
                    FlexFileUpload::make('file_upload__metadata')
                        ->label('With metadata sidecar')
                        ->compactList()
                        ->helperText('Alternative text in three languages; Polish is required. Copyright and up to 20 tags stay shared.')
                        ->withRecommendedDefaults()
                        ->disk('local')
                        ->directory('playground/uploads/metadata')
                        ->storeMetadataIn('file_upload__metadata_meta')
                        ->fileViewer()->fileViewerEditableMetadata()
                        ->fileMetadataEditor()->fileMetadataStatePath('file_upload__metadata_editorial')
                        ->fileMetadataSchema([
                            FlexTextInput::make('alt')->label('Alternative text')->maxLength(250),
                            FlexTextInput::make('copyright')->label('Copyright'),
                            TagsField::make('tags')->label('Tags')->maxTags(20)->showTagCount(),
                        ])
                        ->fileMetadataTranslations(['en' => 'English', 'pl' => 'Polski', 'de' => 'Deutsch'], fields: ['alt'])
                        ->fileMetadataDefaultLocale('pl')->fileMetadataRequiredInDefaultLocale(['alt'])->fileMetadataFallback()
                        ->columnSpanFull(),
                    FlexFileUpload::make('file_upload__grid')
                        ->label('Attachment grid · standard · actions menu')
                        ->gridSize('md')->gridActions('menu')
                        ->helperText('116 px attachment tiles with upload progress around the border. Images show previews; documents keep their filenames.')
                        ->withRecommendedDefaults()
                        ->uploadLayout('grid')
                        ->fileClickAction(fn (string $fileName) => Notification::make()->title('Selected: '.$fileName)->success()->send())
                        ->imageEditor()
                        ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1'])
                        ->imageEditorViewportWidth(1920)
                        ->imageEditorViewportHeight(1080)
                        ->acceptedFileTypes([...FileUploadMimePresets::images(), ...FileUploadMimePresets::documents()])
                        ->downloadable(false)
                        ->openable(false)
                        ->gridColumns(6)
                        ->fileViewer()->fileViewerEditableMetadata()
                        ->fileMetadataEditor()->fileMetadataStatePath('file_upload__grid_editorial')
                        ->fileMetadataTranslations(['en' => 'English', 'pl' => 'Polski'])
                        ->multiple()
                        ->reorderable()
                        ->pausable()
                        ->appendFiles()
                        ->maxFiles(8)
                        ->disk('local')
                        ->directory('playground/uploads/grid')
                        ->columnSpanFull(),
                    Grid::make(['default' => 1, 'lg' => 3])
                        ->schema([
                            FlexImageUpload::make('file_upload__grid_small')
                                ->label('Attachment grid · small · actions menu')
                                ->helperText('96 px tiles · 17% smaller · click an image for preview · menu contains available actions.')
                                ->withRecommendedDefaults()->multiple()->maxFiles(8)->imagesOnly()
                                ->disk('local')->directory('playground/uploads/grid-small')
                                ->uploadLayout('grid')->gridColumns(6)->gridSize('sm')->gridActions('menu')
                                ->compactHeader()->reorderable()->fileClickPreview()
                                ->imageEditor()->fileMetadataEditor()->fileMetadataStatePath('file_upload__grid_small_editorial')
                                ->fileMetadataSchema([FlexTextInput::make('alt')->label('Alternative text')->maxLength(250)])
                                ->columnSpanFull(),
                            FlexFileUpload::make('file_upload__variant_primary')
                                ->label('Primary')
                                ->variant('primary')
                                ->documentsOnly()
                                ->disk('local')
                                ->directory('playground/uploads/variants')
                                ->emptyStateHint('Drop a PDF here')
                                ->maxFiles(1)
                                ->compactList()
                                ->showFileIcon(),
                            FlexFileUpload::make('file_upload__variant_secondary')
                                ->label('Secondary')
                                ->variant('secondary')
                                ->size('sm')
                                ->documentsOnly()
                                ->disk('local')
                                ->directory('playground/uploads/variants')
                                ->maxFiles(1)
                                ->compactList()
                                ->showFileIcon(),
                            FlexFileUpload::make('file_upload__variant_flat')
                                ->label('Flat')
                                ->variant('flat')
                                ->size('lg')
                                ->documentsOnly()
                                ->disk('local')
                                ->directory('playground/uploads/variants')
                                ->maxFiles(1)
                                ->compactList()
                                ->showFileIcon(),
                        ]),
                    FlexImageUpload::make('file_upload__sources')
                        ->label('Upload sources')
                        ->helperText('Optional segment tabs for file, URL, and webcam image capture. Shares segment-control CSS with SegmentTabs.')
                        ->withRecommendedDefaults()
                        ->allowUrlUpload()
                        ->allowWebcamUpload()
                        ->disk('local')
                        ->directory('playground/uploads/sources')
                        ->maxFiles(1)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
