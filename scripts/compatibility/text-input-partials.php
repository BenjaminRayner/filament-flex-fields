<?php

declare(strict_types=1);
use Bjanczak\FilamentFlexFields\Support\Playground\FlexTextInputPlayground;
use Bjanczak\FilamentFlexFields\Support\Playground\TextInput\TextInputDemo;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Support\Livewire\Partials\PartialsComponentHook;
use Livewire\Component;
use Livewire\ComponentHookRegistry;
use Livewire\Livewire;

// Real Livewire requests, including dirty field updates bundled with deferred tabs.
// Keep compatibility probes outside the protected test suite.
require __DIR__.'/text-input-v5.php';

final class TextInputPartialProbe extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(TextInputDemo::defaults());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components((new FlexTextInputPlayground)->components());
    }

    public function render(): string
    {
        return '<div><header data-stable-header>Stable header</header>{{ $this->form }}</div>';
    }
}

foreach ([false, true] as $dirty) {
    $probe = Livewire::test(TextInputPartialProbe::class);
    $schemas = [];
    $walk = function (Schema $schema) use (&$walk, &$schemas): void {
        if ($schema->isLoadingDeferred()) {
            $schemas[] = $schema->getKey();
        }
        foreach ($schema->getComponents(withActions: false) as $component) {
            foreach ($component->getChildSchemas() as $child) {
                $walk($child);
            }
        }
    };
    $walk($probe->instance()->form);
    $key = current(array_filter($schemas, fn (string $key): bool => str_ends_with($key, 'input-demo-counters')));
    inputCheck(is_string($key), 'Counters deferred schema is missing');
    $probe->update(calls: [['method' => 'loadDeferredSchema', 'params' => [$key], 'path' => '']],
        updates: $dirty ? ['data.flex_text_input__email' => 'changed@example.test'] : []);
    $effects = $probe->effects;
    inputCheck(ComponentHookRegistry::getHook($probe->instance(), PartialsComponentHook::class) !== null, 'Filament partial hook was not registered');
    echo json_encode(['dirty' => $dirty, 'key' => $key, 'effects' => array_keys($effects), 'partials' => array_keys($effects['partials'] ?? [])]).PHP_EOL;
    inputCheck(! isset($effects['html']), 'Tab request rendered the whole Livewire root');
    inputCheck(count($effects['partials'] ?? []) === ($dirty ? 2 : 1), 'Expected only the deferred group and optional changed field');
    foreach ($effects['partials'] as $partial) {
        inputCheck(! str_contains(json_encode($partial, JSON_THROW_ON_ERROR), 'data-stable-header'), 'Header leaked into a tab partial');
    }
}
echo "PASS: first deferred tab, with and without a pending field update, renders partials only.\n";
