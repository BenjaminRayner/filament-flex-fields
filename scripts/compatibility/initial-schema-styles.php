<?php

declare(strict_types=1);

require __DIR__.'/shared-overlay-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\CompositeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexColorPickerField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TagsField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaLayout;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaVisibility;
use Bjanczak\FilamentFlexFields\Support\FlexFieldStylesheetQueue;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

$fields = [];
$field = static function (string $name) use (&$fields): FlexTextInput {
    return $fields[$name] = FlexTextInput::make($name);
};
$color = FlexColorPickerField::make('dormant_color')->allowedFormats(['hex', 'rgb'])->showFormatSelector();
$nativeTabs = Tabs::make('Native')->persistTabInQueryString('probe-tab')->tabs([
    Tab::make('First')->schema([$field('first')]),
    Tab::make('Second')->schema([$field('second'), $color]),
]);
$schema = Schema::make($overlayHost)->key('initial-probe')->statePath('data')->components([
    $field('plain'), $field('hidden')->hidden(), $field('client_condition')->visibleJs('showField'),
    $field('client_attribute')->extraAttributes(['x-show' => 'showField']),
    $nativeTabs,
    Tabs::make('Local persisted')->persistTab()->tabs([Tab::make('Saved')->schema([$field('persisted')])]),
    Section::make('Closed')->collapsed()->schema([$field('collapsed')]),
    Section::make('Open')->schema([$field('expanded')]),
    Wizard::make([
        Step::make('One')->schema([$field('step_one')]),
        Step::make('Two')->schema([$field('step_two')]),
    ]),
    SegmentTabs::make('Segment')->tabs([
        SegmentTab::make('One')->schema([$field('segment_one')]),
        SegmentTab::make('Two')->schema([$field('segment_two')]),
    ]),
]);
FlexFieldStylesheetQueue::reset();
$schema->getFlatComponents(withHidden: true);
foreach ($fields as $instance) {
    $instance->toHtml();
}
$color->toHtml();
foreach (['plain', 'first', 'expanded', 'step_one', 'segment_one'] as $name) {
    locationCheck(InitialSchemaVisibility::allows($fields[$name]), 'Initial visible scope was deferred: '.$name);
}
foreach (['hidden', 'client_condition', 'client_attribute', 'second', 'persisted', 'collapsed', 'step_two', 'segment_two'] as $name) {
    locationCheck(! InitialSchemaVisibility::allows($fields[$name]), 'Dormant/unknown scope was promoted: '.$name);
}
$critical = FlexFieldStylesheetQueue::criticalStylesheets();
locationCheck(count(array_keys($critical, 'flex-text-input', true)) === 1, 'Shared initial CSS was duplicated');
locationCheck(! in_array('flex-color-picker', $critical, true), 'Inactive color CSS entered the head');
locationCheck(! in_array('select-field', $critical, true), 'Inactive color capability entered the head');
$secondTab = $nativeTabs->getChildSchema()->getComponents()[1];
request()->query->set('probe-tab', $secondTab->getId());
locationCheck(! InitialSchemaVisibility::allows($fields['first']) && InitialSchemaVisibility::allows($fields['second']), 'Query-selected tab visibility is wrong');
request()->query->set('probe-tab', $secondTab->getKey(isAbsolute: false));
locationCheck(! InitialSchemaVisibility::allows($fields['first']) && InitialSchemaVisibility::allows($fields['second']), 'Browser relative tab key was mistaken for a DOM ID');
FlexFieldStylesheetQueue::reset();
$color->toHtml();
locationCheck(in_array('flex-color-picker', FlexFieldStylesheetQueue::criticalStylesheets(), true)
    && in_array('select-field', FlexFieldStylesheetQueue::criticalStylesheets(), true), 'Anonymous Color Picker lost its visible schema/capability context');
request()->query->remove('probe-tab');
request()->headers->set('X-Livewire', 'true');
FlexFieldStylesheetQueue::reset();
foreach ($fields as $instance) {
    $instance->toHtml();
}
locationCheck(FlexFieldStylesheetQueue::criticalStylesheets() === [], 'Livewire fragment promoted CSS into the head');
request()->headers->remove('X-Livewire');
$modalField = $field('modal');
$modal = Schema::make($overlayHost)->key('mountedActionSchema0')->statePath('mountedActions.0.data')->components([$modalField]);
$modal->toHtml();
locationCheck(! InitialSchemaVisibility::allows($modalField), 'Action-modal CSS was promoted eagerly');
FlexFieldStylesheetQueue::reset();
Blade::render('<x-filament-flex-fields::assets component="flex-text-input" :schema-component="$field" consumer="custom.initial" />', ['field' => $fields['plain']]);
locationCheck(FlexFieldStylesheetQueue::criticalStylesheets() === ['flex-text-input'], 'Custom-renderer initial schema API omitted CSS');

$tableHost = new class extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function prepare(FiltersLayout $layout, bool $deferred): void
    {
        $this->table = Table::make($this)->records(fn (): array => [])->filters([
            Filter::make('sample')->schema([FlexTextInput::make('value')]),
        ], layout: $layout)->deferFilters($deferred);
    }
};
$tableHost->setId('initial-table-filter-probe');
foreach (FiltersLayout::cases() as $layout) {
    foreach ([false, true] as $deferred) {
        $tableHost->prepare($layout, $deferred);
        $filterSchema = $tableHost->getTableFiltersForm();
        $filterField = $filterSchema->getFlatFields()['sample.value'];
        $visible = in_array($layout, [FiltersLayout::AboveContent, FiltersLayout::BelowContent, FiltersLayout::BeforeContent, FiltersLayout::AfterContent], true);
        FlexFieldStylesheetQueue::reset();
        $filterField->toHtml();
        locationCheck(InitialSchemaVisibility::allows($filterField) === $visible, 'Native table filter visibility is wrong: '.$layout->name);
        locationCheck(in_array('flex-text-input', FlexFieldStylesheetQueue::criticalStylesheets(), true) === $visible, 'Closed table filter entered the head: '.$layout->name);
    }
}

$composite = CompositeField::make('contact')->schema([
    SelectField::make('status')->options(['ready' => 'Ready', 'draft' => 'Draft'])->default('ready')->required(),
    FlexTextInput::make('name')->default('FLEX')->required(),
]);
$dormantBuilds = 0;
$preparedTabs = Tabs::make('Prepared')->tabs([
    Tab::make('Contact')->schema([$composite]),
    Tab::make('Dormant')->schema(function () use (&$dormantBuilds): array {
        $dormantBuilds++;

        return [FlexColorPickerField::make('later')];
    }),
]);
$nativeSchema = Schema::make($overlayHost)->statePath('data')->components([$preparedTabs]);
InitialSchemaLayout::prepare($nativeSchema);
locationCheck($dormantBuilds === 0, 'Initial layout materialized a dormant child schema');
$prepared = $preparedTabs->getChildSchema()->getComponents();
locationCheck(($preparedTabs->getExtraAttributes()['data-fff-initial-layout'] ?? null) === 'native-tabs', 'Native nav reservation is absent');
locationCheck(($prepared[0]->getExtraAttributes()['class'] ?? null) === 'fi-active' && ! isset($prepared[1]->getExtraAttributes()['class']), 'Initial native panel was not selected exclusively');
$nativeSchema->fill();
FlexFieldStylesheetQueue::reset();
$nativeHtml = $nativeSchema->toHtml();
locationCheck(str_contains($nativeHtml, 'fff-composite-field') && str_contains($nativeHtml, 'data.contact.status'), 'Composite renderer lost nested child state');
locationCheck(str_contains($nativeHtml, 'data-fff-asset-consumer="select-field"') && str_contains($nativeHtml, 'data-fff-asset-consumer="flex-text-input"'), 'Composite children lost independent ownership');
locationCheck($nativeSchema->getState()['contact'] === ['status' => 'ready', 'name' => 'FLEX'], 'Composite child defaults/validation/dehydration were not preserved');
$nativeSchema->fill(['contact' => ['status' => 'ready', 'name' => '']]);
$childValidationFailed = false;
try {
    $nativeSchema->getState();
} catch (ValidationException $exception) {
    $childValidationFailed = isset($exception->errors()['data.contact.name']);
}
locationCheck($childValidationFailed, 'Composite wrapper bypassed a required child validation rule');

$nativeSchema->fill(['contact' => ['status' => 'ready', 'name' => 'FLEX']]);
$overlayHost->data['contact']['is_admin'] = true;
try {
    $nativeSchema->getState();
    throw new RuntimeException('Unknown composite key survived validation');
} catch (ValidationException $exception) {
    locationCheck(isset($exception->errors()['data.contact']), 'Unknown key has no container error');
}
$nested = Schema::make($overlayHost)->statePath('data')->components([
    CompositeField::make('profile')->schema([
        FlexTextInput::make('address.street')->required()->maxLength(20),
        CompositeField::make('details')->schema([FlexTextInput::make('title')->required()]),
        TagsField::make('tags')->maxTags(3),
        FlexTextInput::make('ignored')->dehydrated(false),
    ]),
]);
$legitimate = ['profile' => ['address' => ['street' => 'Main'], 'details' => ['title' => 'Dr'], 'tags' => ['PHP'], 'ignored' => 'UI only']];
$nested->fill($legitimate);
locationCheck($nested->getState()['profile']['tags'] === ['PHP'], 'Atomic array child rejected');
locationCheck(! array_key_exists('ignored', $nested->getState()['profile']), 'Non-dehydrated child leaked into save');
foreach ([
    ['address', 'is_admin'],
    ['details', 'is_admin'],
] as [$branch, $key]) {
    $nested->fill($legitimate);
    $overlayHost->data['profile'][$branch][$key] = true;
    try {
        $nested->getState();
        throw new RuntimeException('Nested unknown composite key survived validation');
    } catch (ValidationException $exception) {
        locationCheck(isset($exception->errors()['data.profile.'.$branch]), 'Nested shape error missing');
    }
}

locationCheck(! in_array('flex-color-picker', FlexFieldStylesheetQueue::criticalStylesheets(), true), 'Composite preparation loaded an inactive child');
$nativeOnly = Tabs::make('Native only')->tabs([Tab::make('Native')->schema([TextInput::make('plain')])]);
$nativeOnlySchema = Schema::make($overlayHost)->components([$nativeOnly]);
$nativeOnlySchema->toHtml();
locationCheck(! isset($nativeOnly->getExtraAttributes()['data-fff-initial-layout']), 'A native-only layout was modified');

echo 'PASS: real schema SSR visibility, native/segment/query tabs, wizard, collapse, client-only state, all native table filter layouts, modal/update exclusion, optional capabilities, native initial layout and composite child state/validation/asset ownership.'.PHP_EOL;
