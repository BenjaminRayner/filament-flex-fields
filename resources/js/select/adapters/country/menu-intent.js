import { createPendingPickerOpenMixin } from '../../../overlay/pending-open-intent.js'

/** Country and Phone retain their public methods on the shared pending-open core. */
export function createCountryMenuIntentMixin({openKey = 'menuOpen', ownerIdPrefix = 'fff-country-field', warmPhone = false} = {}) {
    return {
        ...createPendingPickerOpenMixin(),
        bindCountryMenuIntent() { this.bindPendingPickerOpen() },
        cancelCountryMenuIntent() { this.cancelPendingPickerOpen() },
        toggleCountryPickerMenu() {
            return this.runPendingPickerOpen({openKey, ownerIdPrefix,
                prepare: () => Promise.all([this.ensureCountriesLoaded(), warmPhone ? this.ensurePhoneLibLoaded() : Promise.resolve()]),
                commit: () => {
                    this[openKey] = true
                    if (this.searchable) this.focusPendingPickerSearch(openKey, 'countrySearch')
                },
            })
        },
    }
}
