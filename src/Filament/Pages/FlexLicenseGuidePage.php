<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Pages;

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards;
use Bjanczak\FilamentFlexFields\Support\Licensing\LicenseUseAssessment;
use Bjanczak\FilamentFlexFields\Support\Playground\PlaygroundAccess;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;
use LogicException;

/** @phpstan-import-type LicenseAssessment from LicenseUseAssessment */
final class FlexLicenseGuidePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = FlexFieldsPlaygroundCluster::class;

    protected static ?string $slug = 'license-guide';

    protected string $view = 'filament-flex-fields::pages.flex-license-guide';

    /** @var array{uses?: list<string>, conditions?: list<string>, ownership?: string, products?: string, client_use?: string|null, client_ownership?: string, client_products?: string} */
    public ?array $data = [];

    /** @var LicenseAssessment|null */
    #[Locked]
    public ?array $assessment = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return PlaygroundAccess::canAccess();
    }

    public function mount(): void
    {
        $this->configuredForm()->fill(['uses' => [], 'conditions' => [], 'ownership' => 'one', 'products' => 'one', 'client_use' => null, 'client_ownership' => 'one', 'client_products' => 'one']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(1)->components([
            $this->choices('uses', LicenseUseAssessment::USES)->required()->minSelections(1)->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('conditions', []);
                    $this->assessment = null;
                }),
            $this->radioChoices('client_use', LicenseUseAssessment::CLIENT_USES)->live()->required()
                ->visible(fn (Get $get): bool => in_array('bespoke_client', $get('uses') ?? [], true))
                ->afterStateUpdated(function (Get $get, Set $set): void {
                    $set('conditions', array_values(array_intersect($get('conditions') ?? [], app(LicenseUseAssessment::class)->conditionsFor($get('uses') ?? [], $get('client_use') ?? ''))));
                    $this->assessment = null;
                }),
            ChoiceCheckboxCards::make('conditions')->label($this->text('questions.conditions'))->layout('stack')->size('sm')->live()
                ->options(fn (Get $get): array => $this->options('conditions', app(LicenseUseAssessment::class)->conditionsFor($get('uses') ?? [], $get('client_use') ?? '')))
                ->visible(fn (Get $get): bool => app(LicenseUseAssessment::class)->conditionsFor($get('uses') ?? [], $get('client_use') ?? '') !== []),
            $this->radioChoices('ownership', ['one', 'group', 'other'])->default('one')->live()->required()
                ->visible(fn (Get $get): bool => in_array('hosted', $get('uses') ?? [], true)),
            $this->radioChoices('products', ['one', 'two', 'portfolio'])->default('one')->live()->required()
                ->visible(fn (Get $get): bool => in_array('hosted', $get('uses') ?? [], true) && $get('ownership') === 'one'),
            $this->radioChoices('client_ownership', ['one', 'group', 'other'], 'ownership')->default('one')->live()->required()
                ->visible(fn (Get $get): bool => $this->hasClientHostedUse($get)),
            $this->radioChoices('client_products', ['one', 'two', 'portfolio'], 'products')->default('one')->live()->required()
                ->visible(fn (Get $get): bool => $this->hasClientHostedUse($get) && $get('client_ownership') === 'one'),
        ]);
    }

    /** @param list<string> $keys */
    private function choices(string $name, array $keys): ChoiceCheckboxCards
    {
        return ChoiceCheckboxCards::make($name)->label($this->text('questions.'.$name))
            ->options($this->options($name, $keys))->layout('stack')->size('sm');
    }

    /** @param list<string> $keys */
    private function radioChoices(string $name, array $keys, ?string $optionGroup = null): ChoiceCards
    {
        return ChoiceCards::make($name)->label($this->text('questions.'.$name))
            ->options($this->options($optionGroup ?? $name, $keys))->layout('stack')->size('sm');
    }

    /** @param list<string> $keys
     * @return array<string, array{label: string, description: string}>
     */
    private function options(string $name, array $keys): array
    {
        $options = [];
        foreach ($keys as $key) {
            $options[$key] = [
                'label' => $this->text($name.'.'.$key.'.label'),
                'description' => $this->text($name.'.'.$key.'.description'),
            ];
        }

        return $options;
    }

    private function hasClientHostedUse(Get $get): bool
    {
        $uses = $get('uses') ?? [];

        return in_array('bespoke_client', $uses, true) && $get('client_use') === 'hosted';
    }

    public function updatedData(): void
    {
        $this->assessment = null;
    }

    public function assess(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->configuredForm()->getState();
        $this->assessment = app(LicenseUseAssessment::class)->assess(
            $state['uses'], $state['conditions'] ?? [], $state['ownership'] ?? 'one', $state['products'] ?? 'one', $state['client_use'] ?? '', $state['client_ownership'] ?? 'one', $state['client_products'] ?? 'one',
        );
    }

    public function getTitle(): string
    {
        return $this->text('title');
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

    private function configuredForm(): Schema
    {
        return $this->getSchema('form') ?? throw new LogicException('License guide form is not configured.');
    }

    private function text(string $key): string
    {
        $value = __('filament-flex-fields::licensing.'.$key);
        if (! is_string($value)) {
            throw new LogicException('Expected a license guide translation string: '.$key);
        }

        return $value;
    }
}
