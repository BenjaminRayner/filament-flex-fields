import { runtime } from '../runtime/resources.js'
import { emptyCollection, pointFeature } from '../geometry/collection.js'
import { validPoint, allowedPoint, roundPoint, wrapLongitude } from '../geometry/coordinates.js'
import { emptyMapCanonical, hasCoordinates, hasStreetNumber, hasStreetAddress, parseGeocodeFeature, reverseGeocodeMapbox, searchMapboxPlaces } from '../../support/mapbox-geocoding.js'

export function createLocationSelection() {
    return {
        selectionBusy: false, locating: false, coordinateLat: '', coordinateLng: '',
        newFeatureId() { return typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : `location-${Date.now()}-${Math.random().toString(36).slice(2)}` },
        invalidateLocationIntent() {
            const r = runtime(this); r.gpsEpoch = (r.gpsEpoch ?? 0) + 1; this.locating = false
            r.requests?.invalidate(); this.selectionBusy = false
        },
        publishPoint(state) {
            const r = runtime(this); r.ownState = JSON.stringify(state); this.state = state; this.syncLocationSummary(); this.refreshMapLayers()
        },
        async selectCoordinates(lng, lat, animate = true) {
            if (this.readOnly || this.__fffPickerDisposed) return
            this.invalidateLocationIntent()
            const point = roundPoint([wrapLongitude(lng), lat])
            if (!allowedPoint(point, this.mapConfiguration)) { this.selectionError = this.labels.outsideArea; this.syncSelectedMarker(); return }
            if (['markers', 'features'].includes(this.mapConfiguration.selectionMode)) {
                const id = this.newFeatureId()
                if (this.commitCollection({ type: 'FeatureCollection', features: [...this.geometryFeatures(), pointFeature(point, id, { label: `${this.labels.marker} ${this.geometryFeatures().length + 1}`, color: this.mapConfiguration.color })] })) { this.selectedFeatureId = id; this.syncSelectedMarker(); if (animate) this.focusLocation(point) }
                return
            }
            if (this.mapConfiguration.selectionMode !== 'point') return
            const r = runtime(this), lease = r.requests.begin('selection')
            if (!lease) return
            const snapshot = JSON.stringify(this.state); this.selectionBusy = true; this.selectionError = null; this.geocodeError = null
            try {
                let parsed = { lat: point[1], lng: point[0] }
                if (this.mapConfiguration.reverseGeocoding && this.canGeocode()) {
                    const reverse = await reverseGeocodeMapbox({ lng: point[0], lat: point[1], signal: lease.signal, accessToken: this.accessToken, geocodeReverseUrl: this.geocodeReverseUrl, countries: this.countries, language: this.language, streetAddressesOnly: this.streetAddressesOnly, requireStreetNumber: this.requireStreetNumber, types: this.searchTypes })
                    if (reverse) parsed = { ...reverse, lat: point[1], lng: point[0] }
                }
                if (!lease.current() || this.__fffPickerDisposed || this.readOnly || JSON.stringify(this.state) !== snapshot) return
                if (this.requireStreetNumber && !hasStreetNumber(parsed)) { this.selectionError = this.labels.streetNumberRequired; this.syncSelectedMarker(); return }
            if (this.streetAddressesOnly && !hasStreetAddress(parsed)) { this.selectionError = this.labels.streetAddressRequired; this.syncSelectedMarker(); return }
                if (Array.isArray(this.countries) && !this.countries.includes(parsed.country)) { this.selectionError = this.labels.outsideArea; this.syncSelectedMarker(); return }
                this.publishPoint({ ...emptyMapCanonical(), ...parsed })
                if (animate) this.focusLocation(point)
            } catch (error) {
                if (!lease.current() || this.__fffPickerDisposed || error?.name === 'AbortError') return
                this.geocodeError = this.labels.geocodeFailed
                if (!this.streetAddressesOnly && !Array.isArray(this.countries)) { this.publishPoint({ ...emptyMapCanonical(), lat: point[1], lng: point[0] }); if (animate) this.focusLocation(point) }
                else this.syncSelectedMarker()
            } finally { if (lease.current()) this.selectionBusy = false; lease.finish() }
        },
        selectSearchResult(result) {
            if (this.readOnly || this.__fffPickerDisposed || !result?.feature || this.mapConfiguration.selectionMode === 'zones') return false
            const parsed = parseGeocodeFeature(result.feature), point = [parsed.lng, parsed.lat]
            if (!allowedPoint(point, this.mapConfiguration)) { this.selectionError = this.labels.outsideArea; return false }
            if (this.requireStreetNumber && !hasStreetNumber(parsed)) { this.selectionError = this.labels.streetNumberRequired; return false }
            if (this.streetAddressesOnly && !hasStreetAddress(parsed)) { this.selectionError = this.labels.streetAddressRequired; return false }
            if (Array.isArray(this.countries) && !this.countries.includes(parsed.country)) { this.selectionError = this.labels.outsideArea; return false }
            this.invalidateLocationIntent(); this.selectionError = null
            if (['markers', 'features'].includes(this.mapConfiguration.selectionMode)) {
                const id = this.newFeatureId()
                if (!this.commitCollection({ type: 'FeatureCollection', features: [...this.geometryFeatures(), pointFeature(point, id, { label: parsed.place_name ?? result.label, color: this.mapConfiguration.color })] })) return
                this.selectedFeatureId = id
            } else this.publishPoint({ ...emptyMapCanonical(), ...parsed })
            this.syncSelectedMarker(); this.focusLocation(point)
            return true
        },
        moveMarkerFeature(id, point) {
            if (this.readOnly || !this.mapConfiguration.draggable) return
            this.invalidateLocationIntent()
            point = roundPoint([wrapLongitude(point[0]), point[1]])
            if (!this.commitCollection({ type: 'FeatureCollection', features: this.geometryFeatures().map(feature => String(feature.id) === String(id) ? { ...feature, geometry: { type: 'Point', coordinates: point } } : feature) })) this.syncSelectedMarker()
        },
        clearLocation() {
            if (this.readOnly || !this.mapConfiguration.clearable) return
            if (this.mapConfiguration.selectionMode !== 'point' && !this.mapConfiguration.deletableFeatures) return
            this.invalidateLocationIntent(); this.cancelDrawing()
            this.mapConfiguration.selectionMode === 'point' ? this.publishPoint(emptyMapCanonical()) : this.commitCollection(emptyCollection())
            this.selectedFeatureId = null; this.selectedLabel = ''; this.selectionError = null; this.geocodeError = null
            this.refreshEditingHandles()
        },
        applyCoordinates() {
            if (String(this.coordinateLat).trim() === '' || String(this.coordinateLng).trim() === '') { this.selectionError = this.labels.coordinatePair; return }
            const point = [Number(this.coordinateLng), Number(this.coordinateLat)]
            if (!validPoint(point)) { this.selectionError = this.labels.outsideArea; return }
            void this.selectCoordinates(point[0], point[1])
        },
        locateUser() {
            if (this.readOnly || !this.mapConfiguration.geolocation || this.locating || this.__fffPickerDisposed) return
            if (!navigator.geolocation) { this.selectionError = this.labels.locationUnavailable; return }
            const r = runtime(this), epoch = r.gpsEpoch = (r.gpsEpoch ?? 0) + 1, snapshot = JSON.stringify(this.state)
            this.locating = true
            navigator.geolocation.getCurrentPosition(position => {
                if (this.__fffPickerDisposed || this.readOnly || r.gpsEpoch !== epoch || JSON.stringify(this.state) !== snapshot) { this.locating = false; return }
                this.locating = false
                if (this.mapConfiguration.selectionMode === 'zones') this.focusLocation([position.coords.longitude, position.coords.latitude])
                else void this.selectCoordinates(position.coords.longitude, position.coords.latitude)
            }, () => { if (!this.__fffPickerDisposed && r.gpsEpoch === epoch) { this.locating = false; this.selectionError = this.labels.locationUnavailable } }, { enableHighAccuracy: this.mapConfiguration.highAccuracy, timeout: this.mapConfiguration.geolocationTimeout, maximumAge: 30000 })
        },
        async resolveInitialLocation() {
            const query = String(this.summaryLabel ?? '').trim(), r = runtime(this)
            if (!query || !this.canGeocode() || query.length > 256 || hasCoordinates(this.state) || Array.isArray(this.countries) && this.countries.length === 0) return
            const snapshot = JSON.stringify(this.state), lease = r.requests.begin('initial')
            if (!lease) return
            try {
                const features = await searchMapboxPlaces({ query, signal: lease.signal, accessToken: this.accessToken, geocodeSearchUrl: this.geocodeSearchUrl, countries: this.countries, language: this.language, types: this.searchTypes, streetAddressesOnly: this.streetAddressesOnly, requireStreetNumber: this.requireStreetNumber, limit: 1, autocomplete: false, bounds: this.searchConfiguration?.bounds, proximity: this.searchConfiguration?.proximity })
                if (!lease.current() || this.__fffPickerDisposed || JSON.stringify(this.state) !== snapshot || !features[0]) return
                const parsed = parseGeocodeFeature(features[0])
                if (!allowedPoint([parsed.lng, parsed.lat], this.mapConfiguration) || Array.isArray(this.countries) && !this.countries.includes(parsed.country)) return
                this.publishPoint({ ...emptyMapCanonical(), ...this.state, ...parsed }); this.focusLocation([parsed.lng, parsed.lat])
            } catch (error) { if (lease.current() && !this.__fffPickerDisposed && error?.name !== 'AbortError') this.geocodeError = this.labels.geocodeFailed }
            finally { lease.finish() }
        },
    }
}
