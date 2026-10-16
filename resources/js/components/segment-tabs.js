import { createSegmentRuntime } from './segments/runtime.js'

/** Schema adapter: no field entanglement, no page-wide Livewire interception. */
export default function segmentTabsSchemaComponent({
    activeTabKey = null, activeTabIndex = 0, optionKeys = [],
    isTabPersisted = false, tabPersistKey = null,
    isTabPersistedInQueryString = false, tabQueryStringKey = null,
    livewireId = null, schemaKey = null,
}) {
    const initial = activeTabKey ?? optionKeys[activeTabIndex] ?? optionKeys[0] ?? null
    const valid = value => optionKeys.some(key => String(key) === String(value))
    return {
        ...createSegmentRuntime({
            property: 'tab', optionKeys, repairSelection: true,
            setup(effects) {
                if (isTabPersistedInQueryString && tabQueryStringKey) {
                    const value = new URLSearchParams(window.location.search).get(tabQueryStringKey)
                    if (valid(value)) this.tab = value
                } else if (isTabPersisted && tabPersistKey) {
                    try {
                        const value = JSON.parse(localStorage.getItem(tabPersistKey))
                        if (valid(value)) this.tab = value
                    } catch { /* Storage may be unavailable. The SSR default remains usable. */ }
                }
                effects.listen(window, 'reset-schema-component-state', event => {
                    if (event.detail?.livewireId !== livewireId || event.detail?.schemaKey !== schemaKey || isTabPersisted || isTabPersistedInQueryString) return
                    this.tab = initial
                })
                effects.listen(window, 'popstate', () => {
                    if (!isTabPersistedInQueryString || !tabQueryStringKey) return
                    const value = new URLSearchParams(window.location.search).get(tabQueryStringKey)
                    this.tab = valid(value) ? value : initial
                })
            },
            onChange(value) {
                if (isTabPersistedInQueryString && tabQueryStringKey) {
                    const url = new URL(window.location.href)
                    if (value == null) url.searchParams.delete(tabQueryStringKey)
                    else url.searchParams.set(tabQueryStringKey, value)
                    if (url.href !== window.location.href) history.replaceState(history.state, '', url)
                } else if (isTabPersisted && tabPersistKey) {
                    try { localStorage.setItem(tabPersistKey, JSON.stringify(value)) } catch {}
                }
            },
        }),
        tab: initial,
    }
}
