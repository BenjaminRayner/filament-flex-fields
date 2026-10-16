<div class="fff-flex-code-panel"
    x-data="{
        requesting: false,
        failed: false,
        loadExamples() {
            if (this.requesting || this.$wire.loaded || this.tab !== 'code-example') return;
            this.requesting = true;
            this.failed = false;
            this.$wire.loadExamples().catch(() => this.failed = true).finally(() => this.requesting = false);
        }
    }"
    x-init="$watch('tab', () => loadExamples()); loadExamples()">
    @include('filament-flex-fields::catalog.code-examples', ['loaded' => $loaded])
    <button type="button" class="fff-flex-code-panel__retry" x-show="failed" x-cloak @click="loadExamples()">
        {{ __('filament-flex-fields::diagnostics.asset_retry') }}
    </button>
</div>
