import { resolveCountriesFromRegistry } from '../../catalogs/country-registry.js'

/** One catalog flight per field/revision; no polling timers or shared cache reset. */
export function createCountryCatalogMixin() {
    let indexedSource = null
    let index = new Map()
    return {
        _countryCatalogRevision: 0,
        _countryCatalogFlight: null,
        countryCatalogError: null,
        bindCountryCatalog() {
            const scope = this.ensurePickerResourceScope()
            scope.listen(document, 'livewire:navigated', () => this.invalidateCountryCatalog())
            scope.defer(() => {
                this._countryCatalogRevision++
                this._countryCatalogFlight = null
                indexedSource = null
                index.clear()
            })
        },
        invalidateCountryCatalog() {
            this._countryCatalogRevision++
            this._countryCatalogFlight = null
            this.countries = []
            this.countriesLoaded = false
            this.countriesLoading = false
            this.cancelCountryMenuIntent?.()
        },
        findCountry(code) {
            if (indexedSource !== this.countries) {
                indexedSource = this.countries
                index = new Map(this.countries.map(country => [country.code, country]))
            }
            return index.get(code) ?? null
        },
        ensureCountriesLoaded() {
            if (this.__fffPickerDisposed || this.countriesLoaded) return Promise.resolve(this.countries)
            if (this._countryCatalogFlight) return this._countryCatalogFlight
            const revision = this._countryCatalogRevision
            this.countriesLoading = true
            this.countryCatalogError = null
            const flight = resolveCountriesFromRegistry({
                pool: this.countryPool,
                countryFilterKey: this.countryFilterKey,
                preferredCountryCode: this.preferredCountryCode,
                sortPreferredFirst: this.sortPreferredFirst,
                locale: this.locale,
            }).then(countries => {
                if (!this.__fffPickerDisposed && revision === this._countryCatalogRevision) {
                    this.countries = countries
                    // Empty pending templates are retryable on the next open.
                    this.countriesLoaded = countries.length > 0
                }
                return countries
            }).catch(() => {
                if (!this.__fffPickerDisposed && revision === this._countryCatalogRevision) this.countryCatalogError = 'catalog-unavailable'
                return []
            }).finally(() => {
                if (revision === this._countryCatalogRevision) {
                    this._countryCatalogFlight = null
                    this.countriesLoading = false
                }
            })
            this._countryCatalogFlight = flight
            return flight
        },
        preloadSelectedCountryFlag() {
            const url = this.selectedCountry?.flag_url
            if (!url || this.__fffPickerDisposed || url === this._lastPreloadedCountryFlag) return
            this._lastPreloadedCountryFlag = url
            const image = new Image()
            image.src = url
        },
    }
}
