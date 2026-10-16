<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Schema;
use Illuminate\Support\MessageBag;
use Livewire\Component;

class TestableInitialSchemaForm extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var list<SchemaComponent> */
    public static array $components = [];

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $savedState = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components(static::$components);
    }

    public function save(): void
    {
        $this->savedState = $this->form->getState();
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }

    public function getErrorBag(): MessageBag
    {
        return parent::getErrorBag() ?? new MessageBag;
    }
}
