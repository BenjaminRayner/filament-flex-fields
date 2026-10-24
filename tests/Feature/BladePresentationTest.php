<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests;

use Bjanczak\FilamentFlexFields\Support\Rendering\BladePresentation;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\ComponentSlot;
use Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys;

class FlexPresentationProbe extends Component
{
    public function __construct(public string $caption, public bool $show = true) {}

    public function shouldRender(): bool
    {
        return $this->show;
    }

    public function render(): string
    {
        return '<section {{ $attributes }}>{{ $caption }}|{{ $slot }}|{{ $actions }}</section>';
    }
}

it('resolves native component views without retaining their instance data', function (): void {
    $presentation = app(BladePresentation::class);
    $first = $presentation->view('filament-flex-fields::text-input.control', ['value' => 'First', 'attributes' => new ComponentAttributeBag]);
    $second = $presentation->view('filament-flex-fields::text-input.control', ['value' => 'Second', 'attributes' => new ComponentAttributeBag]);
    expect($first)->not->toBe($second)
        ->and($first['view'])->toBe('filament-flex-fields::components.text-input.control')
        ->and(view($first['view'], [...$first['data'], 'slot' => new ComponentSlot])->render())->toContain('value="First"')->not->toContain('Second')
        ->and(view($second['view'], [...$second['data'], 'slot' => new ComponentSlot])->render())->toContain('value="Second"')->not->toContain('First');
});

it('preserves native class overrides including constructor data attributes and named slots', function (): void {
    $presentation = app(BladePresentation::class);
    expect($presentation->view('filament-flex-fields::text-input.shell', [])['view'])
        ->toBe('filament-flex-fields::components.text-input.shell');
    Blade::component(FlexPresentationProbe::class, 'filament-flex-fields::text-input.shell');
    $html = Blade::render(<<<'BLADE'
        @php $prepared = $presentation->view('filament-flex-fields::text-input.shell', ['caption' => 'Custom', 'attributes' => new \Illuminate\View\ComponentAttributeBag(['id' => 'custom-control'])]); @endphp
        @component($prepared['view'], $prepared['data'])
            Body
            @slot('actions')
                Action
            @endslot
        @endcomponent
        BLADE, ['presentation' => $presentation]);
    expect($html)->toContain('id="custom-control"', 'Custom|', 'Body', 'Action');
});

it('does not skip native creating or composing hooks', function (): void {
    $created = $composed = 0;
    view()->creator('filament-flex-fields::components.text-input.control', function () use (&$created): void {
        $created++;
    });
    view()->composer('filament-flex-fields::components.text-input.control', function () use (&$composed): void {
        $composed++;
    });
    foreach (['First', 'Second'] as $value) {
        $view = app(BladePresentation::class)->view('filament-flex-fields::text-input.control', ['value' => $value]);
        view($view['view'], [...$view['data'], 'slot' => new ComponentSlot])->render();
    }
    expect($created)->toBe(2)->and($composed)->toBe(2);
});

it('creates the native view only after its default and named slots are complete', function (): void {
    $observed = [];
    view()->creator('filament-flex-fields::components.text-input.shell', function ($view) use (&$observed): void {
        $data = $view->getData();
        $observed[] = [(string) ($data['slot'] ?? ''), (string) ($data['actions'] ?? '')];
    });
    Blade::render(<<<'BLADE'
        @php $prepared = $presentation->view('filament-flex-fields::text-input.shell', []); @endphp
        @component($prepared['view'], $prepared['data'])
            Native body
            @slot('actions')
                Native action
            @endslot
        @endcomponent
        BLADE, ['presentation' => app(BladePresentation::class)]);
    expect($observed)->toBe([['Native body', 'Native action']]);
});

it('preserves native render suppression for a registered component class', function (): void {
    Blade::component(FlexPresentationProbe::class, 'filament-flex-fields::text-input.shell');
    $children = 0;
    $html = Blade::render(<<<'BLADE'
        @php
            $view = $presentation->view('filament-flex-fields::text-input.shell', ['caption' => 'Hidden', 'show' => false]);
            if ($view['view'] !== null):
        @endphp
        @component($view['view'], $view['data'])
            @php $body(); @endphp
        @endcomponent
        @php endif; @endphp
        BLADE, ['presentation' => app(BladePresentation::class), 'body' => function () use (&$children): void {
        $children++;
    }]);
    expect(trim($html))->toBe('')->and($children)->toBe(0);
});

it('hands explicit component keys to native Livewire before rendering their children', function (): void {
    $keys = SupportCompiledWireKeys::$currentLoop;
    try {
        app(BladePresentation::class)->view('filament-flex-fields::text-input.control', [
            'attributes' => new ComponentAttributeBag(['wire:key' => 'native-control-key']),
        ]);
        expect(SupportCompiledWireKeys::$currentLoop['key'])
            ->toBe('native-control-key');
    } finally {
        SupportCompiledWireKeys::$currentLoop = $keys;
    }
});

it('exposes native parent data to aware children during slot capture', function (bool $classOverride): void {
    view()->addNamespace('presentation-aware', __DIR__.'/../fixtures/rendering');
    Blade::component('presentation-aware::aware-probe', 'presentation-probe');
    if ($classOverride) {
        Blade::component(FlexPresentationProbe::class, 'filament-flex-fields::text-input.shell');
    }
    $html = Blade::render(<<<'BLADE'
        @php $prepared = $presentation->view('filament-flex-fields::text-input.shell', $data); @endphp
        @component($prepared['view'], $prepared['data'])
            <x-presentation-probe />
            @slot('actions')
                Action
            @endslot
        @endcomponent
        BLADE, ['presentation' => app(BladePresentation::class), 'data' => $classOverride
        ? ['caption' => 'Native parent'] : ['actionsVisible' => 'Native parent']]);
    expect($html)->toContain('data-parent-context="Native parent"');
})->with([false, true]);
