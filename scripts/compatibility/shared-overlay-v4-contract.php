<?php

declare(strict_types=1);

require __DIR__.'/color-picker-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SocialLinksField;
use Bjanczak\FilamentFlexFields\Support\FlexFieldAssets;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;

$overlayHost = new class extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            SocialLinksField::make('social'), ScheduleField::make('schedule')->timezone('UTC'),
            FlexDatePicker::make('date'),
        ]);
    }
};
$overlayHost->setId('shared-overlay-v4-probe');
$overlayHost->form->fill();
$documentHtml = Blade::render(
    '@php($body = $form->toHtml()) <head>@stack("styles")</head><main>{!! $body !!}</main>',
    ['form' => $overlayHost->form],
);
$overlayHtml = $documentHtml;
locationCheck(! str_contains($overlayHtml, 'x-bind:aria-haspopup="listbox"'), 'Literal accessibility value was evaluated as JS');
locationCheck(str_contains($overlayHtml, 'fff-select-headless-menu') && str_contains($overlayHtml, 'x-ref="headlessOptionsList"'), 'Shared Select listbox presentation absent');
locationCheck(str_contains($overlayHtml, 'data-fff-calendar-template') && str_contains($overlayHtml, 'fff-teleported-menu fff-dt-calendar'), 'Calendar surface adapter markup absent');
locationCheck(str_contains($overlayHtml, 'data-fff-asset-consumer="flex-date-picker"') && str_contains($overlayHtml, 'flex-date-picker.js'), 'Independent civil-date consumer/factory absent');
$head = strstr($documentHtml, '</head>', true);
foreach (['social-links-field', 'schedule-field', 'select-field', 'date-value-presentation', 'date-time-calendar', 'flex-text-input', 'teleported-menu', 'overlay-runtime'] as $asset) {
    locationCheck(! str_contains($head, 'data-fff-initial-stylesheet='), 'SSR preload bypasses visibility');
    $style = basename(parse_url(FlexFieldAssets::stylesheetHref($asset), PHP_URL_PATH));
    locationCheck(str_contains($overlayHtml, $style), 'Missing declared dependency: '.$asset);
}
locationCheck(str_contains($overlayHtml, '09:00'), 'SSR schedule Select time fallback absent');
request()->headers->set('X-Livewire', 'true');
$fragmentHtml = Blade::render(
    '@php($body = $form->toHtml()) <head>@stack("styles")</head><main>{!! $body !!}</main>',
    ['form' => $overlayHost->form],
);
locationCheck(! str_contains($fragmentHtml, 'data-fff-initial-stylesheet='), 'Livewire fragments must not duplicate the initial document head');
request()->headers->remove('X-Livewire');
echo 'PASS: actual shared calendar/time/platform markup and scoped dependency markers without eager hidden-surface head links.'.PHP_EOL;
