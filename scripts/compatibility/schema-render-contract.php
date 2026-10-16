<?php

declare(strict_types=1);
use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\Countries;
use Bjanczak\FilamentFlexFields\Support\Icons\IconSvgCache;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Composer\InstalledVersions;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('Compatibility probes are CLI-only.');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = Application::create(null, null, ['extra' => ['providers' => [
    LivewireServiceProvider::class,
    BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class,
    BladeHugeiconsServiceProvider::class,
    SupportServiceProvider::class,
    ActionsServiceProvider::class,
    SchemasServiceProvider::class,
    FormsServiceProvider::class,
    FilamentFlexFieldsServiceProvider::class,
]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
$app['config']->set('filament-flex-fields.playground.enabled', false);
$app['config']->set('view.compiled', getcwd().'/build/filament-v590-views');
if (! is_dir($app['config']->get('view.compiled'))) {
    mkdir($app['config']->get('view.compiled'), 0755, true);
}
class V590SchemaHost extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        $c = 'Bjanczak\FilamentFlexFields\Filament\Forms\Components\\';

        return $schema->statePath('data')->components([
            ($c.'SelectField')::make('choice')->options(['Group' => ['a' => 'Alpha', 'b' => 'Beta']])->searchable()->default('a'),
            ($c.'UserSelect')::make('users')->options(['u' => [
                'label' => 'User',
                'description' => 'user@example.com',
                'verified' => true,
            ]])->multiple()->default(['u']),
            ($c.'CountryField')::make('country')->countries(['US', 'PL'])->default('PL'),
            ($c.'PhoneField')::make('phone')->countries(['US'])->defaultCountry('US')->default([
                'country' => 'US',
                'national' => '2025550123',
                'e164' => '',
            ]),
            ($c.'TimezoneField')::make('timezone')->timezones(['UTC', 'Europe/Warsaw'])->default('UTC'),
            ($c.'CurrencyField')::make('currency')->default(12345),
            ($c.'TagsField')::make('tags')->suggestions(['Laravel', 'PHP'])->default(['PHP']),
            ($c.'IconPickerField')::make('icon')->default('hugeicons-user'),
            ($c.'AddressAutocompleteField')::make('address'),
            ($c.'MapPickerField')::make('map'),
        ]);
    }
}
$host = new V590SchemaHost;
$host->setId('v590-schema-probe');
$host->form->fill();
$html = $host->form->toHtml();
foreach ([
    'Alpha',
    'User',
    'user@example.com',
    'Country',
    'Phone',
    'Timezone',
    'Currency',
    'Tags',
    'Address',
    'Map',
] as $text) {
    if (! str_contains($html, $text)) {
        throw new RuntimeException('Missing rendered field '.$text);
    }
}
$label = $host->callSchemaComponentMethod('form.choice', 'getOptionLabel');
if ($label !== 'Alpha') {
    throw new RuntimeException('Native exposed label endpoint is unavailable.');
}
$users = $host->callSchemaComponentMethod('form.users', 'getOptionLabelsForJs');
if (count($users) !== 1 || $users[0]['user']['name'] !== 'User') {
    throw new RuntimeException('User label transport is unavailable.');
}
$state = $host->form->getState();
if ($state['choice'] !== 'a' || $state['users'] !== ['u'] || $state['phone']['e164'] !== '+12025550123') {
    throw new RuntimeException('Schema state mismatch');
}
// A new container scope must not inherit the previous request's translated labels.
$app->forgetScopedInstances();
$app['translator']->addLines(['countries.PL' => 'Tenant Alpha'], 'en', 'filament-flex-fields');
$alpha = Countries::metadata(['PL'], [], 'en')[0]['name'];
$app['translator']->addLines(['countries.PL' => 'Tenant Beta'], 'en', 'filament-flex-fields');
$app->forgetScopedInstances();
$beta = Countries::metadata(['PL'], [], 'en')[0]['name'];
if ($alpha !== 'Tenant Alpha' || $beta !== 'Tenant Beta') {
    throw new RuntimeException('Localized picker metadata leaked across container scopes');
}
$svgCache = $app->make(IconSvgCache::class);
$renderCalls = 0;
$render = static function (array $names) use (&$renderCalls): array {
    $renderCalls++;

    return array_fill_keys($names, '<svg></svg>') + ['unexpected-fixture' => '<svg></svg>'];
};
$svgCache->rememberMany(['compat-icon-a', 'compat-icon-b'], $render);
$svgs = $svgCache->rememberMany(['compat-icon-a', 'compat-icon-b', 'unexpected-fixture'], $render);
if ($renderCalls !== 2 || count($svgs) !== 3) {
    throw new RuntimeException('SVG batch cache retained unrequested renderer output or missed a valid cache hit');
}
echo 'PASS: ten picker fields configured, hydrated, rendered and validated through a package-local Laravel/Livewire schema on Filament '.InstalledVersions::getPrettyVersion('filament/forms').' ('.strlen($html).' HTML bytes).'.PHP_EOL;
