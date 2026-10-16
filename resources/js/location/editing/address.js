import { emptyMapCanonical, parseGeocodeFeature, hasStreetNumber, hasStreetAddress } from '../../support/mapbox-geocoding.js'

/** Compound address commands never own a query, menu, transport or popup. */
export function createAddressState() {
    return {
        selectedLabel:'',
        syncAddressLabel() {
            const parts = [this.state?.place_name,this.state?.street,this.state?.city,this.state?.country_name ?? this.state?.country]
            this.selectedLabel = String(parts.find(value => value != null && String(value).trim() !== '') ?? '')
        },
        selectSearchResult(result) {
            if (this.readOnly || this.__fffPickerDisposed || !result?.feature) return false
            const parsed = parseGeocodeFeature(result.feature)
            if (this.requireStreetNumber && !hasStreetNumber(parsed)) { this.selectionError = this.labels.streetNumberRequired; return false }
            if (this.streetAddressesOnly && !hasStreetAddress(parsed)) { this.selectionError = this.labels.streetAddressRequired; return false }
            if (Array.isArray(this.countries) && !this.countries.includes(parsed.country)) { this.selectionError = this.labels.outsideArea; return false }
            this.selectionError = null
            this.state = {...emptyMapCanonical(),...parsed}
            this.syncAddressLabel()
            return true
        },
        clearAddress() {
            if (this.readOnly || this.__fffPickerDisposed) return
            this.state = emptyMapCanonical()
            this.selectionError = null; this.geocodeError = null
            this.syncAddressLabel()
        },
        updateAddressDetail(key, raw) {
            if (this.readOnly || this.__fffPickerDisposed || !this.editableAddress || !this.editableFields.includes(key)) return
            let value = String(raw).trim().slice(0,key === 'country' ? 2 : 2048)
            if (key === 'country') value = value.toUpperCase()
            const next = {...emptyMapCanonical(),...this.state,[key]:value || null,lat:null,lng:null,
                ...(key === 'country' ? {country_name:null} : {})}
            next.place_name = [next.street,next.postcode,next.city,next.country_name ?? next.country]
                .filter(value => value != null && String(value).trim() !== '').join(', ') || null
            this.state = next; this.selectionError = null
            this.syncAddressLabel()
        },
    }
}
