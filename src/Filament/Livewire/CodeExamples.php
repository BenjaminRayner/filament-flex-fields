<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Livewire;

use Bjanczak\FilamentFlexFields\Support\Catalog\Examples\CodeExampleRepository;
use Bjanczak\FilamentFlexFields\Support\Catalog\FlexCatalog;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsPlaygroundRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The reader's first request must never render or morph the demo page. */
#[Isolate]
final class CodeExamples extends Component
{
    #[Locked]
    public string $componentId = '';

    #[Locked]
    public bool $loaded = false;

    public function mount(string $componentId): void
    {
        $this->componentId = $componentId;
        $this->authorizeEntry();
    }

    public function loadExamples(): void
    {
        $this->authorizeEntry();

        if ($this->loaded) {
            $this->skipRender();

            return;
        }

        $this->loaded = true;
    }

    protected function authorizeEntry(): void
    {
        abort_unless(FlexFieldsPlaygroundRegistry::isEnabled() && Auth::check()
            && app(FlexCatalog::class)->find($this->componentId) !== null, 403);
    }

    public function render(): View
    {
        $this->authorizeEntry();

        return view('filament-flex-fields::catalog.code-example-reader', [
            'examples' => $this->loaded
                ? app(CodeExampleRepository::class)->forComponent($this->componentId) : [],
        ]);
    }
}
