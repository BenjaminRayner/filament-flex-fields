<?php

declare(strict_types=1);

require __DIR__.'/schema-render-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\BubbleChoiceField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ChoiceCheckboxCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\DualListboxField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexChecklist;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ImageChoiceCards;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\PriceRangeField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TodoListField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrackSlider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TrafficSplit;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

function rejectSelection(Schema $schema, string $path): void
{
    try {
        $schema->getState();
    } catch (ValidationException $error) {
        if (isset($error->errors()[$path])) {
            return;
        }
        throw $error;
    }
    throw new RuntimeException('Forged selection accepted: '.$path);
}

$selectionHost = new V590SchemaHost;
$selectionHost->setId('strict-selection-probe');
foreach ([false, true] as $multiple) {
    $region = SelectField::make('region')
        ->multiple($multiple)
        ->searchable()
        ->resetOnDependencyChange(fn (): bool => true)
        ->dependsOn('country', fn (?string $country): array => match ($country) {
            'pl' => ['mz' => 'Mazowieckie'],
            'us' => ['ca' => 'California'],
            default => [],
        });
    $schema = Schema::make($selectionHost)->statePath('data')->components([
        SelectField::make('country')->options(['pl' => 'Poland', 'us' => 'United States']),
        $region,
    ]);
    $schema->fill(['country' => 'pl', 'region' => $multiple ? ['mz'] : 'mz']);
    $config = $region->getHeadlessAlpineConfig();
    if ($config['dependencyStatePaths'] !== ['data.country'] || ! $config['resetOnDependencyChange']) {
        throw new RuntimeException('Dependency policy or relative path missing from bootstrap');
    }
    if ($config['searchPrompt'] !== $region->getSearchPrompt() || $config['searchPrompt'] === '') {
        throw new RuntimeException('Default searchable prompt missing from bootstrap');
    }
    $schema->getState();
    $selectionHost->data['country'] = 'us';
    rejectSelection($schema, 'data.region');
    $selectionHost->data['region'] = $multiple ? ['ca'] : 'ca';
    $schema->getState();
}
$localPrompt = SelectField::make('choices')->options(['a' => 'Alpha'])->searchable()->searchPrompt('Find a choice…');
$schema = Schema::make($selectionHost)->statePath('data')->components([$localPrompt]);
$schema->fill(['choices' => null]);
if ($localPrompt->getHeadlessAlpineConfig()['searchPrompt'] !== 'Find a choice…'
    || $localPrompt->shouldResetOnDependencyChange()) {
    throw new RuntimeException('Local search prompt lost or dependency reset unexpectedly enabled');
}
foreach ([['name'], ['name', 'code']] as $names) {
    $create = SelectField::make('choices')->createOptionForm(array_map(
        fn (string $name): FlexTextInput => FlexTextInput::make($name)->required(),
        $names,
    ));
    if (array_map(fn ($field): string => $field->getName(), $create->getCreateOptionActionForm(Schema::make($selectionHost))) !== $names) {
        throw new RuntimeException('Custom FLEX create schema lost its selected fields');
    }
}
echo "PASS: dependency reset policy/path, local translated/custom search prompt, one-field/multi-field FLEX create schemas and current-parent validation.\n";
foreach ([BubbleChoiceField::class, DualListboxField::class, ChoiceCheckboxCards::class, FlexChecklist::class, ImageChoiceCards::class, SelectField::class] as $class) {
    $field = $class::make('choices')->options([0 => 'Zero', 1 => 'One', 'good' => 'Good']);
    if ($field instanceof ImageChoiceCards || $field instanceof SelectField) {
        $field->multiple();
    }
    $schema = Schema::make($selectionHost)->statePath('data')->components([$field]);
    $schema->fill(['choices' => ['0', '1']]);
    if (count($schema->getState()['choices']) !== 2) {
        throw new RuntimeException('Numeric identities lost: '.$class);
    }
    foreach ([['unknown'], ['good', 'good'], ['name' => 'good'], [['nested' => 'good']], [true], [1.5], array_fill(0, 1000, 'good')] as $forged) {
        $selectionHost->data['choices'] = $forged;
        rejectSelection($schema, 'data.choices');
    }
}
foreach ([false, true] as $allowCreate) {
    $schema = Schema::make($selectionHost)->statePath('data')->components([
        SelectField::make('choices')->multiple()->options(['a' => 'Alpha', 0 => 'Zero'])->allowCreateOption($allowCreate),
    ]);
    $schema->fill(['choices' => ['a', '0']]);
    $schema->getState();
    foreach (['a', ['a', 'a'], ['0', 0], ['named' => 'a'], [true], [0.5], [['nested' => 'a']]] as $forged) {
        $selectionHost->data['choices'] = $forged;
        rejectSelection($schema, 'data.choices');
    }
    if ($allowCreate) {
        $selectionHost->data['choices'] = ['New label'];
        $schema->getState();
    }
}
foreach ([BubbleChoiceField::class, DualListboxField::class] as $class) {
    $field = $class::make('choices')->options(['a' => 'A']);
    $field->getNormalizedOptions();
    $field->options(['b' => 'B']);
    if (array_keys($field->getNormalizedOptions()) !== ['b']) {
        throw new RuntimeException('Options setter did not invalidate cache');
    }
    $schema = Schema::make($selectionHost)->statePath('data')->components([$field->maxItems(0)]);
    $schema->fill(['choices' => []]);
    $schema->getState();
    $selectionHost->data['choices'] = ['b'];
    rejectSelection($schema, 'data.choices');
}
$todo = Schema::make($selectionHost)->statePath('data')->components([TodoListField::make('tasks')->options(['a' => 'A'])]);
$todo->fill();
foreach ([[['id' => 'a']], [['id' => ['bad'], 'label' => 'A']], array_fill(0, 10001, ['id' => 'a', 'label' => 'A'])] as $forged) {
    $selectionHost->data['tasks'] = $forged;
    rejectSelection($todo, 'data.tasks');
}
foreach ([
    [TrackSlider::make('value')->min(5)->max(20), 10, [4, 21, 10.5, 'bad', ['bad']]],
    [TrackSlider::make('value')->integer(false)->min(0)->max(1), 0.35, [-0.1, 1.1, 'bad', ['bad']]],
    [TrafficSplit::make('value'), [34, 33, 33], [[40, 40, 40], [99, 0, 1], [50, 50], ['x', 33, 33], [[34], 33, 33], [true, 49, 50], ['a' => 34, 'b' => 33, 'c' => 33]]],
    [PriceRangeField::make('value')->min(0)->max(100), ['min' => 10, 'max' => 90], [['min' => 10, 'max' => 90, 'is_admin' => true], ['min' => -1, 'max' => 90], ['min' => 80, 'max' => 20]]],
] as [$field, $valid, $invalid]) {
    $schema = Schema::make($selectionHost)->statePath('data')->components([$field]);
    $schema->fill(['value' => $valid]);
    $schema->getState();
    foreach ($invalid as $forged) {
        $selectionHost->data['value'] = $forged;
        rejectSelection($schema, 'data.value');
    }
}
echo "PASS: selection identities/shape/budgets, numeric identities, cache invalidation, zero limits, malformed Todo, slider bounds, traffic totals and price container keys before save.\n";
