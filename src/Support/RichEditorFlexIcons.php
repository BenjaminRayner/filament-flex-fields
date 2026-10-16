<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support;

use Filament\Support\Facades\FilamentIcon;

/**
 * Hugeicons icons for Filament RichEditor toolbars and FlexRichEditor panels.
 */
final class RichEditorFlexIcons
{
    /**
     * @return array<string, string>
     */
    public static function packageAliases(): array
    {
        return [
            'filament-flex-fields::flex-rich-editor.toolbar.clear-formatting' => self::icon('clear_formatting'),
            'filament-flex-fields::flex-rich-editor.toolbar.clear-content' => self::icon('clear_content'),
            'filament-flex-fields::flex-rich-editor.toolbar.fullscreen' => self::icon('fullscreen'),
            'filament-flex-fields::flex-rich-editor.toolbar.youtube' => self::icon('youtube'),
        ];
    }

    public static function register(): void
    {
        FilamentIcon::register(self::packageAliases());
    }

    public static function icon(string $key): string
    {
        $configured = config("filament-flex-fields.ui.flex_rich_editor_{$key}_icon");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return match ($key) {
            'undo' => FlexIcon::ArrowRotateLeft,
            'redo' => FlexIcon::ArrowRotateRight,
            'bold' => FlexIcon::make('bold'),
            'italic' => FlexIcon::make('italic'),
            'underline' => FlexIcon::make('underline'),
            'strike' => FlexIcon::make('strikethrough'),
            'link' => FlexIcon::Link,
            'text_color' => FlexIcon::Palette,
            'h1' => FlexIcon::make('heading-1'),
            'h2' => FlexIcon::make('heading-2'),
            'h3' => FlexIcon::make('heading-3'),
            'blockquote' => FlexIcon::make('quote-close'),
            'code' => FlexIcon::Code,
            'code_block' => FlexIcon::make('curly-brackets'),
            'bullet_list' => FlexIcon::make('list-ul'),
            'ordered_list' => FlexIcon::make('list-ol'),
            'attach_files' => FlexIcon::make('picture'),
            'clear_formatting' => FlexIcon::make('eraser'),
            'clear_content' => FlexIcon::TrashBin,
            'fullscreen' => FlexIcon::make('chevrons-expand-up-right'),
            'youtube' => FlexIcon::make('circle-play'),
            default => FlexIcon::make($key),
        };
    }

    public static function iconForToolName(string $toolName): ?string
    {
        return match ($toolName) {
            'bold', 'italic', 'underline', 'strike', 'link', 'h1', 'h2', 'h3',
            'blockquote', 'code', 'undo', 'redo', 'textColor' => self::icon($toolName === 'textColor' ? 'text_color' : $toolName),
            'subscript' => 'hugeicons-text-subscript',
            'superscript' => 'hugeicons-text-superscript',
            'paragraph' => 'hugeicons-paragraph',
            'h4' => 'hugeicons-heading-04',
            'h5' => 'hugeicons-heading-05',
            'h6' => 'hugeicons-heading-06',
            'table' => 'hugeicons-table',
            'tableAddColumnBefore' => 'hugeicons-insert-column-left',
            'tableAddColumnAfter' => 'hugeicons-insert-column-right',
            'tableDeleteColumn' => 'hugeicons-column-delete',
            'tableAddRowBefore' => 'hugeicons-insert-row-up',
            'tableAddRowAfter' => 'hugeicons-insert-row-down',
            'tableDeleteRow' => 'hugeicons-row-delete',
            'tableMergeCells' => 'hugeicons-pathfinder-merge',
            'tableSplitCell' => 'hugeicons-table-columns-split',
            'tableToggleHeaderRow' => 'hugeicons-table-rows-split',
            'tableToggleHeaderCell' => 'hugeicons-cells',
            'tableDelete', 'gridDelete' => 'hugeicons-grid-off',
            'customBlocks' => 'hugeicons-puzzle',
            'mergeTags' => 'hugeicons-tags',
            'horizontalRule' => 'hugeicons-minus-sign',
            'highlight' => 'hugeicons-highlighter',
            'small' => 'hugeicons-reduce-paragraph',
            'lead' => 'hugeicons-expand-paragraph',
            'grid' => 'hugeicons-layout-grid',
            'details' => 'hugeicons-menu-collapse',
            'codeBlock' => self::icon('code_block'),
            'bulletList' => self::icon('bullet_list'),
            'orderedList' => self::icon('ordered_list'),
            'attachFiles' => self::icon('attach_files'),
            'alignStart' => FlexIcon::make('bars-ascending-align-left'),
            'alignCenter' => FlexIcon::make('bars-ascending-align-center'),
            'alignEnd' => FlexIcon::make('bars-ascending-align-right'),
            'alignJustify' => FlexIcon::make('text-align-justify'),
            'clearFormatting' => self::icon('clear_formatting'),
            'clearContent' => self::icon('clear_content'),
            'youtube' => self::icon('youtube'),
            default => null,
        };
    }
}
