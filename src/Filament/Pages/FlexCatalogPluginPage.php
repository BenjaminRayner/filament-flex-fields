<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages;

use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/** @phpstan-import-type CatalogEntry from FlexCatalog */
final class FlexCatalogPluginPage extends Page implements HasForms
{
    use Concerns\InteractsWithCodeExamples;
    use InteractsWithForms;

    protected static ?string $cluster = FlexFieldsPlaygroundCluster::class;

    protected static ?string $slug = 'extensions/{entry}';

    protected string $view = 'filament-flex-fields::pages.flex-catalog-plugin';

    #[Locked]
    public string $entry = '';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return FlexFieldsPlaygroundRegistry::isEnabled() && Auth::check();
    }

    public function mount(string $entry): void
    {
        $this->entry = $entry;
        abort_unless($this->catalogEntry() !== null, 404);
    }

    /** @return CatalogEntry|null */
    public function catalogEntry(): ?array
    {
        return app(FlexCatalog::class)->find($this->entry);
    }

    public function form(Schema $schema): Schema
    {
        $entry = $this->catalogEntry();

        if ($entry === null) {
            return $schema->components([]);
        }

        $tabs = [];

        if ($entry['type'] === 'component') {
            $tabs[] = SegmentTab::make(fn () => Translations::get('filament-flex-fields::catalog.code_example'))
                ->key('code-example')->schema([$this->codeExampleView()]);
        }

        $tabs[] = SegmentTab::make(fn () => Translations::get('filament-flex-fields::catalog.information'))->key('information')->schema([
            SchemaView::make('filament-flex-fields::catalog.information')
                ->key('flex-extension-information')->columnSpanFull()
                ->viewData(['entry' => $entry, 'inTab' => true]),
        ]);

        return $schema->components([
            SegmentTabs::make()->key('flex-extension-tabs')
                ->extraAttributes(['class' => 'fff-flex-detail-tabs'])->tabs($tabs),
        ]);
    }

    public function getTitle(): string
    {
        return $this->catalogEntry()['label'] ?? 'FLEX';
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }
}
