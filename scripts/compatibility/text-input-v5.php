<?php

declare(strict_types=1);

// Isolated real Laravel / Livewire / Filament form; no consumer app or tests modified.
use Bjanczak\BladeHugeicons\BladeHugeiconsServiceProvider;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider;
use Bjanczak\FilamentFlexFields\Support\FormBuilder\Configurators\FlexTextInputFieldConfigurator;
use Bjanczak\FilamentFlexFields\Support\Playground\FlexTextInputPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\TextInput\TextInputDemo;
use Bjanczak\FilamentFlexFields\Support\TextInput\PasswordPolicy;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputOptions;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputValidationRule;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\Action;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\FormsServiceProvider;
use Filament\Schemas\Schema;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\RawJs;
use Filament\Support\SupportServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Livewire\Component;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\Foundation\Application;

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only');
}
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = Application::create(null, null, ['extra' => ['providers' => [BladeIconsServiceProvider::class,
    BladeHeroiconsServiceProvider::class, BladeHugeiconsServiceProvider::class, SupportServiceProvider::class, ActionsServiceProvider::class,
    SchemasServiceProvider::class, FormsServiceProvider::class, FilamentFlexFieldsServiceProvider::class, LivewireServiceProvider::class]]]);
$app['view']->share('errors', new ViewErrorBag);
$app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('x', 32)));
function inputCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function inputReject(Closure $fn): void
{
    try {
        $fn();
    } catch (InvalidArgumentException) {
        return;
    } throw new RuntimeException('Invalid configuration was accepted');
}

$groups = TextInputDemo::groups();
$seen = [];
$choices = [];
$scenarios = [];
foreach ($groups as $group) {
    foreach ($group as $scenario) {
        $scenarios[] = $scenario;
        foreach ($scenario['calls'] as $method => $value) {
            $seen[$method] = true;
            $choices[$method][] = $value;
        }
        TextInputDemo::field($scenario)->getTextInputEditingOptions();
    }
}
$fluent = [];
foreach ((new ReflectionClass(FlexTextInput::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->getReturnType()?->__toString() !== 'static' || ! str_starts_with($method->getDeclaringClass()->getName(), 'Bjanczak\\')) {
        continue;
    }
    $fluent[] = $method->name;
}
$missing = array_diff($fluent, array_keys($seen));
inputCheck($missing === [], 'Missing public demo methods: '.implode(', ', $missing));
foreach (TextInputOptions::CHOICES as $method => $values) {
    foreach ($values as $value) {
        inputCheck(in_array($value, $choices[$method] ?? [], true), 'Missing choice for '.$method);
    }
}
foreach ([['unknown' => true], ['counterWarningAt' => NAN], ['counterWarningAt' => INF], ['toolsOrder' => [['bad']]], ['counterLocale' => 'no_bad_locale'], ['draftKey' => ''],
    ['enforceCounterLimit' => true], ['suggestionLimit' => 21], ['speechDictationTimeout' => 1], ['passwordRequirements' => ['bad' => 1]]] as $invalid) {
    inputReject(fn () => TextInputOptions::resolve($invalid));
}
$builder = new FlexTextInputFieldConfigurator;
foreach (TextInputOptions::DEFAULTS as $method => $default) {
    $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $method));
    $field = $builder->configureFlexTextInputField(FlexTextInput::make('builder'), [$snake => $default]);
    inputCheck($field->getTextInputEditingOptions() === TextInputOptions::resolve([$method => $default]), 'Builder mismatch '.$method);
}
$host = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components((new FlexTextInputPlayground)->components());
    }
};
$host->setId('input-v5-probe');
$host->form->fill(TextInputDemo::defaults());
$started = hrtime(true);
$html = $host->form->toHtml();
$elapsed = (hrtime(true) - $started) / 1e6;
inputCheck(substr_count($html, 'data-fff-text-input-options=') === count($groups['overview']), 'Inactive demo groups rendered eagerly');
inputCheck(! str_contains($html, '@php(') && ! str_contains($html, '@js('), 'Uncompiled Blade');
inputCheck(str_contains($html, 'fff-segment-control'), 'Demo does not compose the real Segment primitive');
inputCheck(! str_contains($html, 'is-level-'), 'Stale password strength classes');
$fields = [];
foreach ($host->form->getFlatFields(withHidden: true) as $instance) {
    $fields[$instance->getName()] = $instance;
}
foreach ($scenarios as $scenario) {
    $field = $fields[$scenario['key']] ?? null;
    inputCheck($field instanceof FlexTextInput, 'Missing rendered field '.$scenario['key']);
    $snapshot = $field->getTextInputRenderState();
    inputCheck(str_contains($field->toHtml(), 'data-fff-asset-consumer'), 'Missing independent consumer boundary');
    if (! $snapshot->options['emojiPicker']) {
        inputCheck(! in_array('emoji-picker', $snapshot->capabilities, true), 'Emoji leaked into a plain field');
    }
    if ($snapshot->options['isPassword']) {
        inputCheck(! $snapshot->options['emojiPicker'] && ! $snapshot->options['speechDictation'], 'Unexpected password tools');
    }
}
$native = $fields['flex_text_input__attributes']->getTextInputRenderState();
inputCheck(str_contains((string) $native->inputAttributes->get('aria-describedby'), 'native-demo-help'), 'Lost native aria-describedby');
inputCheck($fields['flex_text_input__email']->getTextInputRenderState()->inputAttributes->get('autofocus') === false, 'Overview email must not request autofocus');
$holdHtml = $fields['flex_text_input__hold_password']->toHtml();
inputCheck(! str_contains($holdHtml, 'x-show="! isPasswordRevealed"'), 'Held native reveal action hides itself during pointerdown');
inputCheck(str_contains($holdHtml, 'x-on:pointerup="concealPassword()"'), 'Held password lacks its release binding');
$password = PasswordPolicy::strength('aaaaaaaaaaaa1!', ['0', '1', '2', '3', '4']);
inputCheck($password['score'] <= 1, 'Repeated password was rated strong');
$hard = new TextInputValidationRule(TextInputOptions::resolve(['counterMode' => 'graphemes', 'counterLimit' => 1, 'enforceCounterLimit' => true]), false);
$failures = [];
$hard->validate('value', '👨‍👩‍👧‍👦', function (string $error) use (&$failures): void {
    $failures[] = $error;
});
inputCheck($failures === [], 'Family emoji failed a one-grapheme limit');
$hard->validate('value', '👨‍👩‍👧‍👦x', function (string $error) use (&$failures): void {
    $failures[] = $error;
});
inputCheck(count($failures) === 1 && ! str_contains($failures[0], 'filament-flex-fields::'), 'Server enforcement failed or untranslated');
foreach (['session', 'local'] as $storage) {
    $invalid = FlexTextInput::make('sensitive')->password()->draftKey('draft')->draftStorage($storage);
    $schema = Schema::make($host)->statePath('data')->components([$invalid]);
    $schema->fill(['sensitive' => 'private']);
    inputReject(fn () => $invalid->getTextInputRenderState());
}
// Native type() and extraInputAttributes must not bypass sensitive-field policy.
foreach (['native', 'type', 'attribute'] as $route) {
    foreach (['draftKey', 'suggestions', 'verifyUsing', 'normalizeOnBlur'] as $feature) {
        $sensitive = FlexTextInput::make('sensitive');
        match ($route) {
            'native' => $sensitive->password(),
            'type' => $sensitive->type('password'),
            'attribute' => $sensitive->extraInputAttributes(['type' => 'password']),
        };
        $arguments = ['draftKey' => 'draft', 'suggestions' => ['demo'],
            'verifyUsing' => fn (): array => ['state' => 'success', 'message' => 'demo'], 'normalizeOnBlur' => 'trim'];
        $sensitive->{$feature}($arguments[$feature]);
        $schema = Schema::make($host)->statePath('data')->components([$sensitive]);
        $schema->fill(['sensitive' => 'private']);
        inputReject(fn () => $sensitive->getTextInputRenderState());
    }
    $called = false;
    $remote = FlexTextInput::make('remote')->type('password')
        ->suggestionsUsing(function () use (&$called): array {
            $called = true;

            return [];
        })
        ->verifyUsing(function () use (&$called): array {
            $called = true;

            return ['state' => 'success', 'message' => 'demo'];
        });
    $schema = Schema::make($host)->statePath('data')->components([$remote]);
    $schema->fill([]);
    inputCheck($remote->getTextInputSuggestionsForJs('demo') === [], 'Sensitive suggestions exposed');
    inputCheck($remote->verifyTextInputForJs('demo')['state'] === 'idle' && ! $called, 'Sensitive verifier exposed');
}
inputCheck($fields['flex_text_input__custom_actions']->getHint() === null, 'Long action description remains a label hint');
inputCheck(str_contains($fields['flex_text_input__custom_actions']->toHtml(), 'Both native actions show local feedback'), 'Missing native helper text');
$actionHtml = $fields['flex_text_input__custom_actions']->toHtml();
foreach (['Prefix action activated', 'Suffix action activated'] as $label) {
    inputCheck(str_contains($actionHtml, 'x-on:click="showStatus('.e(json_encode($label, JSON_THROW_ON_ERROR)).')"'), 'Malformed native Alpine action attribute');
}
if (in_array('--examples', $argv, true)) {
    foreach ($groups as $name => $items) {
        $sources = [];
        foreach ($items as $scenario) {
            $code = 'FlexTextInput::make('.var_export($scenario['key'], true).")\n    ->label(".var_export($scenario['label'], true).')';
            $skip = false;
            foreach ($scenario['calls'] as $method => $value) {
                if ($value instanceof Closure || $value instanceof Action || (is_array($value) && array_filter($value, fn ($item): bool => is_object($item)))) {
                    $skip = true;
                    break;
                }
                $multi = in_array($method, ['copyable', 'live', 'prefix', 'suffix', 'prefixIcon', 'suffixIcon'], true) && is_array($value);
                if ($value instanceof BackedEnum) {
                    $value = $value->value;
                }
                if ($value instanceof RawJs) {
                    $skip = true;
                    break;
                }
                $arguments = $multi ? implode(', ', array_map(fn ($item): string => var_export($item instanceof BackedEnum ? $item->value : $item, true), $value)) : var_export($value, true);
                $code .= "\n    ->{$method}(".$arguments.')';
            }
            if (! $skip) {
                $sources[] = $code.';';
            }
        }
        file_put_contents('resources/examples/flex-text-input/'.$name.'.php', "<?php\n\nuse Bjanczak\\FilamentFlexFields\\Filament\\Forms\\Components\\FlexTextInput;\n\n".implode("\n\n", $sources)."\n");
    }
}
echo json_encode(['public_methods' => count($fluent), 'covered_methods' => count($fluent) - count($missing), 'options' => count(TextInputOptions::DEFAULTS),
    'scenario_groups' => array_map('count', $groups), 'scenarios' => count($scenarios), 'groups' => count($groups), 'render_ms' => round($elapsed, 1), 'html_bytes' => strlen($html)], JSON_PRETTY_PRINT).PHP_EOL;
