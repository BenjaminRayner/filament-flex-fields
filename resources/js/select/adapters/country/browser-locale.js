/** Region first, then the configured language mapping, always within the catalog. */
export function matchLocaleCountry(locale, allowed, languageCountryMap) {
    const region = String(locale).match(/[-_]([A-Za-z]{2})(?:$|-u-)/)?.[1]?.toUpperCase()
    if (region && allowed.has(region)) return region
    const mapped = languageCountryMap?.[String(locale).split(/[-_]/)[0].toLowerCase()]
    return mapped && allowed.has(mapped) ? mapped : null
}

export function createBrowserCountryMixin() {
    return {
        async bootstrapBrowserLocaleDefault() {
            if (!this.browserLocaleDefault || this.isLocked || this.state || this.initialState) return
            await this.ensureCountriesLoaded()
            this.applyBrowserLocaleDefault()
        },
        applyBrowserLocaleDefault() {
            if (this.__fffPickerDisposed || !this.browserLocaleDefault || this.isLocked || this.state || this.initialState) return
            const code = this.detectBrowserCountry()
            if (code) this.state = code
        },
        detectBrowserCountry() {
            const allowed = new Set(this.countries.map(country => country.code))
            const languages = navigator.languages?.length ? navigator.languages : [navigator.language].filter(Boolean)
            for (const locale of languages) {
                const code = this.matchLocaleToCountry(locale, allowed)
                if (code) return code
            }
            return null
        },
        matchLocaleToCountry(locale, allowed) { return matchLocaleCountry(locale, allowed, this.languageCountryMap) },
    }
}
