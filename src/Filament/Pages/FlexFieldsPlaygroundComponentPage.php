<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages;

use Bjanczak\FilamentFlexFields\Filament\Pages\Concerns\InteractsWithCodeExamples;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Bjanczak\FilamentFlexFields\Support\Playground\Contracts\PlaygroundWithComponents;
use Bjanczak\FilamentFlexFields\Support\Playground\Contracts\PlaygroundWithPersistence;
use Bjanczak\FilamentFlexFields\Support\Playground\Contracts\PlaygroundWithValidationExamples;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexFieldsPlaygroundStore;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * @phpstan-import-type CatalogEntry from FlexCatalog
 * @phpstan-import-type PlaygroundDefinition from FlexFieldsPlaygroundRegistry
 */
class FlexFieldsPlaygroundComponentPage extends Page implements HasForms
{
    use InteractsWithCodeExamples;
    use InteractsWithForms;

    /**
     * @var class-string<Cluster>|null
     */
    protected static ?string $cluster = FlexFieldsPlaygroundCluster::class;

    protected string $view = 'filament-flex-fields::pages.flex-fields-playground-component';

    public ?array $data = [];

    #[Locked]
    public ?string $playgroundSlug = null;

    public static function canAccess(): bool
    {
        return FlexFieldsPlaygroundRegistry::isEnabled()
            && Auth::check()
            && filled(static::resolveDefinition());
    }

    public function getTitle(): string|Htmlable
    {
        return $this->resolvePlaygroundDefinition()['label'] ?? static::$title ?? 'FLEX';
    }

    public static function getNavigationLabel(): string
    {
        return static::resolveDefinition()['label'] ?? parent::getNavigationLabel();
    }

    public function mount(): void
    {
        $this->playgroundSlug ??= Filament::getCurrentPageConfigurationKey() ?? static::resolveSlugFromRequest();

        $definition = $this->resolvePlaygroundDefinition();

        if ($definition === null) {
            abort(404);
        }

        /** @var object{components(): list<Component>, defaultState?: (): array<string, mixed>} $playground */
        $playground = app($definition['playground']);

        if (! is_object($playground)) {
            throw new \LogicException('A playground class must resolve to an object.');
        }
        $state = method_exists($playground, 'defaultState') ? $playground->defaultState() : [];

        if ($playground instanceof PlaygroundWithPersistence) {
            $stored = app(FlexFieldsPlaygroundStore::class)->get($playground->playgroundSlug());

            if ($stored !== null) {
                $keys = $playground->persistedStateKeys();

                foreach ($stored as $key => $value) {
                    if ($keys === null || in_array($key, $keys, true) || $key === '_meta') {
                        $state[$key] = $value;
                    }
                }
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $definition = $this->resolvePlaygroundDefinition();

        if ($definition === null) {
            return $schema->components([])->statePath('data');
        }

        /** @var object{components(): list<Component>} $playground */
        $playground = app($definition['playground']);

        $components = $playground->components();
        $catalog = app(FlexCatalog::class);
        $groupedEntries = $catalog->groupedEntriesFor($definition['component']);
        if ($groupedEntries !== []) {
            $columnComponents = [];
            foreach ($groupedEntries as $entry) {
                $columnPlayground = app($entry['demoClass']);
                if (! $columnPlayground instanceof PlaygroundWithComponents) {
                    throw new \LogicException('Grouped column demos must implement PlaygroundWithComponents.');
                }
                array_push($columnComponents, ...$columnPlayground->components());
            }
            $components = [SegmentTabs::make()->key('flex-component-variants')
                ->persistTabInQueryString('variant')
                ->columnSpanFull()->tabs([
                    SegmentTab::make(Translations::get('filament-flex-fields::catalog.'.(($catalog->find($definition['component'])['kinds'][0] ?? 'field') === 'display' ? 'display_variant' : 'field_variant')))->key('field')->schema($components),
                    SegmentTab::make(Translations::get('filament-flex-fields::catalog.column_variant'))->key('table-column')->schema($columnComponents),
                ])];
        }

        return $schema->components([
            SegmentTabs::make()->key('flex-detail-tabs')
                ->extraAttributes(['class' => 'fff-flex-detail-tabs'])->columnSpanFull()->tabs([
                    SegmentTab::make(Translations::get('filament-flex-fields::catalog.demo'))->key('demo')->schema($components),
                    SegmentTab::make(Translations::get('filament-flex-fields::catalog.code_example'))->key('code-example')->schema([$this->codeExampleView()]),
                    SegmentTab::make(Translations::get('filament-flex-fields::catalog.information'))->key('information')->schema([
                        SchemaView::make('filament-flex-fields::catalog.information')
                            ->key('flex-component-information')->columnSpanFull()
                            ->viewData(['entry' => $this->catalogEntry(), 'inTab' => true]),
                    ]),
                ]),
        ])->statePath('data');
    }

    public function getPlaygroundSlug(): ?string
    {
        return $this->playgroundSlug ?? Filament::getCurrentPageConfigurationKey() ?? static::resolveSlugFromRequest();
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    /** @return CatalogEntry|null */
    public function catalogEntry(): ?array
    {
        $definition = $this->resolvePlaygroundDefinition();

        return $definition === null ? null : app(FlexCatalog::class)->find($definition['component']);
    }

    public function resetState(): void
    {
        $this->mount();

        Notification::make()
            ->title(Translations::get('filament-flex-fields::catalog.demo_reset'))
            ->success()
            ->send();
    }

    public function validateForm(): void
    {
        $hadErrors = $this->getErrorBag()->isNotEmpty();
        $this->form->validate();

        Notification::make()
            ->title(Translations::get('filament-flex-fields::default.flex_fields_playground.validation_passed'))
            ->success()
            ->send();

        // A successful notification needs no form morph unless old errors clear.
        if (! $hadErrors) {
            $this->skipRender();
        }
    }

    public function runPlaygroundValidationExample(string $name, string $mode = 'validate'): void
    {
        $definition = $this->resolvePlaygroundDefinition();
        if ($definition === null || ! in_array($mode, ['validate', 'invalid', 'valid'], true)) {
            abort(404);
        }
        $playground = app($definition['playground']);
        abort_unless($playground instanceof PlaygroundWithValidationExamples, 404);
        $example = $playground->validationExamples()[$name] ?? null;
        abort_if($example === null, 404);

        if ($mode !== 'validate') {
            $this->data[$name] = $mode === 'invalid' ? $example->invalid : $example->valid;
        }

        $this->validateOnly('data.'.$name, $this->form->getValidationRules(), $this->form->getValidationMessages(), $this->form->getValidationAttributes());

        Notification::make()->title('Value accepted')->body((string) $example->field->getLabel())->success()->send();
    }

    public function savePlaygroundState(): void
    {
        $definition = $this->resolvePlaygroundDefinition();

        if ($definition === null) {
            return;
        }

        /** @var object $playground */
        $playground = app($definition['playground']);

        if (! $playground instanceof PlaygroundWithPersistence) {
            return;
        }

        $this->form->validate();

        $state = $this->form->getState();
        $keys = $playground->persistedStateKeys();
        $toStore = [];

        foreach ($state as $key => $value) {
            if ($keys === null || in_array($key, $keys, true)) {
                $toStore[$key] = $value;
            }
        }

        if (method_exists($playground, 'sealPersistedState')) {
            $toStore = $playground->sealPersistedState($toStore);
        }

        app(FlexFieldsPlaygroundStore::class)->put($playground->playgroundSlug(), $toStore);

        $sealedAt = is_array($toStore['_meta'] ?? null)
            ? ($toStore['_meta']['sealed_at'] ?? null)
            : null;

        Notification::make()
            ->title(Translations::get('filament-flex-fields::default.flex_fields_playground.saved'))
            ->body($sealedAt ? Translations::get('filament-flex-fields::default.flex_fields_playground.saved_sealed_at', ['at' => $sealedAt]) : null)
            ->success()
            ->send();
    }

    public function verifyVerificationCodeDemo(string $code): void
    {
        Notification::make()
            ->title('Verification code received')
            ->body($code)
            ->success()
            ->send();
    }

    public function resendVerificationCodeDemo(): void
    {
        Notification::make()
            ->title('Verification code resent')
            ->body('A new code was sent to a****@gmail.com')
            ->success()
            ->send();
    }

    public function getHeader(): ?View
    {
        return view('filament-flex-fields::catalog.empty-header');
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        $definition = $this->resolvePlaygroundDefinition();

        if ($definition !== null) {
            $playground = app($definition['playground']);

            if ($playground instanceof PlaygroundWithPersistence) {
                $actions[] = Action::make('savePlaygroundState')
                    ->label(Translations::get('filament-flex-fields::default.flex_fields_playground.save'))
                    ->icon('hugeicons-cloud-upload')
                    ->color('primary')
                    ->action(fn () => $this->savePlaygroundState());
            }
        }

        return [
            ...$actions,
            Action::make('validateForm')
                ->label(Translations::get('filament-flex-fields::catalog.validate'))
                ->icon('hugeicons-shield-01')
                ->color('gray')
                ->action(fn () => $this->validateForm()),
            Action::make('dumpState')
                ->label(Translations::get('filament-flex-fields::catalog.dump_json'))
                ->icon('hugeicons-code')
                ->color('gray')
                ->modalHeading(Translations::get('filament-flex-fields::catalog.form_state'))
                ->modalContent(fn () => view('filament-flex-fields::catalog.state-preview', ['state' => $this->form->getRawState()]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(Translations::get('filament-flex-fields::catalog.close'))
                ->modalWidth(Width::ExtraLarge),
            Action::make('resetState')
                ->label(Translations::get('filament-flex-fields::catalog.reset_defaults'))
                ->icon('hugeicons-undo-02')
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->resetState()),
        ];
    }

    /** @return PlaygroundDefinition|null */
    protected function resolvePlaygroundDefinition(): ?array
    {
        $slug = $this->getPlaygroundSlug();

        return filled($slug) ? FlexFieldsPlaygroundRegistry::find($slug) : null;
    }

    /**
     * @return PlaygroundDefinition|null
     */
    protected static function resolveDefinition(): ?array
    {
        $slug = Filament::getCurrentPageConfigurationKey() ?? static::resolveSlugFromRequest();

        if (blank($slug)) {
            return null;
        }

        return FlexFieldsPlaygroundRegistry::find($slug);
    }

    protected static function resolveSlugFromRequest(): ?string
    {
        return FlexFieldAssets::resolvePlaygroundSlugFromRequest();
    }
}
