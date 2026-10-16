<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Support;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TestableSwitchForm extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var list<\Filament\Schemas\Components\Component> */
    public static array $formSchema = [];

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $saved = null;

    /** @param array<string, mixed>|null $initial */
    public function mount(?array $initial = null): void
    {
        $this->getSchema('form')->fill($initial);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(static::$formSchema)->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->getSchema("form") }}</div>';
    }

    public function save(): void
    {
        try {
            $this->saved = $this->getSchema('form')->getState();
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }
        }
    }
}
