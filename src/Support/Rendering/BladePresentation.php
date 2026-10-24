<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Rendering;

use Bjanczak\FilamentFlexFields\Support\FilamentApi;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\View\AnonymousComponent;
use Illuminate\View\Compilers\ComponentTagCompiler;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys;

/** Resolve presentation metadata only; every view, slot and data array stays fresh. */
final class BladePresentation
{
    /** @var array<mixed> */
    private array $registrations = [];

    /** @var array<string, string> */
    private array $resolved = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array{view: View|Htmlable|\Closure|string|null, data: array<string, mixed>}
     */
    public function view(string $component, array $data): array
    {
        $compiler = app('blade.compiler');
        $registrations = [$compiler->getClassComponentAliases(), $compiler->getClassComponentNamespaces(),
            $compiler->getAnonymousComponentPaths(), $compiler->getAnonymousComponentNamespaces()];
        if ($registrations !== $this->registrations) {
            $this->registrations = $registrations;
            $this->resolved = [];
        }
        $resolved = $this->resolved[$component] ?? (new ComponentTagCompiler($registrations[0], $registrations[1], $compiler))
            ->componentClass($component);
        if (count($this->resolved) < 64) {
            $this->resolved[$component] = $resolved;
        }
        if (is_subclass_of($resolved, Component::class)) {
            $attributes = $data['attributes'] ?? new ComponentAttributeBag;
            $instance = $resolved::resolve(array_diff_key($data, ['attributes' => true]) + $attributes->all());
            $instance->withName($component);
            if (! $instance->shouldRender()) {
                return ['view' => null, 'data' => []];
            }
            $nativeView = $instance->resolveView();
            $nativeData = $instance->data();
            $instance->withAttributes($attributes->except($resolved::ignoredParameterNames())->all());
            if (class_exists(SupportCompiledWireKeys::class)
                && FilamentApi::hasMethod(SupportCompiledWireKeys::class, 'processComponentKey')) {
                SupportCompiledWireKeys::processComponentKey($instance);
            }

            return ['view' => $nativeView, 'data' => $nativeData];
        }
        $attributes = $data['attributes'] ?? null;
        if ($attributes instanceof ComponentAttributeBag && $attributes->has('wire:key')
            && class_exists(SupportCompiledWireKeys::class)
            && FilamentApi::hasMethod(SupportCompiledWireKeys::class, 'processComponentKey')) {
            $keyOwner = new AnonymousComponent($resolved, $data);
            $keyOwner->withAttributes($attributes->all());
            SupportCompiledWireKeys::processComponentKey($keyOwner);
        }

        // Let native Blade capture slots, expose @aware data and create the view
        // at its original lifecycle point. Never pre-render or retain a View.
        return ['view' => $resolved, 'data' => $data];
    }
}
