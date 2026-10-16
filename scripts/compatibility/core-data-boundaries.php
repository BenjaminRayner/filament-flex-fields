<?php

declare(strict_types=1);

// The current task prohibits tests/** changes. This extends the isolated compatibility harness.
try {
    require __DIR__.'/schema-render-contract.php';
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
}

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});

use Bjanczak\FilamentFlexFields\Concerns\HasFlexFields;
use Bjanczak\FilamentFlexFields\Data\FlexFieldDefinition;
use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\TimezoneField;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\Enterprise\Schema\SchemaVersionConflict;
use Bjanczak\FilamentFlexFields\Support\Enterprise\SchemaRegistry;
use Bjanczak\FilamentFlexFields\Support\FlexFieldFormBuilder;
use Bjanczak\FilamentFlexFields\Support\FlexFieldSchemaMigrator;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEncryption;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldGroupValidator;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaBlueprintPacks;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaImportExport;
use Bjanczak\FilamentFlexFields\Support\Schema\SchemaPayloadContract;
use Bjanczak\FilamentFlexFields\Support\Values\FieldStoragePath;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueCodec;
use Bjanczak\FilamentFlexFields\Support\Values\FieldValueWriter;
use Bjanczak\FilamentFlexFields\Support\Values\JsonDataBudget;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueContract;
use Bjanczak\FilamentFlexFields\Support\Values\Submission\FieldValueSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function dataProof(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}

function dataReject(Closure $operation, string $type = Throwable::class): Throwable
{
    try {
        $operation();
    } catch (Throwable $error) {
        dataProof($error instanceof $type, 'Expected '.$type.', received '.get_class($error).' at probe line '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line'].': '.$error->getMessage());

        return $error;
    }
    throw new RuntimeException('Invalid data was accepted at line '.debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['line']);
}

$checks = [];
foreach ([null, false, true, 0, 1.0, '', '001', 'Zażółć', [], ['alt' => null, 'tags' => ['0', '01']]] as $value) {
    dataProof(FieldValueCodec::decode(FieldValueCodec::encode($value)) === $value, 'Value type changed during exchange.');
    dataProof(FlexFieldEncryption::decrypt(FlexFieldEncryption::encrypt($value)) === $value, 'Encrypted round-trip changed the type.');
}
$checks[] = 'codec/encryption preserve null, empty, false, numeric strings, float and nested types';
foreach ([new stdClass, NAN, INF, "\xff", ["\xff" => 'bad'], array_fill(0, FieldValueCodec::MAX_NODES, null), str_repeat('a', FieldValueCodec::MAX_BYTES + 1)] as $value) {
    dataReject(static fn () => FieldValueCodec::assertCompatible($value));
}
$recursive = [];
$recursive['loop'] = &$recursive;
dataReject(static fn () => FieldValueCodec::assertCompatible($recursive));
unset($recursive);
foreach (['{"version":99,"type":"null","value":null}', '{"version":1,"type":"int","value":"1"}', '{"version":1,"type":"null","value":null,"method":"destroy"}', str_repeat(' ', FieldValueCodec::MAX_BYTES + 1)] as $payload) {
    dataReject(static fn () => FieldValueCodec::decode($payload));
}
dataReject(static fn () => (new JsonDataBudget(10, 10, 2))->assert("\0\0"));
$checks[] = 'bounded bytes including JSON escaping, nodes/depth/recursive data and strict value envelope';

$schema = ['target' => 'App\\Models\\Article', 'version' => 1, 'fields' => [
    ['slug' => 'alt', 'label' => 'Alternative text', 'type' => 'single_line_text', 'default_value' => 1.0, 'is_required' => false],
]];
$exchange = new SchemaImportExport;
dataProof($exchange->import($exchange->export($schema)) === $schema, 'Schema float/default round-trip changed.');
$mutations = [
    [...$schema, 'version' => 99], [...$schema, 'version' => '1'], [...$schema, 'class' => 'Some\\Class'],
    [...$schema, 'fields' => array_fill(0, 257, $schema['fields'][0])],
    [...$schema, 'sections' => array_fill(0, 129, ['id' => 'section'])],
    [...$schema, 'fields' => ['not-a-list' => $schema['fields'][0]]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'slug' => 'alt.*']]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'is_required' => 'false']]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'config' => 'not-a-map']]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'validation' => [new stdClass]]]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'class' => 'ForgedClass']]],
    [...$schema, 'fields' => [[...$schema['fields'][0], 'slug' => 'profile'], [...$schema['fields'][0], 'slug' => 'profile.alt']]],
    [...$schema, 'sections' => [['id' => 'section', 'type' => 'unknown-type']]],
    [...$schema, 'fields' => [$schema['fields'][0], $schema['fields'][0]]],
];
foreach ($mutations as $bad) {
    dataProof(! $exchange->dryRunValidate($bad)['ok'], 'Dry-run accepted a malformed schema.');
    dataReject(static fn () => $exchange->assertValid($bad));
    if (! is_object($bad['fields'][0]['validation'][0] ?? null)) {
        dataReject(static fn () => $exchange->import(json_encode(['version' => 1, 'schema' => $bad], JSON_THROW_ON_ERROR)), JsonException::class);
    }
}
foreach ([['version' => [], 'schema' => $schema], ['version' => 1, 'schema' => $schema, 'import' => '/evil.js']] as $bad) {
    dataReject(static fn () => $exchange->import(json_encode($bad, JSON_THROW_ON_ERROR)), JsonException::class);
}
dataReject(static fn () => $exchange->import(str_repeat(' ', SchemaPayloadContract::MAX_BYTES + 1)), JsonException::class);
foreach (SchemaBlueprintPacks::names() as $name) {
    $pack = SchemaBlueprintPacks::pack($name);
    dataProof($exchange->import($exchange->export($pack)) === $pack, 'Existing blueprint exchange rejected.');
}
dataReject(static fn () => FlexFieldDefinition::fromArray([...$schema['fields'][0], 'is_required' => false, 'required' => true]), InvalidArgumentException::class);
dataProof(FlexFieldDefinition::fromArray([...$schema['fields'][0], 'is_required' => false, 'required' => false])->isRequired === false,
    'Equivalent legacy flag changed explicit canonical false.');
dataProof(FlexFieldDefinition::fromArray(['slug' => 'required', 'label' => 'Required', 'type' => 'single_line_text', 'required' => true])->isRequired,
    'Existing blueprint required flag was lost.');
$checks[] = 'schema import/export/direct validation share shape, version, paths and bounds; existing blueprints remain valid';

$booleanChecks = 0;
foreach (FieldType::cases() as $type) {
    $defaults = $type->defaultConfig();
    $typedSchema = [...$schema, 'fields' => [[...$schema['fields'][0], 'type' => $type->value, 'config' => $defaults]]];
    $exchange->assertValid($typedSchema);
    $unknownSchema = [...$typedSchema, 'fields' => [[...$typedSchema['fields'][0], 'config' => [...$defaults, 'undeclared_option' => true]]]];
    dataReject(static fn () => $exchange->assertValid($unknownSchema), InvalidArgumentException::class);
    dataReject(static fn () => FlexFieldDefinition::fromArray($unknownSchema['fields'][0]), InvalidArgumentException::class);
    foreach ($defaults as $key => $default) {
        if (! is_bool($default) || $key === 'scoped_directory') {
            continue;
        }
        $forgedSchema = [...$typedSchema, 'fields' => [[...$typedSchema['fields'][0], 'config' => [...$defaults, $key => 'false']]]];
        dataReject(static fn () => $exchange->assertValid($forgedSchema), InvalidArgumentException::class);
        $booleanChecks++;
    }
}
dataProof($booleanChecks > 100, 'Boolean type matrix did not exercise the installed built-ins.');
$configurationMutations = [
    ['type' => 'timezone', 'config' => ['include_utc' => 'false']],
    ['type' => 'single_line_text', 'config' => ['encrypted' => 'false']],
    ['type' => 'single_line_text', 'config' => ['max_length' => -1]],
    ['type' => 'single_line_text', 'config' => ['max_length' => 1.5]],
    ['type' => 'single_line_text', 'config' => ['min_length' => 10, 'max_length' => 2]],
    ['type' => 'integer', 'config' => ['min' => 5, 'max' => 2]],
    ['type' => 'integer', 'config' => ['step' => 0]],
    ['type' => 'integer', 'config' => ['step' => '1e999']],
    ['type' => 'integer', 'config' => ['min' => []]],
    ['type' => 'single_line_text', 'config' => ['width' => 'unsupported']],
    ['type' => 'flex_text_input', 'config' => ['counter_mode' => 'unknown']],
    ['type' => 'flex_text_input', 'config' => ['emoji_picker_width' => 1]],
    ['type' => 'flex_text_input', 'config' => ['tools_order' => ['clear', 'clear']]],
    ['type' => 'flex_text_input', 'config' => ['enforce_counter_limit' => true]],
    ['type' => 'flex_text_input', 'config' => ['draft_lifetime' => '60']],
    ['type' => 'flex_text_input', 'config' => ['text_direction' => 'unknown']],
    ['type' => 'flex_text_input', 'config' => ['speech_dictation_messages' => ['error' => ['injected']]]],
    ['type' => 'flex_text_input', 'config' => ['password_requirements' => ['unknown' => true]]],
    ['type' => 'select', 'config' => ['options' => 'forged']],
    ['type' => 'select', 'config' => ['size' => 'giant']],
    ['type' => 'choice_cards', 'config' => ['options' => [['value' => ['forged'], 'label' => 'Label']]]],
    ['type' => 'choice_cards', 'config' => ['options' => [['value' => 'id', 'label' => ['forged']]]]],
    ['type' => 'choice_cards', 'config' => ['grid_columns' => -1]],
    ['type' => 'choice_cards', 'config' => ['layout' => 'forged']],
    ['type' => 'choice_checkbox_cards', 'config' => ['min_selections' => 5, 'max_selections' => 2]],
    ['type' => 'dual_listbox', 'config' => ['min_items' => 3, 'exact_items' => 1]],
    ['type' => 'matrix_choice', 'config' => ['mode' => 'forged']],
    ['type' => 'date', 'config' => ['hour_cycle' => 18]],
    ['type' => 'date', 'config' => ['minute_step' => 7]],
    ['type' => 'date', 'config' => ['time_zone' => 'Forged/Zone']],
    ['type' => 'date', 'config' => ['locale' => ['forged']]],
    ['type' => 'date', 'config' => ['disabled_weekdays' => [7]]],
    ['type' => 'date', 'config' => ['disabled_weekdays' => ['1']]],
    ['type' => 'date', 'config' => ['unavailable_dates' => ['2026-02-30']]],
    ['type' => 'date', 'config' => ['min_date' => '2026-02-30']],
    ['type' => 'date', 'config' => ['min_value' => '2026-04-01', 'max_value' => '2026-03-01']],
    ['type' => 'date_time', 'config' => ['min_value' => '2026-01-01 25:00:00']],
    ['type' => 'time', 'config' => ['min_value' => '25:00']],
    ['type' => 'time', 'config' => ['allowed_times' => ['09:60']]],
    ['type' => 'time', 'config' => ['allowed_times' => ['09:00'], 'default_time' => '08:00']],
    ['type' => 'time', 'config' => ['unavailable_dates' => ['2026-01-01']]],
    ['type' => 'date_range', 'config' => ['calendar_months' => 3]],
    ['type' => 'date_range', 'config' => ['range_presets' => [['label' => 'Bad', 'start' => '2026-04-01', 'end' => '2026-03-01']]]],
    ['type' => 'date_range', 'config' => ['range_presets' => [['label' => 'Bad', 'start' => '2026-02-30', 'end' => '2026-03-01']]]],
    ['type' => 'schedule', 'config' => ['days' => ['noday']]],
    ['type' => 'schedule', 'config' => ['max_slots' => 0]],
    ['type' => 'schedule', 'config' => ['min_slots' => 20, 'max_slots' => 10]],
    ['type' => 'timezone', 'config' => ['timezones' => ['Forged/Zone']]],
    ['type' => 'country', 'config' => ['countries' => ['POL']]],
    ['type' => 'file', 'config' => ['storage_driver' => 'forged']],
    ['type' => 'file', 'config' => ['visibility' => 'forged']],
    ['type' => 'file', 'config' => ['max_size_kb' => -1]],
    ['type' => 'file', 'config' => ['min_files' => 5, 'max_files' => 2]],
    ['type' => 'file', 'config' => ['accepted_types' => [['forged']]]],
    ['type' => 'file', 'config' => ['accepted_types' => ['javascript:alert(1)']]],
    ['type' => 'file', 'config' => ['scoped_directory' => ['forged']]],
    ['type' => 'file', 'config' => ['image_conversion_queue' => ['method' => 'delete']]],
    ['type' => 'file', 'config' => ['image_conversion_queue' => ['tries' => null]]],
    ['type' => 'signature', 'config' => ['pen_width' => -2]],
    ['type' => 'signature', 'config' => ['webp_quality' => 2]],
    ['type' => 'barcode', 'config' => ['scan_interval' => 0]],
    ['type' => 'barcode', 'config' => ['camera_facing' => 'forged']],
    ['type' => 'map_picker', 'config' => ['default_center' => [91, 0]]],
    ['type' => 'map_picker', 'config' => ['default_center' => [0, 181]]],
    ['type' => 'map_picker', 'config' => ['default_center' => ['lat' => 0, 'lng' => 0]]],
    ['type' => 'map_picker', 'config' => ['search_types' => ['forged']]],
    ['type' => 'flex_color_picker', 'config' => ['grid_rows' => 32, 'grid_columns' => 32]],
    ['type' => 'flex_color_picker', 'config' => ['grid_colors' => ['forged']]],
    ['type' => 'flex_slider', 'config' => ['min' => '-1e308', 'max' => '1e308']],
    ['type' => 'price_range', 'config' => ['histogram' => [null]]],
    ['type' => 'verification_code', 'config' => ['length' => 6, 'groups' => [2, 3]]],
    ['type' => 'select', 'config' => ['option_view' => ['forged']]],
    ['type' => 'select', 'config' => ['options' => [['value' => 'a', 'label' => 'First'], ['value' => 'a', 'label' => 'Second']]]],
    ['type' => 'user_select', 'config' => ['inline_search' => 'false']],
    ['type' => 'todo_list', 'config' => ['paginated' => -1]],
    ['type' => 'todo_list', 'config' => ['infinite_scroll' => 'true']],
    ['type' => 'todo_list', 'config' => ['paginated' => null]],
    ['type' => 'date_range', 'config' => ['calendar_months' => null]],
    ['type' => 'date', 'config' => ['disabled_dates' => null]],
    ['type' => 'time', 'config' => ['minute_step' => null]],
    ['type' => 'flex_textarea', 'config' => ['toolbar_selects' => [['state_path' => 'data.*', 'options' => []]]]],
    ['type' => 'flex_textarea', 'config' => ['toolbar_selects' => [['state_path' => 'data.choice', 'statePath' => 'data.other']]]],
    ['type' => 'flex_textarea', 'config' => ['submit_action' => ['name' => 'save', 'action' => 'delete']]],
    ['type' => 'matrix_choice', 'config' => ['disable_cell_when' => [['row' => 'r', 'column' => 'c', 'when_row' => ['forged'], 'when_columns' => ['c']]]]],
    ['type' => 'matrix_choice', 'config' => ['disabled_cells' => ['row' => [['forged']]]]],
    ['type' => 'audio', 'config' => ['waveform' => [true]]],
    ['type' => 'tags', 'config' => ['spatie_tag_search_limit' => 101]],
    ['type' => 'integer', 'config' => ['formula' => 'system(1)']],
    ['type' => 'integer', 'config' => ['formula' => '{a}', 'calculated' => '{b}']],
    ['type' => 'user_select', 'config' => ['option_model' => 'Unapproved\\Model']],
    ['type' => 'select', 'config' => ['option_view' => 'unapproved-option-view']],
    ['type' => 'verification_code', 'config' => ['auto_submit_method' => 'unapprovedMethod']],
    ['type' => 'todo_list', 'config' => ['remote_loader' => 'unapprovedMethod']],
];
foreach ($configurationMutations as $mutation) {
    $forgedSchema = [...$schema, 'fields' => [[...$schema['fields'][0], ...$mutation]]];
    dataReject(static fn () => $exchange->assertValid($forgedSchema), InvalidArgumentException::class);
    dataReject(static fn () => $exchange->import(json_encode(['version' => 1, 'schema' => $forgedSchema], JSON_THROW_ON_ERROR)), JsonException::class);
    dataReject(static fn () => (new FlexFieldFormBuilder)->makeComponent(new FlexFieldDefinition(
        'forged', 'Forged', FieldType::from($mutation['type']), config: $mutation['config'],
    )), InvalidArgumentException::class);
}
foreach ([
    ['type' => 'file', 'config' => ['scoped_directory' => 'uploads', 'accepted_types' => ['image/*', 'application/pdf'], 'image_conversion_queue' => ['tries' => 3]]],
    ['type' => 'color', 'config' => ['tooltips' => ['red' => 'Red']]],
    ['type' => 'bubble_choice', 'config' => ['gutter' => 1.5, 'gravitation' => 2.5]],
    ['type' => 'schedule', 'config' => ['days' => [], 'locked_days' => ['sun'], 'min_slots' => 0]],
    ['type' => 'date_range', 'config' => ['range_presets' => [], 'calendar_months' => 'auto']],
    ['type' => 'time', 'config' => ['allowed_times' => [], 'hour_cycle' => '12']],
    ['type' => 'month', 'config' => ['min_value' => '2026-02', 'max_value' => '2026-12']],
    ['type' => 'year', 'config' => ['min_value' => '2025', 'max_value' => '2026']],
    ['type' => 'date', 'config' => ['min_date' => '2024-02-29', 'locale' => 'pl_PL']],
    ['type' => 'todo_list', 'config' => ['paginated' => 25, 'infinite_scroll' => false, 'celebration' => false]],
    ['type' => 'flex_textarea', 'config' => ['toolbar_selects' => [['state_path' => 'data.choice', 'options' => ['a' => 'Alpha']]], 'submit_action' => ['name' => 'save', 'icon' => 'hugeicons-checkmark-circle-02']]],
    ['type' => 'user_select', 'config' => ['clearable' => false, 'inline_search' => true, 'max_visible_avatars' => 3]],
    ['type' => 'timezone', 'config' => ['include_utc' => false]],
] as $valid) {
    $validSchema = [...$schema, 'fields' => [[...$schema['fields'][0], ...$valid]]];
    dataProof($exchange->import($exchange->export($validSchema)) === $validSchema, 'Valid domain settings changed during round-trip.');
}
$checks[] = count($configurationMutations).' invalid configurations reject consistently at direct/import/builder boundaries; domain options preserve supported polymorphic values';
$timezone = (new FlexFieldFormBuilder)->makeComponent(FlexFieldDefinition::fromArray([
    'slug' => 'timezone', 'label' => 'Timezone', 'type' => 'timezone', 'config' => ['include_utc' => false],
]));
dataProof($timezone instanceof TimezoneField
    && ! $timezone->shouldIncludeUtc(), 'Declared timezone setting did not reach its registered renderer.');
foreach ([['active' => 'false'], ['sort' => '1'], ['fields' => [[...$schema['fields'][0], 'width' => 'unsupported']]],
    ['fields' => [[...$schema['fields'][0], 'sort' => []]]], ['fields' => [[...$schema['fields'][0], 'validation' => ['']]]],
    ['fields' => [[...$schema['fields'][0], 'label' => "Text\0Text"]]], ['sections' => [['id' => 'one', 'sort' => 1.5]]]] as $mutation) {
    dataReject(static fn () => $exchange->assertValid([...$schema, ...$mutation]), InvalidArgumentException::class);
}
$numericStringSchema = [...$schema, 'fields' => [[...$schema['fields'][0], 'type' => 'integer', 'config' => ['min' => '-2.5', 'max' => '10', 'step' => '0.5', 'max_length' => '20']]]];
dataProof($exchange->import($exchange->export($numericStringSchema)) === $numericStringSchema, 'Admissible numeric-string configuration changed during exchange.');
foreach ([null, ''] as $emptyWidth) {
    $exchange->assertValid([...$schema, 'fields' => [[...$schema['fields'][0], 'width' => $emptyWidth, 'config' => ['width' => $emptyWidth]]]]);
}
$checks[] = count(FieldType::cases()).' built-in default configs and '.$booleanChecks.' switch mutations; imported numeric bounds, ordering, widths and metadata are checked before casts';

foreach ([['is_required' => 'false'], ['is_active' => 'true'], ['sort' => '1'],
    ['label' => ['forged']], ['width' => 'unsupported'], ['is_required' => true, 'isRequired' => false]] as $attributes) {
    dataReject(static fn () => FlexFieldDefinition::fromArray([...$schema['fields'][0], ...$attributes]), InvalidArgumentException::class);
}
$typedDefinition = FlexFieldDefinition::fromArray([...$schema['fields'][0], 'type' => FieldType::SingleLineText]);
dataProof($typedDefinition->type === FieldType::SingleLineText, 'Typed PHP definition convenience was lost.');
$sharedLabel = 'Original';
$sharedRule = 'nullable';
$sharedCondition = 'original';
$immutableDefinition = new FlexFieldDefinition('immutable', 'Immutable', FieldType::Select,
    config: ['options' => ['a' => &$sharedLabel]], validation: [&$sharedRule],
    defaultValue: ['label' => &$sharedLabel], visibleWhen: ['field' => &$sharedCondition]);
$sharedLabel = 'Changed';
$sharedRule = 'required';
$sharedCondition = 'changed';
dataProof($immutableDefinition->config['options']['a'] === 'Original'
    && $immutableDefinition->validation === ['nullable'] && $immutableDefinition->defaultValue['label'] === 'Original'
    && $immutableDefinition->visibleWhen['field'] === 'original', 'Readonly definition retained mutable caller reference cells.');
$formulaCycle = [...$schema, 'fields' => [
    [...$schema['fields'][0], 'slug' => 'a', 'config' => ['formula' => '{b}']],
    [...$schema['fields'][0], 'slug' => 'b', 'config' => ['calculated' => '{a}']],
]];
dataReject(static fn () => $exchange->assertValid($formulaCycle), InvalidArgumentException::class);
$checks[] = 'closed per-type vocabulary, strict array-definition admission, detached reference cells and config-backed formula cycles';
$referencePolicy = $app['config']->get('filament-flex-fields.schema.configuration_references', []);
try {
    $app['config']->set('filament-flex-fields.schema.configuration_references.select.views', ['approved.option']);
    $approvedReference = [...$schema, 'fields' => [[...$schema['fields'][0], 'type' => 'select', 'config' => ['option_view' => 'approved.option']]]];
    dataProof($exchange->import($exchange->export($approvedReference)) === $approvedReference, 'Approved application reference failed exchange.');
    $app['config']->set('filament-flex-fields.schema.configuration_references.select.views', []);
    dataReject(static fn () => $exchange->assertValid($approvedReference), InvalidArgumentException::class);
} finally {
    $app['config']->set('filament-flex-fields.schema.configuration_references', $referencePolicy);
}
$checks[] = 'server-owned reference allowlists admit explicit references, reject unknown ones and observe policy removal without process caching';

$migrator = new FlexFieldSchemaMigrator;
foreach ([null, '1', -1, 99] as $version) {
    dataReject(static fn () => $migrator->migrate([...$schema, 'version' => $version]));
}
foreach (['corrupt', [null], ['not-a-list' => []]] as $fields) {
    dataReject(static fn () => $migrator->migrate(['target' => 'Article', 'field' => $fields]));
}
$legacy = $migrator->migrate(['target' => 'Article', 'field' => [
    ['name' => 'alt', 'label' => 'Alt', 'type' => 'single_line_text', 'default_value' => null, 'default' => 'must not overwrite'],
]]);
dataProof($legacy['fields'][0]['default_value'] === null, 'Explicit null overwritten by legacy alias.');
dataReject(static fn () => $migrator->migrate(['target' => 'Article', 'field' => [], 'fields' => []]));
$checks[] = 'legacy migration is bounded/lossless and future/null/string versions never silently migrate';

$groupValidator = new FlexFieldGroupValidator;
foreach ([[null], ['not-a-list' => []], [[...$schema['fields'][0], 'type' => ['forged']]],
    [[...$schema['fields'][0], 'config' => '{broken']], [[...$schema['fields'][0], 'config' => '"not-a-map"']],
    [[...$schema['fields'][0], 'type_settings' => 'forged']]] as $fields) {
    dataReject(static fn () => $groupValidator->normalizeFields($fields), InvalidArgumentException::class);
}
dataProof($groupValidator->normalizeFieldAttributes([...$schema['fields'][0], 'config' => '{"size":"sm"}'])['config']['size'] === 'sm', 'Valid form JSON settings were lost.');
$checks[] = 'group normalization rejects corrupt records/settings before casts or utilities can discard their data';

foreach (['', '*', 'a..b', 'a.*', 'a[b]', '__proto__', 'a.constructor', 'prototype.x', str_repeat('a.', 60).'a'] as $path) {
    dataReject(static fn () => FieldStoragePath::assert($path));
}
$definition = new FlexFieldDefinition('secret', 'Secret', FieldType::SingleLineText, isEncrypted: true);
$input = ['secret' => false, 'missing_package' => ['retained' => 'value']];
$stored = FieldValueWriter::replacement($input, [$definition]);
dataProof($input['secret'] === false && FlexFieldEncryption::decrypt($stored['secret']) === false && $stored['missing_package'] === $input['missing_package'], 'Storage changed input or erased unavailable field values.');
dataProof(FieldValueWriter::replacement($stored, [$definition]) === $stored, 'Storage double-encrypted a stored value.');
dataReject(static fn () => FieldValueWriter::assignment($stored, 'secret.part', 'forged', [$definition]));
$checks[] = 'literal storage paths, detached/idempotent encryption and retention of fields belonging to unavailable packages';

$submit = new FieldValueSubmission;
foreach ([null, 'forged', 42, new stdClass] as $root) {
    dataReject(static fn () => $submit->prepare($root, [], static fn (): bool => true), InvalidArgumentException::class);
}
$contracts = [new FieldValueContract('profile.alt', ['nullable', 'string', 'max:20']),
    new FieldValueContract('enabled', ['required', 'boolean']),
    new FieldValueContract('secret', ['nullable', 'string'], encrypted: true),
    new FieldValueContract('details', ['nullable', 'array:title']),
    new FieldValueContract('tags', ['nullable', 'array', 'max:2']),
];
$allow = static fn (FieldValueContract $field): bool => $field->path !== 'secret';
$plain = ['profile' => ['alt' => null], 'enabled' => false, 'details' => ['title' => 'Title'], 'tags' => ['one']];
dataProof($submit->prepare($plain, $contracts, $allow) === $plain, 'Submission changed null/false or introduced absent values.');
foreach ([['extra' => 'forged'], ['profile' => ['unknown' => 'forged']], ['profile' => 'not-a-container']] as $bad) {
    dataReject(static fn () => $submit->prepare([...$plain, ...$bad], $contracts, $allow), InvalidArgumentException::class);
}
dataReject(static fn () => $submit->prepare([...$plain, 'secret' => 'forged'], $contracts, $allow), AuthorizationException::class);
dataReject(static fn () => $submit->prepare($plain, $contracts, static fn () => 'yes'), InvalidArgumentException::class);
foreach ([['enabled' => 'yes'], ['profile' => ['alt' => str_repeat('a', 21)]], ['tags' => ['one', 'two', 'three']], ['details' => ['admin' => true]]] as $bad) {
    dataReject(static fn () => $submit->prepare([...$plain, ...$bad], $contracts, $allow), ValidationException::class);
}
dataReject(static fn () => $submit->prepare([], $contracts, $allow), ValidationException::class);
dataReject(static fn () => $submit->prepare([], [new FieldValueContract('x', ['nullable']), new FieldValueContract('x.y', ['nullable'])], $allow));
dataReject(static fn () => $submit->prepare([], [new FieldValueContract('x.y', ['nullable']), new FieldValueContract('x', ['nullable'])], $allow));
$envelope = FlexFieldEncryption::encrypt('pretend stored value');
$prepared = $submit->prepare(['enabled' => true, 'secret' => $envelope], $contracts, static fn (): bool => true);
dataProof(FlexFieldEncryption::decrypt($prepared['secret']) === $envelope, 'Client envelope was accepted as trusted storage.');
$checks[] = 'headless submission rejects unknown paths, denied fields, wrong types, hidden keys, max rules and forged ciphertext';

$nestedContracts = [new FieldValueContract('items', ['required', 'array', 'max:20'], nestedRules: [
    '*' => ['array:title,tags'], '*.title' => ['required', 'string', 'max:30'],
    '*.tags' => ['present', 'array', 'max:2'], '*.tags.*' => ['required', 'string', 'max:10'],
])];
$nestedPlain = ['items' => [['title' => 'First', 'tags' => ['one']], ['title' => 'Second', 'tags' => []]]];
dataProof($submit->prepare($nestedPlain, $nestedContracts, static fn (): bool => true) === $nestedPlain, 'Nested submission changed list values.');
foreach ([['title' => 'Valid', 'tags' => [123]], ['title' => 'Valid', 'tags' => ['one', 'two', 'three']],
    ['tags' => []], ['title' => 'Valid', 'tags' => [], 'admin' => true], ['title' => str_repeat('x', 31), 'tags' => []]] as $item) {
    dataReject(static fn () => $submit->prepare(['items' => [$item]], $nestedContracts, static fn (): bool => true), ValidationException::class);
}
foreach (['../title', 'a.*x', '*.__proto__', 'a..title'] as $path) {
    dataReject(static fn () => new FieldValueContract('items', ['array'], nestedRules: [$path => ['string']]), InvalidArgumentException::class);
}
dataReject(static fn () => $submit->prepare(['items' => ['literal.dotted' => ['title' => 'Valid', 'tags' => []]]], $nestedContracts, static fn (): bool => true), InvalidArgumentException::class);
$overlappingRules = [new FieldValueContract('items', ['array'], nestedRules: ['*' => ['string'], '0' => ['string']])];
dataReject(static fn () => $submit->prepare(['items' => ['value']], $overlappingRules, static fn (): bool => true), InvalidArgumentException::class);
$budgetContracts = [new FieldValueContract('items', ['array'], nestedRules: ['*' => ['string']])];
dataReject(static fn () => $submit->prepare(['items' => array_fill(0, 4096, 'value')], $budgetContracts, static fn (): bool => true), InvalidArgumentException::class);
$referenced = 'original';
$aliasPayload = ['details' => ['title' => &$referenced]];
$detached = $submit->prepare($aliasPayload, [new FieldValueContract('details', ['array:title'], nestedRules: ['title' => ['string']])], static fn (): bool => true);
$detached['details']['title'] = 'changed';
dataProof($referenced === 'original' && $aliasPayload['details']['title'] === 'original', 'Prepared data retained caller PHP references.');
$checks[] = 'nested map/list/wildcard rules validate every member, reject hidden keys and overlapping patterns, bound expansion and detach input references';
$distinctContracts = [new FieldValueContract('tags', ['array', 'list', 'max:20'], nestedRules: ['*' => ['string', 'distinct']])];
dataReject(static fn () => $submit->prepare(['tags' => ['same', 'same']], $distinctContracts, static fn (): bool => true), ValidationException::class);
dataProof($submit->prepare(['tags' => ['one', 'two']], $distinctContracts, static fn (): bool => true) === ['tags' => ['one', 'two']], 'Distinct wildcard validation rejected distinct members.');
$checks[] = 'bounded nested validation preserves native Laravel wildcard context, including distinct across sibling list values';

$app['config']->set('filament-flex-fields.schema.registry_persistence', false);
SchemaRegistry::clear();
dataReject(static fn () => SchemaRegistry::publish("\xff", $schema, expectedVersion: 0), InvalidArgumentException::class);
dataProof(SchemaRegistry::publish('tenant-a:data', $schema, expectedVersion: 0) === 1, 'Memory first publish failed.');
$conflict = dataReject(static fn () => SchemaRegistry::publish('tenant-a:data', $schema, expectedVersion: 0), SchemaVersionConflict::class);
dataProof($conflict->expected === 0 && $conflict->actual === 1 && count(SchemaRegistry::versions('tenant-a:data')) === 1, 'Memory conflict changed history.');
SchemaRegistry::purgeHistory('tenant-a:data');
dataProof(SchemaRegistry::currentVersion('tenant-a:data') === 1 && SchemaRegistry::publish('tenant-a:data', $schema, expectedVersion: 1) === 2, 'Memory purge reused version IDs.');
$checks[] = 'memory schema conflicts match persistence semantics and purged version IDs are never reused';

// Default is isolated SQLite. SQL mode requires an explicitly named empty disposable DB.
$driver = 'sqlite';
$connection = ['driver' => $driver, 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
if (in_array('--disposable-sql', $argv, true)) {
    $driver = getenv('FFF_DATA_DRIVER');
    $database = getenv('FFF_DATA_DATABASE');
    $port = getenv('FFF_DATA_PORT');
    dataProof(in_array($driver, ['mysql', 'pgsql'], true) && is_string($database)
        && preg_match('/^fff_core_probe_[a-z0-9_]{8,48}$/D', $database) === 1
        && is_string($port) && ctype_digit($port) && (int) $port > 0 && (int) $port <= 65535,
        'SQL probe requires a disposable fff_core_probe_* database and a valid loopback port.');
    dataProof(extension_loaded('pcntl') && function_exists('stream_socket_pair'), 'SQL contention requires pcntl and socket pairs.');
    $connection = ['driver' => $driver, 'host' => '127.0.0.1', 'port' => (int) $port,
        'database' => $database, 'username' => getenv('FFF_DATA_USERNAME'), 'password' => getenv('FFF_DATA_PASSWORD'),
        'charset' => $driver === 'mysql' ? 'utf8mb4' : 'utf8', 'prefix' => ''];
    if ($driver === 'mysql') {
        $connection += ['collation' => 'utf8mb4_unicode_ci', 'strict' => true];
    }
}
$app['config']->set('database.connections.fff_data_probe', $connection);
$app['config']->set('database.default', 'fff_data_probe');
$db = DB::connection('fff_data_probe');
if ($driver !== 'sqlite') {
    dataProof($db->getSchemaBuilder()->getTables() === [], 'SQL probe refuses a non-empty database.');
}
foreach (glob('database/migrations/*.php') as $file) {
    (require $file)->up();
}
$db = DB::connection('fff_data_probe');
$db->getSchemaBuilder()->create('submission_records', static function ($table): void {
    $table->increments('id');
    $table->string('tenant');
    $table->text('payload');
});
$app['config']->set('filament-flex-fields.schema.registry_persistence', true);
SchemaRegistry::clear();
dataProof(SchemaRegistry::publish('tenant-a:data', $schema, connectionName: 'fff_data_probe', expectedVersion: 0) === 1, 'DB first publish failed.');
dataProof(SchemaRegistry::publish('tenant-b:data', $schema, connectionName: 'fff_data_probe', expectedVersion: 0) === 1, 'Tenant head collided.');
$before = $db->table('flex_field_schema_versions')->count();
dataReject(static fn () => SchemaRegistry::publish('tenant-a:data', [...$schema, 'version' => 99], connectionName: 'fff_data_probe', expectedVersion: 1));
dataReject(static fn () => SchemaRegistry::publish('new:bad', $schema, connectionName: 'fff_data_probe', expectedVersion: 1), SchemaVersionConflict::class);
dataProof(! $db->table('flex_field_schema_heads')->where('schema_id', 'new:bad')->exists(), 'Rejected first publication left a partial head.');
dataReject(static function () use ($db, $schema): void {
    $db->transaction(static function () use ($db, $schema): void {
        $db->table('submission_records')->insert(['tenant' => 'tenant-a', 'payload' => 'must rollback']);
        SchemaRegistry::publish('tenant-a:data', $schema, connectionName: 'fff_data_probe', expectedVersion: 0);
    });
}, SchemaVersionConflict::class);
dataProof($db->table('submission_records')->count() === 0 && $db->table('flex_field_schema_versions')->count() === $before, 'Conflict partially persisted changes.');
$save = static function (array $values) use ($db, $submit, $contracts, $allow): void {
    $db->transaction(static function () use ($values, $db, $submit, $contracts, $allow): void {
        $prepared = $submit->prepare($values, $contracts, $allow);
        $db->table('submission_records')->insert(['tenant' => 'tenant-a', 'payload' => json_encode($prepared, JSON_THROW_ON_ERROR)]);
    });
};
dataReject(static fn () => $save([...$plain, 'secret' => 'forged']), AuthorizationException::class);
dataReject(static fn () => $save([...$plain, 'enabled' => 'invalid']), ValidationException::class);
dataProof($db->table('submission_records')->count() === 0, 'Rejected headless save wrote a row.');
$save($plain);
dataProof(json_decode($db->table('submission_records')->value('payload'), true, flags: JSON_THROW_ON_ERROR) === $plain, 'Headless accepted save changed the payload.');
$checks[] = 'actual isolated database create path rejects forged submissions without UI and preserves valid data';
$id = $db->table('submission_records')->value('id');
$edit = static function (string $tenant, bool $canEdit, array $values) use ($db, $submit, $contracts, $allow, $id): void {
    $db->transaction(static function () use ($tenant, $canEdit, $values, $db, $submit, $contracts, $allow, $id): void {
        $row = $db->table('submission_records')->where('id', $id)->where('tenant', $tenant)->lockForUpdate()->first();
        if (! $canEdit || $row === null) {
            throw new AuthorizationException('Record is not writable.');
        }
        $prepared = $submit->prepare($values, $contracts, $allow);
        $db->table('submission_records')->where('id', $id)->where('tenant', $tenant)->update(['payload' => json_encode($prepared, JSON_THROW_ON_ERROR)]);
    });
};
dataReject(static fn () => $edit('tenant-b', true, $plain), AuthorizationException::class);
dataReject(static fn () => $edit('tenant-a', false, $plain), AuthorizationException::class);
dataReject(static fn () => $edit('tenant-a', true, [...$plain, 'secret' => 'forged']), AuthorizationException::class);
dataReject(static fn () => $edit('tenant-a', true, [...$plain, 'tags' => ['one', 'two', 'three']]), ValidationException::class);
dataProof(json_decode($db->table('submission_records')->value('payload'), true, flags: JSON_THROW_ON_ERROR) === $plain, 'Rejected edit changed original values.');
$updated = [...$plain, 'profile' => ['alt' => 'Updated text']];
$edit('tenant-a', true, $updated);
dataProof(json_decode($db->table('submission_records')->value('payload'), true, flags: JSON_THROW_ON_ERROR) === $updated, 'Authorized edit lost values.');
$checks[] = 'actual headless edit composes record authorization, tenant-scoped query and field rules; rejected updates leave the row unchanged';
dataProof(SchemaRegistry::publish('tenant-a:data', $schema, connectionName: 'fff_data_probe', expectedVersion: 1) === 2, 'Valid guarded update failed.');
dataReject(static fn () => SchemaRegistry::rollback('tenant-a:data', 1, connectionName: 'fff_data_probe', expectedVersion: 1), SchemaVersionConflict::class);
dataProof(SchemaRegistry::rollback('tenant-a:data', 1, connectionName: 'fff_data_probe', expectedVersion: 2)['version'] === 3, 'Guarded rollback failed.');
SchemaRegistry::purgeHistory('tenant-a:data', 'fff_data_probe');
dataProof(SchemaRegistry::currentVersion('tenant-a:data', 'fff_data_probe') === 3 && SchemaRegistry::currentVersion('tenant-b:data', 'fff_data_probe') === 1, 'Purge forgot counters or crossed tenants.');
$checks[] = 'DB guarded publish/rollback preserve transaction atomicity, history, counters and tenant isolation';

// This uses the package's actual public model API and Eloquent save hooks.
final class DataBoundaryRecord extends Model
{
    use HasFlexFields;

    protected $table = 'submission_records';

    protected $guarded = [];

    public $timestamps = false;

    public static function flexFieldsColumn(): string
    {
        return 'payload';
    }

    protected function flexFieldStorageDefinitions(): array
    {
        return [new FlexFieldDefinition('secret', 'Secret', FieldType::SingleLineText, isEncrypted: true),
            new FlexFieldDefinition('profile.secret', 'Nested secret', FieldType::SingleLineText, isEncrypted: true)];
    }
}
$recordContracts = [new FieldValueContract('title', ['sometimes', 'string', 'max:20']),
    new FieldValueContract('secret', ['sometimes', 'nullable', 'string']),
    new FieldValueContract('items', ['sometimes', 'array', 'max:20'], nestedRules: $nestedContracts[0]->nestedRules)];
$recordPolicy = static fn (DataBoundaryRecord $record): bool => $record->tenant === 'tenant-a';
$fieldPolicy = static fn (FieldValueContract $field): bool => $field->path !== 'secret';
$app['config']->set('filament-flex-fields.audit.enabled', false);
$record = new DataBoundaryRecord(['tenant' => 'tenant-a', 'payload' => ['unavailable_package' => 'retained']]);
$newValues = ['title' => 'Created', ...$nestedPlain];
dataReject(static fn () => $record->applySubmittedFlexFieldValues($newValues, $recordContracts, static fn (): bool => false, $fieldPolicy), AuthorizationException::class);
dataReject(static fn () => $record->applySubmittedFlexFieldValues([], [], static fn () => 'yes', $fieldPolicy), InvalidArgumentException::class);
dataReject(static fn () => $record->applySubmittedFlexFieldValues(['secret' => 'forged'], $recordContracts, $recordPolicy, $fieldPolicy), AuthorizationException::class);
dataReject(static fn () => $record->applySubmittedFlexFieldValues(['secret' => ['part' => 'forged']], [new FieldValueContract('secret.part', ['string'])], $recordPolicy, static fn (): bool => true), InvalidArgumentException::class);
dataReject(static fn () => $record->applySubmittedFlexFieldValues(['profile' => ['secret' => $envelope]], [new FieldValueContract('profile', ['array:secret'], nestedRules: ['secret' => ['string']])], $recordPolicy, static fn (): bool => true), InvalidArgumentException::class);
dataProof($record->payload === ['unavailable_package' => 'retained'] && ! $record->exists, 'Rejected create mutated the model.');
$record->applySubmittedFlexFieldValues($newValues, $recordContracts, $recordPolicy, $fieldPolicy)->save();
$record->refresh();
dataProof($record->payload === ['unavailable_package' => 'retained', ...$newValues], 'Public model create lost unavailable package data.');
$before = $record->payload;
$storedBefore = $db->table('submission_records')->where('id', $record->id)->value('payload');
dataReject(static fn () => $record->applySubmittedFlexFieldValues(['items' => [['title' => 'Valid', 'tags' => [123]]]], $recordContracts, $recordPolicy, $fieldPolicy), ValidationException::class);
dataReject(static fn () => $record->applySubmittedFlexFieldValues(['unknown' => true], $recordContracts, $recordPolicy, $fieldPolicy), InvalidArgumentException::class);
dataProof($record->payload === $before && ! $record->isDirty() && $db->table('submission_records')->where('id', $record->id)->value('payload') === $storedBefore, 'Rejected edit changed the model or row.');
$record->applySubmittedFlexFieldValues(['secret' => $envelope], $recordContracts, $recordPolicy, static fn (): bool => true)->save();
$record->refresh();
$ciphertext = $record->payload['secret'];
dataProof($ciphertext !== $envelope && FlexFieldEncryption::decrypt($ciphertext) === $envelope, 'Model accepted a client encryption envelope as stored ciphertext.');
$record->applySubmittedFlexFieldValues(['title' => 'Edited'], $recordContracts, $recordPolicy, $fieldPolicy)->save();
$record->refresh();
dataProof($record->payload['secret'] === $ciphertext && $record->payload['items'] === $nestedPlain['items'] && $record->payload['unavailable_package'] === 'retained', 'Partial edit deleted or reencrypted denied/absent stored data.');
$record->applySubmittedFlexFieldValues(['secret' => null], $recordContracts, $recordPolicy, static fn (): bool => true)->save();
$record->refresh();
dataProof(array_key_exists('secret', $record->payload) && $record->payload['secret'] === null, 'Explicit nullable write was confused with an absent path.');
$before = $record->payload;
dataReject(static function () use ($db, $record, $recordContracts, $recordPolicy, $fieldPolicy): void {
    $db->transaction(static function () use ($record, $recordContracts, $recordPolicy, $fieldPolicy): void {
        $record->applySubmittedFlexFieldValues(['title' => 'Rolled back'], $recordContracts, $recordPolicy, $fieldPolicy)->save();
        throw new RuntimeException('Deliberate outer transaction failure.');
    });
});
$record->refresh();
dataProof($record->payload === $before, 'Public model API escaped the caller transaction.');
$foreign = new DataBoundaryRecord(['tenant' => 'tenant-b', 'payload' => []]);
dataReject(static fn () => $foreign->applySubmittedFlexFieldValues([], [], $recordPolicy, $fieldPolicy), AuthorizationException::class);
$checks[] = 'public Eloquent create/edit API requires record plus field policies, preserves absent/denied/unavailable values, upgrades schema encryption, rejects without mutation and participates in outer rollback';

foreach ($configurationMutations as $index => $mutation) {
    $group = new FlexFieldGroup(['name' => 'Rejected', 'slug' => 'config-probe-'.$index, 'target_type' => DataBoundaryRecord::class,
        'fields' => [[...$schema['fields'][0], ...$mutation]], 'sections' => [], 'tenant_id' => 'tenant-a', 'order' => 0]);
    dataReject(static fn () => $group->save(), ValidationException::class);
}
dataProof($db->table('flex_field_groups')->count() === 0, 'Rejected configuration left a persisted group.');
$group = new FlexFieldGroup(['name' => 'Accepted', 'slug' => 'accepted-config-probe', 'target_type' => DataBoundaryRecord::class,
    'fields' => $schema['fields'], 'sections' => [], 'tenant_id' => 'tenant-a', 'order' => 0]);
$group->save();
$persistedFields = $db->table('flex_field_groups')->where('id', $group->id)->value('fields');
foreach ($configurationMutations as $mutation) {
    $group->fields = [[...$schema['fields'][0], ...$mutation]];
    dataReject(static fn () => $group->save(), ValidationException::class);
    dataProof($db->table('flex_field_groups')->where('id', $group->id)->value('fields') === $persistedFields, 'Rejected group edit changed its stored schema.');
    $group->refresh();
}
$checks[] = 'actual FlexFieldGroup Eloquent create/edit uses the same configuration checks as import, refuses invalid creates and preserves the previous schema after rejected updates';

$validFlexConfig = ['counter_mode' => 'words', 'counter_limit' => 20, 'enforce_counter_limit' => true,
    'tools_order' => ['clear'], 'text_direction' => 'rtl', 'draft_lifetime' => 60];
$validFlexSchema = [...$schema, 'fields' => [[...$schema['fields'][0], 'type' => 'flex_text_input', 'config' => $validFlexConfig]]];
dataProof($exchange->import($exchange->export($validFlexSchema)) === $validFlexSchema, 'Admissible FLEX options changed during import.');
$group->fields = $validFlexSchema['fields'];
$group->save();
$group->refresh();
$storedConfig = $group->fields[0]['config'];
// JSON object key order is not stable in MySQL binary JSON; types and list order still are.
ksort($storedConfig);
ksort($validFlexConfig);
dataProof($storedConfig === $validFlexConfig, 'Admissible FLEX options changed during actual group edit.');
$checks[] = 'declared text option contract admits valid RTL, ordered tools and enforced counter configuration through import and actual database save';

if ($driver !== 'sqlite') {
    // Each writer has a separate PDO connection; a socket barrier starts contention together.
    $contend = static function (?int $expectedVersion, bool $rollback = false) use ($schema): array {
        DB::connection('fff_data_probe')->disconnect();
        DB::purge('fff_data_probe');
        $workers = [];
        $results = [];
        try {
            for ($index = 0; $index < 8; $index++) {
                $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                dataProof($pair !== false, 'Cannot create contention barrier.');
                $pid = pcntl_fork();
                dataProof($pid !== -1, 'Cannot fork contention writer.');
                if ($pid === 0) {
                    fclose($pair[0]);
                    foreach ($workers as $worker) {
                        fclose($worker['socket']);
                    }
                    stream_set_timeout($pair[1], 30);
                    try {
                        dataProof(fwrite($pair[1], "ready\n") === 6 && fgets($pair[1]) === "go\n", 'Contention barrier failed.');
                        $version = $rollback
                            ? SchemaRegistry::rollback('contended:data', 1, connectionName: 'fff_data_probe', expectedVersion: $expectedVersion)['version']
                            : SchemaRegistry::publish('contended:data', $schema, actor: 'writer-'.$index,
                                state: SchemaRegistry::STATE_LIVE, connectionName: 'fff_data_probe', expectedVersion: $expectedVersion);
                        $result = ['status' => 'published', 'version' => $version];
                    } catch (SchemaVersionConflict $error) {
                        $result = ['status' => 'conflict', 'expected' => $error->expected, 'actual' => $error->actual];
                    } catch (Throwable $error) {
                        $result = ['status' => 'error', 'type' => get_class($error),
                            'detail' => $error instanceof QueryException ? $error->errorInfo : null];
                    }
                    fwrite($pair[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
                    fclose($pair[1]);
                    DB::disconnect('fff_data_probe');
                    exit($result['status'] === 'error' ? 1 : 0);
                }
                fclose($pair[1]);
                stream_set_timeout($pair[0], 30);
                $workers[] = ['pid' => $pid, 'socket' => $pair[0]];
            }
            foreach ($workers as $worker) {
                dataProof(fgets($worker['socket']) === "ready\n", 'Writer failed before contention.');
            }
            foreach ($workers as $worker) {
                dataProof(fwrite($worker['socket'], "go\n") === 3, 'Cannot release contention writer.');
            }
            foreach ($workers as &$worker) {
                $line = fgets($worker['socket']);
                dataProof(is_string($line), 'Writer did not return a bounded result.');
                $result = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                dataProof(in_array($result['status'] ?? null, ['published', 'conflict'], true), 'Unexpected SQL writer failure: '.json_encode($result, JSON_THROW_ON_ERROR));
                $results[] = $result;
                $deadline = hrtime(true) + 5_000_000_000;
                do {
                    $waited = pcntl_waitpid($worker['pid'], $status, WNOHANG);
                    if ($waited !== 0) {
                        break;
                    }
                    usleep(1000);
                } while (hrtime(true) < $deadline);
                dataProof($waited === $worker['pid'] && pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                    'SQL writer failed after its result.');
                $worker['reaped'] = true;
            }
            unset($worker);
        } finally {
            unset($worker);
            foreach ($workers as $worker) {
                if (is_resource($worker['socket'])) {
                    fclose($worker['socket']);
                }
                if (! ($worker['reaped'] ?? false) && pcntl_waitpid($worker['pid'], $status, WNOHANG) === 0) {
                    // Bounded cleanup even if a writer is stuck in a failed database operation.
                    posix_kill($worker['pid'], SIGTERM);
                    pcntl_waitpid($worker['pid'], $status);
                }
            }
            DB::purge('fff_data_probe');
        }

        return $results;
    };
    foreach ([0, 1] as $expected) {
        $results = $contend($expected);
        $published = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 'published'));
        $conflicts = array_values(array_filter($results, static fn (array $result): bool => $result['status'] === 'conflict'));
        dataProof(count($published) === 1 && $published[0]['version'] === $expected + 1 && count($conflicts) === 7,
            'Optimistic contention did not yield exactly one winner.');
        foreach ($conflicts as $conflict) {
            dataProof($conflict['expected'] === $expected && $conflict['actual'] === $expected + 1, 'Conflict metadata lost the winning version.');
        }
    }
    $results = $contend(null);
    $versions = array_column($results, 'version');
    sort($versions);
    dataProof($versions === range(3, 10), 'Unguarded writers reused or lost a schema version.');
    $results = $contend(10, true);
    dataProof(count(array_filter($results, static fn (array $result): bool => $result['status'] === 'published')) === 1,
        'Concurrent rollback did not yield one winner.');
    $history = SchemaRegistry::versions('contended:data', 'fff_data_probe');
    $db = DB::connection('fff_data_probe');
    dataProof(count($history) === 11 && SchemaRegistry::currentVersion('contended:data', 'fff_data_probe') === 11
        && count(array_filter($history, static fn (array $record): bool => $record['state'] === 'live')) === 1
        && $db->table('flex_field_schema_heads')->where('schema_id', 'contended:data')->value('live_version') === 11
        && SchemaRegistry::currentVersion('tenant-b:data', 'fff_data_probe') === 1,
        'Concurrent publication/rollback broke history, live ownership or tenant isolation.');
    $checks[] = '32 independent SQL writers: first publish/update/unguarded sequencing/rollback preserve one winner, exact conflicts, unique versions, one live head and tenant isolation';
}

$queries = 0;
$db->listen(static function () use (&$queries): void {
    $queries++;
});
$memory = memory_get_usage(true);
$started = hrtime(true);
for ($index = 0; $index < 1000; $index++) {
    dataProof($submit->prepare($plain, $contracts, $allow) === $plain, 'Repeated submission retained another value or policy.');
}
dataProof($queries === 0, 'Pure data validation unexpectedly queries the database.');
$elapsed = (hrtime(true) - $started) / 1000000;
$growth = memory_get_usage(true) - $memory;
echo json_encode(['scope' => 'warm-simple-submission', 'iterations' => 1000, 'milliseconds' => round($elapsed, 2), 'database_queries' => $queries, 'allocated_growth_bytes' => $growth], JSON_THROW_ON_ERROR).PHP_EOL;
$checks[] = '1000 repeated warm submissions retain no value/policy state and make zero DB queries with these pure rules';

foreach ($checks as $check) {
    echo 'PASS: '.$check.PHP_EOL;
}
echo count($checks).' core data boundary groups passed; isolated '.$driver.', no application database writes.'.PHP_EOL;
