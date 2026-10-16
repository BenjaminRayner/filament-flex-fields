<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\UserSelect;
use Composer\InstalledVersions;
use Filament\Forms\Components\Select;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

$checked = 0;
$native = new ReflectionClass(Select::class);
$protectedContract = [
    'cachedSelectedRecord' => '?Illuminate\\Database\\Eloquent\\Model',
    'cachedRelationshipRecords' => '?Illuminate\\Database\\Eloquent\\Collection',
    'cachedRelationshipOptions' => '?array',
    'getSelectedRecordUsing' => '?Closure',
    'modifyRelationshipQueryUsing' => '?Closure',
    'shouldIgnoreRelationshipRecord' => 'bool',
    'getSearchResultsUsing' => '?Closure',
    'getOptionLabelUsing' => '?Closure',
    'getOptionLabelsUsing' => '?Closure',
    'transformOptionsForJsUsing' => '?Closure',
    'options' => 'Illuminate\\Contracts\\Support\\Arrayable|Closure|array|string|null',
];
foreach ($protectedContract as $name => $type) {
    if (! $native->hasProperty($name)) {
        throw new RuntimeException("Native adapter property removed: {$name}.");
    }
    $property = $native->getProperty($name);
    if ($property->isPrivate() || $property->isStatic() || (string) $property->getType() !== $type) {
        throw new RuntimeException("Native adapter property changed: {$name}. Review the framework boundary before upgrading.");
    }
}

foreach ([SelectField::class, UserSelect::class] as $class) {
    $field = new ReflectionClass($class);
    foreach ($native->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (! $field->hasMethod($method->name)) {
            throw new RuntimeException("Missing native method {$class}::{$method->name}.");
        }
        $actual = $field->getMethod($method->name);
        if (! $actual->isPublic() || count($actual->getParameters()) !== count($method->getParameters())) {
            throw new RuntimeException("Native method shape changed: {$class}::{$method->name}.");
        }
        foreach ([ExposedLivewireMethod::class, Renderless::class] as $attribute) {
            if ($method->getAttributes($attribute) && ! $actual->getAttributes($attribute)) {
                throw new RuntimeException("Native endpoint attribute lost: {$class}::{$method->name}.");
            }
        }
        $checked++;
    }
}

foreach ([['native', 'searchable'], ['searchable', 'native'], ['native', 'multiple'], ['multiple', 'native'], ['native', 'allowHtml'], ['allowHtml', 'native']] as $configuration) {
    $field = new SelectField('configuration');
    foreach ($configuration as $method) {
        $field->$method();
    }
    if (! $field->shouldUseHeadlessEngine()) {
        throw new RuntimeException('Native renderer decision depends on fluent ordering.');
    }
}

echo "PASS: {$checked} inherited native public API entries, 11 protected adapter properties, endpoint attributes and six renderer configuration orderings on Filament ".InstalledVersions::getPrettyVersion('filament/forms').'.'.PHP_EOL;
