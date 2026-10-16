@if ($options['permalinkPreview'])
    <div class="fff-slug-field__header">
        <span class="fff-slug-field__eyebrow">{{ $labels['permalink'] }}</span>
        @if ($options['sourcePath'] !== null)
            <div class="fff-slug-field__meta">
                <span @class(['fff-slug-field__badge', 'is-custom' => $options['initialAutoSyncDisabled']])
                    x-bind:class="{ 'is-custom': autoSyncDisabled }" x-text="badgeLabel()">{{ $options['initialAutoSyncDisabled'] ? $labels['custom'] : $labels['auto'] }}</span>
                <span class="fff-slug-field__changed" x-cloak x-show="isChangedFromRecord()">{{ $labels['changed'] }}</span>
            </div>
        @endif
    </div>
@endif
