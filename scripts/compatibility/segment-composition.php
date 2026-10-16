<?php

declare(strict_types=1);

require __DIR__.'/shared-overlay-v4-contract.php';

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTextInput;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SegmentControl;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\SegmentTabs\SegmentTab;
use Bjanczak\FilamentFlexFields\Filament\Schemas\Components\TranslatableFields;
use Bjanczak\FilamentFlexFields\Support\Assets\ComponentAssetPlan;
use Bjanczak\FilamentFlexFields\Support\Assets\InitialSchemaVisibility;
use Filament\Schemas\Schema;

try {
    $modifiers = 0;
    $localeFields = TranslatableFields::make('Translations')
        ->locales(['en' => 'English', 'pl' => 'Polski'])
        ->schema([FlexTextInput::make('title')->label(fn (): string => 'Dynamic title')])
        ->modifyFieldsUsing(function () use (&$modifiers): void {
            $modifiers++;
        });
    $selectLocales = TranslatableFields::make('Select translations')->locales(['en', 'pl'])
        ->schema([SelectField::make('status')->options(['a' => 'Alpha', 'b' => 'Beta'])]);
    $control = SegmentControl::make('choice')->options([
        0 => 'Zero', 'disabled' => ['label' => 'Disabled', 'disabled' => true], 'last' => 'Last',
    ])->default(0);
    $tabs = SegmentTabs::make('Panels')->persistTabInQueryString('segment-probe')->tabs([
        SegmentTab::make('Hidden')->key('hidden')->hidden()->schema([FlexTextInput::make('hidden')]),
        SegmentTab::make('First')->key('first')->schema([$control]),
        SegmentTab::make('Second')->key('second')->schema([$localeFields, $selectLocales]),
    ]);
    $schema = Schema::make($overlayHost)->key('segment-probe')->statePath('data')->components([$tabs]);
    $overlayHost->data = ['choice' => 0, 'title' => ['en' => 'Hello', 'pl' => 'Cześć']];
    $schema->getFlatComponents(withHidden: true);
    request()->query->set('segment-probe', 'second');
    if ($tabs->getActiveTabKey() !== 'second' || $tabs->getActiveTab() !== 2) {
        throw new RuntimeException('Relative query selection ignored hidden tabs incorrectly.');
    }
    if (InitialSchemaVisibility::allows($control) || ! InitialSchemaVisibility::allows($localeFields)) {
        throw new RuntimeException('Initial child CSS visibility mismatch.');
    }
    $html = $schema->toHtml();
    if (str_contains(file_get_contents('resources/views/schemas/components/segment-tabs.blade.php'), 'x-init="init()"') || str_contains(file_get_contents('resources/views/forms/components/segment-control.blade.php'), 'x-init="init()"')) {
        throw new RuntimeException('Double Alpine initialization remains.');
    }
    if (! str_contains($html, 'data-fff-asset-consumer="translatable-fields"')) {
        throw new RuntimeException('Locale layout lost its instance asset boundary.');
    }
    $first = $localeFields->getChildSchema();
    $second = $localeFields->getChildSchema();
    if ($first !== $second || $modifiers !== 2) {
        throw new RuntimeException('Locale templates or modifiers were repeated.');
    }
    $localeTab = $first->getComponents()[0];
    $field = $localeTab->getChildSchema()->getComponents()[0];
    if ($field->getState() !== 'Hello' || $field->getLabel() !== 'Dynamic title') {
        throw new RuntimeException('Locale state/label callbacks were lost.');
    }
    $clone = $localeFields->getClone();
    $clone->container($localeFields->getContainer());
    if ($clone->getChildSchema() === $first) {
        throw new RuntimeException('Clone shares mutable locale fields.');
    }
    $localeFields->locales(['de' => 'Deutsch']);
    if (count($localeFields->getChildSchema()->getComponents()) !== 1 || $localeFields->getChildSchema()->getComponents()[0]->getLocale() !== 'de') {
        throw new RuntimeException('Locale reconfiguration failed to invalidate schema.');
    }
    $plan = ComponentAssetPlan::for('translatable-fields');
    if (in_array('flex-text-input', $plan->components, true) || in_array('flex-textarea', $plan->components, true)) {
        throw new RuntimeException('Locale wrapper eagerly imports unrelated child types.');
    }
    if (! $control->isOptionDisabled('disabled') || $control->isOptionDisabled(0)) {
        throw new RuntimeException('Numeric/disabled choices regressed.');
    }
    request()->query->remove('segment-probe');
    if ($tabs->getActiveTabKey() !== 'first') {
        throw new RuntimeException('Visible default index is unstable.');
    }
    echo "PASS: real SegmentControl/SegmentTabs/TranslatableFields rendering, SSR/query selection, native child state, injected labels, one-time locale composition, clone isolation, cache invalidation and instance asset plans.\n";

} catch (Throwable $error) {
    fwrite(STDERR, $error."\n");
    exit(1);
}
