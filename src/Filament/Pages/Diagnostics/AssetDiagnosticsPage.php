<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages\Diagnostics;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CountryField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextareaField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SegmentControl;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;
use Bjanczak\FilamentFlexFields\Filament\Pages\FlexFieldsPlaygroundCluster;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/** @property-read Schema $form */
abstract class AssetDiagnosticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = FlexFieldsPlaygroundCluster::class;

    protected string $view = 'filament-flex-fields::diagnostics.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app()->environment(['local', 'testing']) && Auth::check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string|Htmlable
    {
        return Translations::get('filament-flex-fields::diagnostics.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    abstract public function scenario(): string;

    public function mount(): void
    {
        $this->form->fill();
    }

    /** @return list<array{url: string, label: string, active: bool}> */
    public function diagnosticsPages(): array
    {
        $pages = [];
        foreach ([AssetFormsPage::class, AssetTablesPage::class, AssetModalsPage::class] as $page) {
            $pages[] = [
                'url' => $page::getUrl(),
                'label' => Translations::get('filament-flex-fields::diagnostics.'.$page::SCENARIO),
                'active' => $page === static::class,
            ];
        }

        return $pages;
    }

    protected function compositeLabel(): string
    {
        return Translations::get('filament-flex-fields::diagnostics.composite');
    }

    protected function sharedSelect(): SelectField
    {
        return SelectField::make('page_select')->label(fn () => Translations::get('filament-flex-fields::diagnostics.shared_select'))
            ->options(['one' => 'First option', 'two' => 'Second option'])->default('one');
    }

    /** Actual Filament schema actions: each child preserves its parent's mounted form. */
    protected function nestedModal(int $depth = 1): Action
    {
        if ($depth < 1 || $depth > 5) {
            throw new \InvalidArgumentException('Diagnostic modal depth must be between 1 and 5.');
        }
        $fields = match ($depth) {
            1 => [CompositeField::make('contact')->label($this->compositeLabel())->schema([
                FlexTextInput::make('name')->label('Name')->default('FLEX')->emojiPicker()->clearable()->characterCounter(),
                FlexTextareaField::make('emoji_note')->label('Emoji note')->emojiPicker(), $this->sharedSelect(),
            ]), SegmentControl::make('segment_choice')->label('Segment choice')->options(['one' => 'One', 'two' => 'Two'])->default('one')],
            2 => [SegmentTabs::make('Nested segment tabs')->tabs([
                SegmentTab::make('Schedule')->key('schedule')->schema([ScheduleField::make('schedule')->label('Schedule')->timezone('UTC')]),
                SegmentTab::make('Details')->key('details')->schema([FlexTextInput::make('detail')->label('Details')->default('Modal tab')]),
            ])],
            3 => [FlexColorPickerField::make('color')->label('Color')->default('#6366F1')->showSwatches()->alpha(),
                IconPickerField::make('icon')->label(fn () => Translations::get('filament-flex-fields::diagnostics.icon_picker'))->sets('hugeicons')->list()->perPage(32),
                TranslatableFields::make('Modal locales')->locales(['en' => 'English', 'pl' => 'Polski'])->schema([
                    FlexTextInput::make('localized_title')->label('Localized title'),
                ]),
            ],
            4 => [SocialLinksField::make('social')->label('Social links'), CountryField::make('country')->label('Country')->default('PL')],
            5 => [FlexDatePicker::make('date')->label('Calendar'), FlexTextInput::make('note')->label('Note')->default('Level 5')],
        };
        if ($depth < 5) {
            $fields[] = Actions::make([$this->nestedModal($depth + 1)]);
        }

        return Action::make('level'.$depth)
            ->label(fn () => Translations::get('filament-flex-fields::diagnostics.open_level', ['depth' => $depth]))
            ->modalHeading(fn () => Translations::get('filament-flex-fields::diagnostics.level', ['depth' => $depth]))
            ->modalDescription(fn () => Translations::get('filament-flex-fields::diagnostics.modal_hint'))
            ->schema($fields)->modalWidth(Width::ThreeExtraLarge)
            ->modalFooter(fn () => view('filament-flex-fields::diagnostics.modal-inspector'))
            ->modalSubmitAction(false)->modalCancelActionLabel(fn () => Translations::get('filament-flex-fields::diagnostics.close'));
    }
}
