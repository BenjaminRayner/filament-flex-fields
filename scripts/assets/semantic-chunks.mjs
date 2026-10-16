export const SEMANTIC_CHUNK_RULES = [
    {
        slug: 'flex-fields-theme-utils',
        matches: (modules) => modules.includes('core/theme-utils.js'),
    },
    {
        slug: 'flex-fields-search-normalize',
        matches: (modules) => modules.includes('core/search-normalize.js'),
    },
    {
        slug: 'flex-fields-combobox-engine',
        matches: (modules) => modules.includes('core/combobox-engine.js'),
    },
    {
        slug: 'flex-fields-emoji',
        matches: (modules) => modules.includes('core/shared-emoji-picker.js'),
    },
    {
        slug: 'flex-fields-audio',
        matches: (modules) => modules.some((modulePath) => (
            modulePath === 'core/format-time.js'
            || modulePath === 'audio/waveform.js'
            || modulePath === 'core/audio-waveform.js'
            || modulePath === 'audio/playback.js'
        )),
    },
    {
        slug: 'flex-fields-dynamic-bars',
        matches: (modules) => modules.includes('core/dynamic-bars.js'),
    },
    {
        slug: 'flex-fields-mapbox',
        matches: (modules) => modules.includes('support/mapbox-geocoding.js'),
    },
    {
        slug: 'flex-fields-select-trigger',
        matches: (modules) => modules.some((modulePath) => (
            modulePath.includes('select-field/headless-select-state.js')
            || modulePath.includes('select-field/headless-inline-search.js')
            || modulePath.includes('select-field/select-field-trigger-labels.js')
            || modulePath.includes('select-field/headless-combobox-ssr-handoff.js')
        )),
    },
    {
        slug: 'flex-fields-select-keyboard',
        matches: (modules) => modules.includes('select-field/headless-combobox-keyboard.js'),
    },
    {
        slug: 'flex-fields-select-scroll-virt',
        matches: (modules) => modules.includes('select-field/headless-combobox-scroll-virt.js'),
    },
    {
        slug: 'flex-fields-entity-mention',
        matches: (modules) => modules.includes('core/entity-mention.js'),
    },
    {
        slug: 'flex-fields-headless-user-select',
        matches: (modules) => modules.includes('select-field/headless-user-select.js'),
    },
    {
        slug: 'flex-fields-headless-livewire',
        matches: (modules) => modules.includes('select-field/headless-combobox-livewire.js'),
    },
    {
        slug: 'flex-fields-select-menu',
        matches: (modules) => modules.includes('core/searchable-select-menu.js'),
    },
    {
        slug: 'flex-fields-overlay-virtual-list',
        matches: (modules) => modules.includes('core/overlay-virtual-list.js'),
    },
    {
        slug: 'flex-fields-fff-virtual-adapter',
        matches: (modules) => modules.includes('core/fff-virtual-adapter.js')
            || modules.some((modulePath) => modulePath.includes('@tanstack/virtual-core')),
    },
    {
        slug: 'flex-fields-overlay-scrollbar',
        matches: (modules) => modules.includes('core/overlay-scrollbar.js'),
    },
    {
        slug: 'flex-fields-country-registry',
        matches: (modules) => modules.includes('select/catalogs/country-registry.js'),
    },
    {
        slug: 'flex-fields-timezone-registry',
        matches: (modules) => modules.includes('select/catalogs/timezone-registry.js'),
    },
    {
        slug: 'flex-fields-currency-registry',
        matches: (modules) => modules.includes('select/catalogs/currency-registry.js'),
    },
    {
        slug: 'flex-fields-country-search',
        matches: (modules) => modules.includes('select/adapters/country-search.js'),
    },
    {
        slug: 'flex-fields-barcode-scanner',
        matches: (_modules, inputs) => inputs.some((input) => input.includes('@zxing/')),
    },
    {
        slug: 'flex-fields-whisper',
        matches: (_modules, inputs) => inputs.some((input) => input.includes('@huggingface/transformers')),
    },
    {
        slug: 'flex-fields-time-panel',
        matches: (modules) => modules.includes('core/date-time/time-panel.js'),
    },
    {
        slug: 'flex-fields-calendar-interactions',
        matches: (modules) => modules.includes('core/date-time/calendar-interactions.js'),
    },
    {
        slug: 'flex-fields-segment-editing',
        matches: (modules) => modules.includes('core/date-time/segment-editing.js'),
    },
    {
        slug: 'flex-fields-calendar-panel',
        matches: (modules) => modules.includes('core/date-time/calendar-panel.js'),
    },
    {
        slug: 'flex-fields-phone-lib',
        matches: (_modules, inputs) => inputs.some((input) => input.includes('libphonenumber-js')),
    },
];

