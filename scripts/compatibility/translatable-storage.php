<?php

declare(strict_types=1);

// Run separately with/without the optional package; never modify the consumer DB.
$spatieDirectory = $argv[1] ?? null;
if ($spatieDirectory !== null) {
    spl_autoload_register(static function (string $class) use ($spatieDirectory): void {
        $prefix = 'Spatie\\Translatable\\';
        if (str_starts_with($class, $prefix)) {
            $file = $spatieDirectory.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    });
}
require __DIR__.'/segment-composition.php';
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, (string) $error.PHP_EOL);
    exit(1);
});

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;
use Bjanczak\FilamentFlexFields\Support\Translatable\SpatieTranslatableIntegration;
use Bjanczak\FilamentFlexFields\Support\Translatable\TranslatableLocales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Livewire\Component;
use Spatie\Translatable\Events\TranslationHasBeenSetEvent;
use Spatie\Translatable\HasTranslations;
use Spatie\Translatable\Translatable;

$makeHost = static function (array $components, Model|array|null $record = null): Component {
    $host = new class extends Component implements HasForms
    {
        use InteractsWithForms;

        public array $data = [];

        public array $components = [];

        public Model|array|null $probeRecord = null;

        public function form(Schema $schema): Schema
        {
            return $schema->statePath('data')->model($this->probeRecord)->components($this->components);
        }
    };
    $host->setId('translatable-storage-probe');
    $host->components = $components;
    $host->probeRecord = $record;

    return $host;
};
$hooks = 0;
$template = FlexTextInput::make('title')->default('Template default')
    ->afterStateHydrated(function () use (&$hooks): void {
        $hooks++;
    })
    ->dehydrateStateUsing(fn (mixed $state): mixed => is_string($state) ? '['.$state.']' : $state);
$layout = TranslatableFields::make('Content')->locales(['en', 'pl', 'pt_BR'])->schema([$template]);
$host = $makeHost([$layout]);
$host->form->fill(['title' => json_encode(['en' => 'Hello', 'pl' => 'Cześć', 'pt_BR' => 'Olá'])]);
locationCheck($host->data['title']['pt_BR'] === 'Olá' && $hooks === 3, 'JSON/regional keys/native hydration callbacks');
$host->form->toHtml();
locationCheck($host->form->getState()['title']['en'] === '[Hello]', 'Custom dehydration callback lost');
$host->data['title']['en'] = '';
$layout->locales(['en', 'pl', 'pt_BR']);
$host->form->toHtml();
locationCheck($host->data['title']['en'] === '', 'Cleared translation restored on schema rebuild');
$host->form->fill();
locationCheck($host->data['title']['en'] === 'Template default', 'Field default overwritten');
$layout->borderedPanels()->borderedPanels(false);
locationCheck(! str_contains($host->form->toHtml(), 'fff-translatable-fields--bordered'), 'Bordered preset cannot be disabled');
locationCheck(TranslatableLocales::resolve(['en' => 'English'], ['en' => 'Override'])['en'] === 'Override', 'Locale label override ignored');
locationReject(fn () => TranslatableLocales::resolve(['en', 'en']));
locationReject(fn () => TranslatableLocales::resolve(['en.evil']));

$custom = TextInput::make('ignored')->default('Custom default');
$customLayout = TranslatableFields::make('Custom')->locales(['en', 'pl'])
    ->localeFieldUsing(fn () => $custom, useTemplateStatePath: true)->schema([TextInput::make('title')]);
$customHost = $makeHost([$customLayout]);
$customHost->form->fill(['title' => ['en' => 'Custom EN', 'pl' => 'Custom PL']]);
$customFields = $customLayout->getChildSchema()->getFlatComponents(withHidden: true);
$fields = array_values(array_filter($customFields, fn ($field) => $field instanceof TextInput));
locationCheck(count($fields) === 2 && $fields[0] !== $fields[1] && $custom->getName() === 'ignored', 'Custom field instance leaked across locales');
locationCheck($customHost->form->getState()['title']['pl'] === 'Custom PL', 'Custom field bypasses managed translation paths');

$macro = TextInput::make('macro_title')->label(fn (Component $livewire): string => 'Injected macro')->translatableFields(['en']);
$macroHost = $makeHost([$macro]);
$macroHost->form->fill(['macro_title' => ['en' => 'Macro content']]);
locationCheck(str_contains($macroHost->form->toHtml(), 'Injected macro'), 'Macro resolved injected label before a container existed');
$presets = TranslatableFields::make('Presets')->locales(['ar-SA', 'en'])->withRecommendedDefaults('Missing')
    ->schema([FlexTextInput::make('title')]);
$presetHost = $makeHost([$presets]);
$presetHost->form->fill(['title' => ['ar-SA' => '', 'en' => 'Present']]);
locationCheck($presets->getActiveTab() === 2, 'Active-value preset chose an empty locale');
$localeTabs = $presets->getChildSchema()->getComponents();
locationCheck($localeTabs[0]->getBadge() === 'Missing' && $localeTabs[1]->getBadge() === null, 'Empty badges mismatch');
$arabic = $localeTabs[0]->getChildSchema()->getComponents()[0];
locationCheck($arabic->getExtraInputAttributes()['dir'] === 'rtl', 'Regional RTL direction missing');
locationCheck(in_array('live', $arabic->getStateBindingModifiers(), true), 'Empty-badge preset lost inherited live binding');
$presetHost->data['title']['en'] = '';
locationCheck($localeTabs[1]->getBadge() === 'Missing', 'Badge restores a cleared value');
$host->form->fillPartially(['title' => json_encode(['en' => 'Partial', 'pl' => 'Częściowy', 'pt_BR' => 'Parcial'])], ['title']);
locationCheck($host->data['title']['pl'] === 'Częściowy', 'Partial hydration left a JSON string');

$nested = $makeHost([Repeater::make('items')->schema([
    TranslatableFields::make('Nested')->locales(['en', 'pl'])->schema([TextInput::make('title')]),
])]);
$nested->form->fill(['items' => [['title' => ['en' => 'First', 'pl' => 'Pierwszy']], ['title' => ['en' => 'Second', 'pl' => 'Drugi']]]]);
$items = array_values($nested->form->getState()['items']);
locationCheck($items[0]['title']['en'] === 'First' && $items[1]['title']['pl'] === 'Drugi', 'Repeater locale paths crossed');

if ($spatieDirectory === null) {
    locationCheck(! SpatieTranslatableIntegration::isAvailable(), 'Without-Spatie probe unexpectedly loads Spatie');
    try {
        $without = $makeHost([TranslatableFields::make('Opt in')->locales(['en'])->spatieTranslatable()->schema([TextInput::make('title')])]);
        $without->form->fill();
        throw new RuntimeException('Missing optional package did not report configuration error');
    } catch (LogicException $error) {
        locationCheck(str_contains($error->getMessage(), 'optional spatie/laravel-translatable'), 'Wrong missing-package error');
    }
    echo "PASS: optional absence, JSON hydration, exact locale keys, callbacks/defaults, clear/rebuild, reversible presets, custom field isolation and real Filament repeaters.\n";
    exit;
}

$app->instance(Translatable::class, new Translatable);
$getterModel = new class extends Model
{
    use HasTranslations;

    public array $translatable = ['title'];

    public function getTitleAttribute(mixed $value): string
    {
        return 'View:'.$value;
    }
};
$getterModel->setTranslations('title', ['en' => 'Stored']);
locationCheck(SpatieTranslatableIntegration::translations($getterModel, 'title')['en'] === 'View:Stored', 'Spatie locale getter bypassed');
$attributeModel = new #[Spatie\Translatable\Attributes\Translatable(['title'])] class extends Model
{
    use HasTranslations;
};
locationCheck(SpatieTranslatableIntegration::modelUsesTranslatable($attributeModel, 'title'), 'Spatie attribute-based declarations not detected');
$app['config']->set('database.default', 'sqlite');
$app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]);
$app['db']->purge();
$app['db']->connection()->getSchemaBuilder()->create('translation_probe_records', function (Blueprint $table): void {
    $table->id();
    $table->json('title')->nullable();
    $table->json('content')->nullable();
    $table->json('meta')->nullable();
});
$record = new class extends Model
{
    use HasTranslations;

    protected $table = 'translation_probe_records';

    protected $guarded = [];

    public $timestamps = false;

    public array $translatable = ['title', 'content', 'meta->description'];
};
$record->setTranslations('title', ['en' => 'Record EN', 'pl' => 'Record PL', 'pt_BR' => 'Olá', 'de' => 'Keep DE']);
$record->setTranslations('content', ['en' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]], 'pl' => false, 'pt_BR' => 0]);
$record->setTranslations('meta->description', ['en' => 'Nested EN', 'pl' => 'Nested PL']);
$record->save();
$translationEvents = 0;
$app['events']->listen(TranslationHasBeenSetEvent::class, function () use (&$translationEvents): void {
    $translationEvents++;
});
$fallbackCalls = 0;
$app->make(Translatable::class)->fallback(fallbackLocale: 'en', fallbackAny: true, missingKeyCallback: function () use (&$fallbackCalls): string {
    $fallbackCalls++;

    return 'Fallback';
});
$spatie = TranslatableFields::make('Spatie')->spatieTranslatable()->locales(['en', 'pl', 'pt_BR', 'fr'])->schema([
    TextInput::make('title')->dehydrateStateUsing(fn (mixed $state): mixed => $state),
    TextInput::make('content'), TextInput::make('meta->description'),
]);
$spatieHost = $makeHost([$spatie], $record);
// Reproduce accessor-shaped edit data; the adapter must recover maps before child hydration.
$spatieHost->form->fill(['title' => $record->title, 'content' => $record->content, 'meta->description' => $record->getTranslation('meta->description', 'en')]);
locationCheck($spatieHost->data['title']['pl'] === 'Record PL', 'Spatie accessor-shaped fill lost locale map');
locationCheck(($spatieHost->data['title']['fr'] ?? null) === null && $fallbackCalls === 0, 'Editor copied fallback into untranslated locale');
locationCheck($spatieHost->data['content']['pl'] === false && $spatieHost->data['content']['pt_BR'] === 0, 'Spatie boolean/numeric translations lost');
locationCheck($spatieHost->data['content']['en']['type'] === 'doc', 'Structured Spatie translation lost');
locationCheck($spatieHost->data['meta']['description']['pl'] === 'Nested PL', 'Spatie nested JSON attribute lost');
$spatieHost->data['title']['en'] = '';
$spatieHost->data['title']['pl'] = ' Nowa treść ';
$spatie->locales(['en', 'pl', 'pt_BR', 'fr']);
$spatieHost->form->toHtml();
locationCheck($spatieHost->data['title']['en'] === '', 'Spatie clear restored old record value');
$state = $spatieHost->form->getState();
$record->fill($state)->save();
$record->refresh();
locationCheck($translationEvents > 0, 'Native Spatie translation events were bypassed');
locationCheck($record->getTranslations('title')['pl'] === ' Nowa treść ', 'Native field whitespace/dehydration changed');
locationCheck($record->getTranslations('title')['de'] === 'Keep DE', 'Saving configured locales removed unexposed locale');
locationCheck(($record->getTranslations('title')['en'] ?? '') === '', 'Cleared translation persisted old value');
locationCheck($record->getTranslations('content')['pl'] === false && $record->getTranslations('content')['pt_BR'] === 0, 'Spatie SQLite roundtrip changed scalar types');
locationCheck($record->getTranslations('meta->description')['pl'] === 'Nested PL', 'Nested Spatie SQLite roundtrip failed');
$spatieHost->form->fill($record->toArray());
locationCheck($spatieHost->data['title']['pl'] === ' Nowa treść ', 'Spatie toArray edit hydration failed');
$spatieHost->form->fill(['title' => ['en' => null], 'content' => [], 'meta' => ['description' => []]]);
locationCheck($spatieHost->data['title']['en'] === null && ($spatieHost->data['title']['pl'] ?? null) === null, 'Explicit empty map/null was replaced with record content');
echo "PASS: real Spatie v6 SQLite create/edit/save/reload, accessor/toArray hydration, absent-locale fallback isolation, exact regional keys, structured/scalar data, nested JSON attributes and unexposed locale retention.\n";
