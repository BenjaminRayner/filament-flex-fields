<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Forms\Components;

use Bjanczak\FilamentFlexFields\Concerns\HasControlSize;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldFocusOutline;
use Bjanczak\FilamentFlexFields\Concerns\HasFieldRounding;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithFlexRichEditorEnhancements;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithFlexRichEditorFileAttachments;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Concerns\InteractsWithFlexRichEditorYoutube;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Actions\AttachFilesAction;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Actions\NativeActionTranslations;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Concerns\BuildsRichEditorToolbar;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\RichEditor\Concerns\ConfiguresRichEditorPresentation;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Concerns\HasMaxHeight;
use Filament\Forms\Components\Concerns\HasMinHeight;
use Filament\Forms\Components\RichEditor;
use InvalidArgumentException;

class FlexRichEditor extends RichEditor
{
    use BuildsRichEditorToolbar;
    use ConfiguresRichEditorPresentation;
    use HasControlSize;
    use HasFieldFocusOutline;
    use HasFieldRounding;
    use HasMaxHeight;
    use HasMinHeight;
    use InteractsWithFlexRichEditorEnhancements;
    use InteractsWithFlexRichEditorFileAttachments;
    use InteractsWithFlexRichEditorYoutube;

    protected string $view = 'filament-flex-fields::forms.components.flex-rich-editor-field';

    protected string|Closure $variant = 'secondary';

    protected bool|Closure $showWordCount = false;

    protected bool|Closure $showJsonBadge = false;

    /**
     * @var list<string>
     */
    protected const VARIANTS = ['primary', 'secondary', 'soft', 'flat'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDefaultRichEditorTranslations();

        $this->json();

        $this->toolbarButtons($this->getFlexDefaultToolbarButtons());
        $this->floatingToolbars($this->getFlexDefaultFloatingToolbars());

        $this->tools(static::getFlexExtraTools());

        $this->registerFlexRichEditorFileAttachmentHooks();
        $this->registerFlexRichEditorEnhancementHooks();
    }

    /** @return array<Action> */
    public function getDefaultActions(): array
    {
        return array_map(
            fn (Action $action): Action => $action->getName() === 'attachFiles'
                ? AttachFilesAction::make()
                : NativeActionTranslations::apply($action),
            parent::getDefaultActions(),
        );
    }

    /**
     * @return list<string>
     */
    public function getWrapperClasses(): array
    {
        $classes = [
            'fff-rich-editor-field',
            'fff-rich-editor-field--'.$this->getSize(),
            'fff-rounding-'.$this->getRounding(),
            'fff-rich-editor-field--'.$this->getVariant(),
        ];

        if ($this->shouldShowFocusOutline()) {
            $classes[] = 'has-focus-outline';
        }

        if ($this->shouldEnableRichEditorDistractionFree()) {
            $classes[] = 'fff-rich-editor-field--distraction-free';
        }

        return $classes;
    }

    public function variant(string|Closure $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function wordCount(bool|Closure $condition = true): static
    {
        $this->showWordCount = $condition;

        return $this;
    }

    public function jsonBadge(bool|Closure $condition = true): static
    {
        $this->showJsonBadge = $condition;

        return $this;
    }

    public function getVariant(): string
    {
        $variant = $this->evaluate($this->variant);

        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException("Invalid rich editor variant [{$variant}].");
        }

        return $variant;
    }

    public function shouldShowWordCount(): bool
    {
        return (bool) $this->evaluate($this->showWordCount);
    }

    public function shouldShowJsonBadge(): bool
    {
        return (bool) $this->evaluate($this->showJsonBadge);
    }

    /**
     * @return list<string>
     */
    public function getDistractionFreeHiddenToolsForJs(): array
    {
        return $this->getDistractionFreeHiddenTools();
    }

    public function getJsonBadgeLabel(): string
    {
        return Translations::get('filament-flex-fields::default.field_types.json');
    }

    public function getWordCountEmptyLabel(): string
    {
        return Translations::get('filament-flex-fields::default.rich_editor.word_count.empty');
    }

    public function getWordCountLineTemplate(): string
    {
        $line = Translations::get('filament-flex-fields::default.rich_editor.word_count.line');

        return str_replace(
            [':characters', ':words'],
            ['__CHARACTERS__', '__WORDS__'],
            $line,
        );
    }
}
