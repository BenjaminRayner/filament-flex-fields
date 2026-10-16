<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexRichEditor;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableFlexRichEditorForm;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableFlexRichEditorForm::$formSchema = [];
});

it('renders flex rich editor shell and alpine configuration', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body')
            ->wordCount()
            ->maxCharacters(5000)
            ->youtube(),
    ];

    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);

    expect($html)
        ->toContain('fff-rich-editor')
        ->toContain('flexRichEditorFormComponent({')
        ->toContain('fi-fo-rich-editor')
        ->toContain('fff-rich-editor__footer-stats')
        ->not->toContain('x-effect="editorUpdatedAt; scheduleRichEditorChromeSync()"')
        ->toContain('aria-orientation="horizontal"')
        ->toContain('role="textbox"')
        ->toContain('aria-live="polite"');
});

it('omits chrome sync effect for a minimal editor without footer metrics', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body'),
    ];

    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);

    expect($html)
        ->toContain('flexRichEditorFormComponent({')
        ->not->toContain('x-effect="editorUpdatedAt; scheduleRichEditorChromeSync()"');
});

it('enables chrome sync when autosave is configured', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body')->autosave(30),
    ];

    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);

    expect($html)
        ->toContain('fff-rich-editor__footer-autosave')
        ->not->toContain('x-effect="editorUpdatedAt; scheduleRichEditorChromeSync()"');
});

it('renders optional image overlay chrome when attachments are enabled', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body')
            ->fileAttachments(true)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory('rich-editor'),
    ];

    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);

    expect($html)
        ->toContain('fff-rich-editor__image-overlay')
        ->toContain('editSelectedImage()');
});

it('renders configured height and merge-tag panel using FLEX chrome', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body')->minHeight('320px')->maxHeight('560px')->mergeTags(['customer_name' => 'Customer name']),
    ];
    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);
    expect($html)->toContain('--fff-rich-editor-config-min-h: 320px', '--fff-rich-editor-config-max-h: 560px', 'fff-rich-editor__panels', 'Customer name', 'insertMergeTag')
        ->not->toContain('fi-fo-rich-editor-panels');
});

it('renders grouped searchable custom blocks in the FLEX panel', function (): void {
    TestableFlexRichEditorForm::$formSchema = [
        FlexRichEditor::make('body')
            ->customBlocks(['Content' => [CompletionCalloutBlock::class]])
            ->searchableCustomBlocks()
            ->customBlocksGrid()
            ->stickyToolbar(),
    ];

    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);

    expect($html)->toContain('fff-rich-editor__panel-items--grid', 'fff-rich-editor--sticky-toolbar', 'Search blocks', 'Content', 'Completion callout', 'No blocks match your search.')
        ->not->toContain('fi-fo-rich-editor-panels', 'custom_blocks.no_search_results');
});

class CompletionCalloutBlock extends RichContentCustomBlock
{
    public static function getId(): string
    {
        return 'completion_callout';
    }

    public static function getLabel(): string
    {
        return 'Completion callout';
    }
}

it('labels its contenteditable through aria instead of an invalid label for target', function (): void {
    TestableFlexRichEditorForm::$formSchema = [FlexRichEditor::make('body')->label('Article')];
    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);
    expect($html)->toContain('id="form.body-label"', 'aria-labelledby="form.body-label"')
        ->not->toContain('for="form.body"');
});

it('uses Hugeicons for every built in tool and dropdown chevron with uniform chrome strokes', function (): void {
    TestableFlexRichEditorForm::$formSchema = [FlexRichEditor::make('body')
        ->toolbarButtons(fn (FlexRichEditor $component): array => $component->getFlexFullToolbarButtons())];
    $html = Livewire::test(TestableFlexRichEditorForm::class)->html(false);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $icons = $xpath->query('//div[contains(@class,"fff-rich-editor__toolbar")]//svg');
    expect($icons->length)->toBeGreaterThan(15);
    $field = Livewire::test(TestableFlexRichEditorForm::class)->instance()->getSchema('form')->getComponent('body');
    foreach ($field->getTools() as $name => $tool) {
        expect($tool->getIcon())->toStartWith('hugeicons-', $name);
    }
    $groups = file_get_contents(__DIR__.'/../../src/Filament/Forms/Components/RichEditor/FlexToolbarButtonGroup.php');
    expect($groups)->toContain("'hugeicons-arrow-down-01'")->not->toContain('<svg');
    $css = file_get_contents(__DIR__.'/../../resources/css/components/rich-editor-field.css');
    expect($css)->toContain('svg [stroke-width]', 'stroke-width: 2;');
});
