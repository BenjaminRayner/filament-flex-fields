<?php

use Bjanczak\FilamentFlexFields\Support\Fonts\FontCatalog;
use Filament\FontProviders\GoogleFontProvider;

// $settings is your application's cached settings record; font_id is stored by FontSelect.
$font = FontCatalog::find($settings->font_id) ?? FontCatalog::find('google:inter');

// A complete stylesheet for actual page content, not a family-name preview subset.
$stylesheet = $font->stylesheetUrl(weights: [400, 500, 600, 700]);
$family = $font->family;

// Inside your PanelProvider: supply the cached setting when the panel is resolved.
$panel->font($family, url: $stylesheet, provider: GoogleFontProvider::class);

// Or pass the immutable font record to a Blade template:
// <link rel="stylesheet" href="{{ $font->stylesheetUrl(weights: [400, 700]) }}">
// <main style="font-family: {{ $font->cssFamily() }}, sans-serif">…</main>

// Optional full variable font for arbitrary application weights (e.g. CSS 550).
$inter = FontCatalog::find('google:inter');
$variableStylesheet = $inter->variableStylesheetUrl(
    weightRange: [100, 900], styles: ['normal', 'italic'],
);
