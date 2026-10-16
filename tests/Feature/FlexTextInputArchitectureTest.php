<?php

declare(strict_types=1);

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\IconPickerField;
use Bjanczak\FilamentFlexFields\Support\Assets\PublishedResourceUrl;
use Bjanczak\FilamentFlexFields\Support\TextInput\TextInputCounter;
use Bjanczak\FilamentFlexFields\Tests\Support\TestableTranslatableForm;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Filament\Support\RawJs;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\ViewException;
use Livewire\Livewire;

beforeEach(function (): void {
    TestableTranslatableForm::$formSchema = [];
});

it('renders each native input type and binding through the new renderer', function (string $method, string $type, ?string $mode): void {
    $field = FlexTextInput::make('value')->{$method}()->live(onBlur: true)->required()->extraInputAttributes(['data-native' => 'retained']);
    TestableTranslatableForm::$formSchema = [$field];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => '']);
    $test->assertSeeHtml('data-fff-text-input')->assertSeeHtml('type="'.$type.'"')
        ->assertSeeHtml('wire:model.live.blur="data.value"')->assertSeeHtml('data-native="retained"');
    if ($mode !== null) {
        $test->assertSeeHtml('inputmode="'.$mode.'"');
    }
})->with([
    ['email', 'email', null], ['url', 'url', null], ['tel', 'tel', null],
    ['password', 'password', null], ['numeric', 'number', 'decimal'], ['integer', 'number', 'numeric'],
]);

it('retains whitespace and server clearing instead of resurrecting the initial value', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->trim(false)->characterCounter()->clearable()];
    $test = Livewire::test(TestableTranslatableForm::class);
    $test
        ->call('fillPlaygroundState', ['value' => '  draft  '])->assertSet('data.value', '  draft  ')->assertSeeHtml('value="  draft  "')
        ->set('data.value', '')->assertSet('data.value', '')->assertSeeHtml('value=""');
});

it('keeps numeric state casts and validation identical to native TextInput', function (): void {
    $nativeValue = null;
    foreach ([TextInput::class, FlexTextInput::class] as $class) {
        TestableTranslatableForm::$formSchema = [$class::make('value')->integer()->minValue(2)->maxValue(5)->required()];
        $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', '3');
        $schema = $test->instance()->getSchema('form');
        $value = $schema->getState()['value'];
        if ($class === TextInput::class) {
            $nativeValue = $value;
        }
        expect($value)->toEqual(3)->toBe($nativeValue);
        $test->set('data.value', '1');
        expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    }
});

it('renders native masks datalist bounds input attributes and accessibility without duplicated ownership', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->mask(RawJs::make('$money($input)'))
        ->datalist(['100', '200'])->minLength(2)->maxLength(10)->minValue(0)->maxValue(500)->step(0.5)
        ->autocomplete('off')->autocapitalize('none')->spellcheck(false)->enterKeyHint('done')->textDirection('auto')];
    Livewire::test(TestableTranslatableForm::class)->assertSeeHtml('x-mask:dynamic="$money($input)"')
        ->assertSeeHtml('maxlength="10"')->assertSeeHtml('spellcheck="false"')->assertSeeHtml('enterkeyhint="done"')
        ->assertSeeHtml('dir="auto"')->assertSeeHtml('<datalist')->assertSeeHtml('step="0.5"');
});

it('leaves a custom action named copy as a native action', function (): void {
    $action = Action::make('copy')->label('Custom copy action')->action(fn () => null);
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->suffixAction($action)];
    Livewire::test(TestableTranslatableForm::class)->assertSee('Custom copy action');
    expect($action)->not->toBeInstanceOf(CopyAction::class);
});

it('evaluates optional tools per rendered field and keeps plain fields lightweight', function (): void {
    $enabled = false;
    $field = FlexTextInput::make('value')->emojiPicker(function () use (&$enabled) {
        return $enabled;
    })->disabled(function () use (&$enabled) {
        return $enabled;
    });
    TestableTranslatableForm::$formSchema = [$field];
    $test = Livewire::test(TestableTranslatableForm::class);
    expect($test->instance()->getSchema('form')->getComponents()[0]->getTextInputRenderState()->capabilities)->toBe([]);
    $enabled = true;
    // Disabled fields never acquire editing-only capabilities even when requested.
    expect($field->getTextInputRenderState()->capabilities)->toBe([]);
});

it('has identical Unicode counters before and after initialization', function (): void {
    expect(TextInputCounter::count('A😀é', 'characters'))->toBe(3)
        ->and(TextInputCounter::count('A😀é', 'bytes'))->toBe(7)
        ->and(TextInputCounter::count('👨‍👩‍👧‍👦', 'graphemes'))->toBe(1)
        ->and(TextInputCounter::count(' one  two ', 'words'))->toBe(2);
});

it('retains the complete public native TextInput method surface and parameter types', function (): void {
    $native = new ReflectionClass(TextInput::class);
    $flex = new ReflectionClass(FlexTextInput::class);
    foreach ($native->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        expect($flex->hasMethod($method->getName()))->toBeTrue();
        $actual = $flex->getMethod($method->getName());
        expect((string) $actual->getReturnType())->toBe((string) $method->getReturnType());
        foreach ($method->getParameters() as $index => $parameter) {
            expect((string) $actual->getParameters()[$index]->getType())->toBe((string) $parameter->getType());
        }
    }
});

it('escapes attribute text and preserves the native deferred binding by default', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->placeholder('" onfocus="unsafe')];
    Livewire::test(TestableTranslatableForm::class)
        ->assertSeeHtml('placeholder="&quot; onfocus=&quot;unsafe"')->assertSeeHtml('wire:model="data.value"');
});

it('renders one password toolbar position and preserves RTL on the complete control', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->password()->revealable()->textDirection('rtl')];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => 'demo']);
    $html = $test->html();
    expect(substr_count($html, 'fff-flex-text-input__suffix-action'))->toBe(1);
    expect($html)->toContain('dir="rtl" data-fff-text-input', 'aria-label="Show password"', 'aria-label="Hide password"');
});

it('resolves the declared shadow stylesheet only for an enabled editable emoji capability', function (): void {
    $field = FlexTextInput::make('value');
    TestableTranslatableForm::$formSchema = [$field];
    $test = Livewire::test(TestableTranslatableForm::class);
    $test->assertDontSeeHtml('shadow.css');
    $field->emojiPicker();
    TestableTranslatableForm::$formSchema = [$field];
    Livewire::test(TestableTranslatableForm::class)->assertSeeHtml('shadow.css');
    $field->disabled();
    TestableTranslatableForm::$formSchema = [$field];
    Livewire::test(TestableTranslatableForm::class)->assertDontSeeHtml('shadow.css');
});

it('rejects undeclared resource identities and unsafe relative paths', function (): void {
    $url = PublishedResourceUrl::class;
    foreach (['../shadow.css', '/shadow.css', 'sub//shadow.css', 'sub/./shadow.css', 'shadow.css?secret'] as $file) {
        expect(fn () => $url::for('emoji-picker', 'shadow-styles', $file))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => $url::for('emoji-picker', 'unregistered', 'shadow.css'))->toThrow(InvalidArgumentException::class);
});

it('composes an independently validated inline icon beside scalar text state', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('title')->iconPicker('title_icon',
        fn (IconPickerField $picker) => $picker->sets(['heroicons'])->icons(['heroicon-o-star'])->required())];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['title' => 'Contact', 'title_icon' => 'heroicon-o-star']);
    $test->assertSeeHtml('fff-icon-select--inline')->assertSeeHtml('data.title_icon');
    $schema = $test->instance()->getSchema('form');
    expect($schema->getState())->toMatchArray(['title' => 'Contact', 'title_icon' => 'heroicon-o-star']);
    $text = $schema->getComponent('title');
    $child = $text->getChildComponents('iconPicker')[0];
    expect($child->getStatePath())->toBe('data.title_icon');
    $page = $test->instance()->callSchemaComponentMethod($child->getKey(), 'getIconPickerSelectPage', ['query' => '', 'set' => null, 'cursor' => null]);
    expect(array_column($page['items'], 'value'))->toBe(['heroicon-o-star']);
    $test->set('data.title_icon', 'heroicon-o-heart');
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
});

it('rejects icon state paths that collide with the scalar text field', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('title')->iconPicker('title.icon')];
    Livewire::test(TestableTranslatableForm::class);
})->throws(ViewException::class, 'Associated icon state cannot overlap its text input.');

it('keeps associated icons scoped and validated inside repeater rows', function (): void {
    TestableTranslatableForm::$formSchema = [Repeater::make('links')->schema([
        FlexTextInput::make('label')->iconPicker('icon', fn (IconPickerField $picker) => $picker->sets(['heroicons'])->icons(['heroicon-o-star'])),
    ])];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['links' => [
        ['label' => 'First', 'icon' => 'heroicon-o-star'], ['label' => 'Second', 'icon' => null],
    ]]);
    $state = $test->instance()->getSchema('form')->getState();
    expect(array_values($state['links']))->toBe([
        ['label' => 'First', 'icon' => 'heroicon-o-star'], ['label' => 'Second', 'icon' => null],
    ]);
});

it('revokes a readonly parent icon picker and preserves ordinary fields without it', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('plain'), FlexTextInput::make('title')->readOnly()->iconPicker('icon')];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['plain' => '', 'title' => 'Title', 'icon' => null]);
    $schema = $test->instance()->getSchema('form');
    expect($schema->getComponent('plain')->hasIconPicker())->toBeFalse()
        ->and($schema->getComponent('title')->getChildComponents('iconPicker')[0]->isDisabled())->toBeTrue();
});

it('round trips associated text and icon through database columns and fresh form hydration', function (): void {
    Schema::create('fff_text_icon_probe', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('title_icon')->nullable();
    });
    try {
        TestableTranslatableForm::$formSchema = [FlexTextInput::make('title')->iconPicker('title_icon',
            fn (IconPickerField $picker) => $picker->sets(['heroicons'])->icons(['heroicon-o-star'])->default('heroicon-o-star'))];
        $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['title' => 'Persisted label', 'title_icon' => 'heroicon-o-star']);
        $state = $test->instance()->getSchema('form')->getState();
        $id = DB::table('fff_text_icon_probe')->insertGetId($state);
        $record = (array) DB::table('fff_text_icon_probe')->find($id);
        unset($record['id']);
        $fresh = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', $record);
        $fresh->assertSet('data.title', 'Persisted label')->assertSet('data.title_icon', 'heroicon-o-star');
        expect($fresh->instance()->getSchema('form')->getState())->toMatchArray($record);
        $child = $fresh->instance()->getSchema('form')->getComponent('title')->getChildComponents('iconPicker')[0];
        expect($child->renderIconHtml($child->getState()))->toContain('<svg');
    } finally {
        Schema::dropIfExists('fff_text_icon_probe');
    }
});

it('rejects forged structured values including empty arrays without breaking null or numeric input', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')];
    foreach ([[], ['unexpected' => 'value']] as $value) {
        $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', $value);
        expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    }
    $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', null);
    expect($test->instance()->getSchema('form')->getState())->toHaveKey('value', null);
});

it('enforces Unicode limits on a forged submission while accepting one combined grapheme', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->counterMode('graphemes')->counterLimit(1)->enforceCounterLimit()];
    $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', '👨‍👩‍👧‍👦');
    expect($test->instance()->getSchema('form')->getState())->toHaveKey('value', '👨‍👩‍👧‍👦');
    $test->set('data.value', '👨‍👩‍👧‍👦x');
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
});

it('does not apply an optional password policy to empty input but rejects a forged weak password', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->password()->passwordRequirements(['min' => 8, 'uppercase' => 1, 'numbers' => 1])];
    $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', '');
    expect($test->instance()->getSchema('form')->getState())->toHaveKey('value');
    $test->set('data.value', 'weak');
    expect(fn () => $test->instance()->getSchema('form')->getState())->toThrow(ValidationException::class);
    $test->set('data.value', 'StrongTest47');
    expect($test->instance()->getSchema('form')->getState())->toHaveKey('value', 'StrongTest47');
});

it('renders four neutral password segments before editing and keeps native accessibility', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->password()->passwordStrength()->textDirection('rtl')];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['value' => '']);
    $html = $test->html();
    expect(substr_count($html, 'class="fff-flex-text-input__password-strength-segment"'))->toBe(4);
    $test->assertSeeHtml('aria-valuenow="0"')->assertSeeHtml('transform:scaleX(0)');
    $test->set('data.value', 'a');
    $test->assertSeeHtml('aria-valuenow="25"')->assertSeeHtml('transform:scaleX(1)');
});

it('uses the same four password fill thresholds on the server', function (): void {
    $field = FlexTextInput::make('value')->password()->passwordStrength();
    foreach (['' => 0, 'a' => 25, 'abcdefgh' => 25, 'abcdefghijkl' => 50, 'Abcdefghijk1' => 75, 'Fern!82Violet#47' => 100] as $value => $percent) {
        expect($field->calculatePasswordStrength($value)['percent'])->toBe($percent);
    }
});

it('renders a localized password checklist with independent rule states and hides it on request', function (): void {
    $requirements = ['min' => 12, 'uppercase' => 1, 'numbers' => 2];
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->password()->passwordStrength()->passwordRequirements($requirements)->textDirection('rtl')];
    $test = Livewire::test(TestableTranslatableForm::class)->set('data.value', 'Short47');
    $test->assertSeeHtml('aria-label="Password requirements"')->assertSeeHtml('fff-flex-text-input__requirement-tick')
        ->assertSee('At least one uppercase letter')->assertSee('At least 2 numbers');
    $html = $test->html();
    expect(substr_count($html, 'class="fff-flex-text-input__requirement"'))->toBe(3);
    expect(substr_count($html, 'data-met="true"'))->toBe(2)->and(substr_count($html, 'data-met="false"'))->toBe(1);
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('value')->password()->passwordRequirements($requirements)->passwordRequirementsVisible(false)];
    Livewire::test(TestableTranslatableForm::class)->assertDontSeeHtml('class="fff-flex-text-input__requirements"');
});

it('clears an associated icon to null without changing the text or resurrecting its default', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('title')->iconPicker('title_icon',
        fn (IconPickerField $picker) => $picker->sets(['heroicons'])->icons(['heroicon-o-star'])->default('heroicon-o-star'))];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['title' => 'Contact', 'title_icon' => 'heroicon-o-star']);
    $child = $test->instance()->getSchema('form')->getComponent('title')->getChildComponents('iconPicker')[0];
    expect($child->isClearable())->toBeTrue();
    $test->assertSeeHtml('fff-icon-select__inline-clear')->set('data.title_icon', null);
    expect($test->instance()->getSchema('form')->getState())->toBe(['title' => 'Contact', 'title_icon' => null]);
    $fresh = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['title' => 'Contact', 'title_icon' => null]);
    expect($fresh->instance()->getSchema('form')->getState())->toBe(['title' => 'Contact', 'title_icon' => null]);
});

it('can explicitly disable associated icon clearing', function (): void {
    TestableTranslatableForm::$formSchema = [FlexTextInput::make('title')->iconPicker('title_icon', fn (IconPickerField $picker) => $picker->clearable(false))];
    $test = Livewire::test(TestableTranslatableForm::class)->call('fillPlaygroundState', ['title' => 'Contact', 'title_icon' => null]);
    expect($test->instance()->getSchema('form')->getComponent('title')->getChildComponents('iconPicker')[0]->isClearable())->toBeFalse();
    $test->assertDontSeeHtml('fff-icon-select__inline-clear');
});
