<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Actions;

use Bjanczak\FilamentFlexFields\Filament\Actions\Action;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRichEditor;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Livewire\Component;

class AttachFilesAction
{
    public static function make(): Action
    {
        return Action::make('attachFiles')
            ->label(fn () => Translations::get('filament-forms::components.rich_editor.actions.attach_files.label'))
            ->modalHeading(fn () => Translations::get('filament-forms::components.rich_editor.actions.attach_files.modal.heading'))
            ->disabled(fn (FlexRichEditor $component): bool => $component->isDisabled() || ! $component->hasFileAttachments())
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'alt' => $arguments['alt'] ?? null,
            ])
            ->schema(fn (array $arguments, FlexRichEditor $component): array => [
                FlexFileUpload::make('file')
                    ->mediaStaging(false)
                    ->imageEditor()
                    ->fileViewer()
                    ->label(fn () => filled($arguments['src'] ?? null)
                        ? Translations::get('filament-forms::components.rich_editor.actions.attach_files.modal.form.file.label.existing')
                        : Translations::get('filament-forms::components.rich_editor.actions.attach_files.modal.form.file.label.new'))
                    ->acceptedFileTypes($component->getFileAttachmentsAcceptedFileTypes() ?? [])
                    ->maxSize($component->getFileAttachmentsMaxSize())
                    ->storeFiles(false)
                    ->required(blank($arguments['src'] ?? null))
                    ->hiddenLabel(blank($arguments['src'] ?? null)),
                FlexTextInput::make('alt')
                    ->label(fn () => filled($arguments['src'] ?? null)
                        ? Translations::get('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.existing')
                        : Translations::get('filament-forms::components.rich_editor.actions.attach_files.modal.form.alt.label.new'))
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, FlexRichEditor $component, Component $livewire): void {
                if ($data['file'] ?? null) {
                    $id = (string) Str::orderedUuid();

                    data_set($livewire, "componentFileAttachments.{$component->getStatePath()}.{$id}", $data['file']);
                    $src = $component->getUploadedFileAttachmentTemporaryUrl($data['file']);
                }

                if (filled($arguments['src'] ?? null)) {
                    // Fixes an issue where the editor selection is sent as text instead of a node,
                    // which causes the image update to fail when though the image is selected.
                    if (($arguments['editorSelection']['type'] ?? 'text') !== 'node') {
                        $arguments['editorSelection']['type'] = 'node';
                        $arguments['editorSelection']['anchor'] = max(0, ($arguments['editorSelection']['anchor'] ?? 1) - 1);

                        unset($arguments['editorSelection']['head']);
                    }

                    $id ??= $arguments['id'] ?? null;
                    $src ??= $arguments['src'];

                    $component->runCommands(
                        [
                            EditorCommand::make('updateAttributes', arguments: [
                                'image',
                                [
                                    'alt' => $data['alt'] ?? null,
                                    'id' => $id,
                                    'src' => $src,
                                ],
                            ]),
                        ],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                if (blank($id ?? null)) {
                    return;
                }

                if (blank($src ?? null)) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('insertContent', arguments: [[
                            'type' => 'image',
                            'attrs' => [
                                'alt' => $data['alt'] ?? null,
                                'id' => $id,
                                'src' => $src,
                            ],
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
