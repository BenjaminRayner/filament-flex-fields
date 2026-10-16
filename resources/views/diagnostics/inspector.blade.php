<aside wire:ignore class="fff-asset-lab__inspector" x-fff-load x-fff-load-src="{{ \Bjanczak\FilamentFlexFields\Support\FlexFieldAssets::alpineEntrySrc('asset-diagnostics') }}" x-data="assetDiagnosticsComponent()">
    <h2>{{ __('filament-flex-fields::diagnostics.live_assets') }}</h2>
    <p>{{ __('filament-flex-fields::diagnostics.inspector_hint') }}</p>
    <label class="fff-asset-lab__hold"><input type="checkbox" x-model="slowReveal"> {{ __('filament-flex-fields::diagnostics.slow_reveal') }}</label>
    <dl>
        <div><dt>{{ __('filament-flex-fields::diagnostics.modals_open') }}</dt><dd x-text="stack.length">0</dd></div>
        <div><dt>{{ __('filament-flex-fields::diagnostics.loading') }}</dt><dd x-text="inflight">0</dd></div>
        <div><dt>{{ __('filament-flex-fields::diagnostics.duplicates') }}</dt><dd x-text="duplicates" :class="duplicates ? 'is-error' : ''">0</dd></div>
        <div><dt>{{ __('filament-flex-fields::diagnostics.layout_score') }}</dt><dd data-fff-layout-score x-text="layoutScore === null ? '—' : layoutScore.toFixed(5)">—</dd></div>
    </dl>
    <ul class="fff-asset-lab__history" data-fff-layout-shifts><template x-for="shift in layoutShifts" :key="shift.id"><li><time x-text="shift.time + ' ms'"></time><span x-text="shift.score.toFixed(5) + ' · ' + shift.sources"></span></li></template></ul>
    <ol class="fff-asset-lab__stack"><template x-for="id in stack" :key="id"><li x-text="id"></li></template></ol>
    <ul class="fff-asset-lab__assets"><template x-for="asset in assets" :key="asset.url">
        <li><div><span x-text="asset.name"></span><b x-text="asset.refs"></b></div><small x-text="asset.owners"></small></li>
    </template></ul>
    <h3>{{ __('filament-flex-fields::diagnostics.history') }}</h3>
    <ul class="fff-asset-lab__history"><template x-for="event in events" :key="event.id"><li><time x-text="event.time"></time><span x-text="event.text"></span></li></template></ul>
    <p>{{ __('filament-flex-fields::diagnostics.js_cache') }}</p>
</aside>
