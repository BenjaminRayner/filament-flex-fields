import { normalizeSearchQuery } from '../../core/search-normalize.js'

function rank(record, query) {
    if (record.code === query) return 0
    if (record.name.startsWith(query)) return 1
    if (record.code.startsWith(query)) return 2
    if (record.dial.startsWith(query.replace('+', ''))) return 3
    if (record.name.includes(query) || record.code.includes(query) || record.dial.includes(query)) return 4
    return 99
}

export function createCountrySearchMixin({ queryKey = 'countrySearch', debouncedQueryKey = 'countrySearchDebounced', sourceKey = 'countries', debounceMs = 100 } = {}) {
    return {
        [debouncedQueryKey]: '',
        countrySearchTimer: null,
        countrySearchCache: null,
        _countrySearchSource: null,
        _countrySearchLocale: null,
        _countrySearchIndex: null,
        _countrySearchCollator: null,
        _countrySearchInitialized: false,
        initCountrySearch() {
            if (this._countrySearchInitialized || this.__fffPickerDisposed) return
            this._countrySearchInitialized = true
            this.ensurePickerResourceScope?.().defer(() => {
                clearTimeout(this.countrySearchTimer)
                this.countrySearchTimer = null
                this.countrySearchCache?.clear()
                this._countrySearchIndex = null
            })
            this.countrySearchCache = new Map()
            this[debouncedQueryKey] = this[queryKey] ?? ''
            this.$watch(queryKey, (value) => {
                clearTimeout(this.countrySearchTimer)
                this.countrySearchTimer = setTimeout(() => {
                    this.countrySearchTimer = null
                    if (this.__fffPickerDisposed) return
                    this[debouncedQueryKey] = value
                    this.resetVirtualListScroll?.()
                    this.resetCountryListKeyboard?.()
                }, debounceMs)
            })
        },
        filteredCountries() {
            const countries = this[sourceKey] ?? []
            const locale = this.locale ?? undefined
            this.countrySearchCache ??= new Map()
            if (countries !== this._countrySearchSource || locale !== this._countrySearchLocale) {
                this.countrySearchCache.clear()
                this._countrySearchSource = countries
                this._countrySearchLocale = locale
                this._countrySearchIndex = countries.map((country) => ({
                    country, code: String(country.code ?? '').toLowerCase(),
                    name: normalizeSearchQuery(country.name), dial: String(country.dial_code ?? '').replace('+', ''),
                }))
                try { this._countrySearchCollator = new Intl.Collator(locale) }
                catch { this._countrySearchCollator = new Intl.Collator() }
            }
            const query = normalizeSearchQuery(this[debouncedQueryKey])
            if (!query) return countries
            const cached = this.countrySearchCache.get(query)
            if (cached) { this.countrySearchCache.delete(query); this.countrySearchCache.set(query, cached); return cached }
            const results = this._countrySearchIndex.map((record) => ({ record, rank: rank(record, query) }))
                .filter((entry) => entry.rank < 99)
                .sort((a, b) => a.rank - b.rank || this._countrySearchCollator.compare(a.record.country.name, b.record.country.name))
                .map((entry) => entry.record.country)
            this.countrySearchCache.set(query, results)
            while (this.countrySearchCache.size > 48) this.countrySearchCache.delete(this.countrySearchCache.keys().next().value)
            return results
        },
    }
}
