import { createPickerLifetimeMixin } from '../overlay/picker-lifetime.js'
import { createAddressState } from '../location/editing/address.js'

/** Public address owner; the independent location Select child owns all search effects. */
export default function addressAutocompleteFormComponent(configuration) {
    return {
        ...createPickerLifetimeMixin(), ...configuration, ...createAddressState(),
        selectionError:null, geocodeError:null, tokenError:null,
        init() {
            this._pickerHostElement ??= this.$el
            if (!this.beginPickerInitialization()) return
            this.syncAddressLabel()
            if (!this.geocodeSearchUrl && !this.accessToken && this.searchable) this.tokenError = this.labels.missingToken
            this.$watch('state',() => {if (!this.__fffPickerDisposed) this.syncAddressLabel()})
        },
        destroy() {this.disposePickerResources()},
    }
}
