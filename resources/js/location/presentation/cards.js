import { createFeatureDisclosure } from './disclosure.js'
import { runtime } from '../runtime/resources.js'
import { zoneAnchor } from './anchors.js'

function textNode(tag, className, text) {
    const element = document.createElement(tag)
    element.className = className
    element.textContent = String(text)
    return element
}

/** Safe plain-text cards. A single popup belongs to the map runtime and is reused/replaced. */
export function createLocationCards() {
    return {
        featureDisclosure(id) { return createFeatureDisclosure(id) },
        legendFeatures() {
            return this.mapConfiguration.selectionMode === 'point' ? this.mapConfiguration.features.features
                : this.geometryFeatures().length ? this.geometryFeatures() : this.mapConfiguration.features.features
        },
        selectLegendFeature(feature) {
            if (this.mapConfiguration.selectionMode !== 'point' && this.geometryFeatures().some(item => String(item.id) === String(feature.id))) return this.selectMapFeature(feature.id)
            const coordinate = feature.geometry.type === 'Point' ? feature.geometry.coordinates : feature.geometry.type === 'Polygon' ? feature.geometry.coordinates[0][0] : feature.geometry.coordinates[0]
            if (this.mapConfiguration.featurePopups !== false) this.showFeaturePopup(feature, coordinate)
            else this.showLegendTooltip(feature)
        },
        showLegendTooltip(feature) {
            if (this.mapConfiguration.featurePopups !== false) return
            const coordinate = feature.geometry.type === 'Polygon'
                ? zoneAnchor(feature.geometry.coordinates[0])
                : feature.geometry.type === 'Point' ? feature.geometry.coordinates : feature.geometry.coordinates[0]
            this.showFeatureTooltip(feature, coordinate)
        },
        toggleMapFeature(id) {
            if (String(this.selectedFeatureId) !== String(id)) return this.selectMapFeature(id)
            this.selectedFeatureId = null
            this.closeFeaturePopup()
            this.refreshEditingHandles()
            this.syncSelectedMarker()
        },
        selectMapFeature(id) {
            const feature = this.geometryFeatures().find(feature => String(feature.id) === String(id))
            if (!feature) return
            if (String(this.selectedFeatureId) === String(feature.id)) return
            this.selectedFeatureId = String(feature.id)
            this.refreshEditingHandles(); this.syncSelectedMarker()
            if (feature.geometry.type === 'Point' && (!this.mapConfiguration.cluster || runtime(this).marker?.getElement().style.visibility === 'visible')) this.showFeaturePopup(feature, feature.geometry.coordinates)
        },
        closeFeaturePopup({ restoreFocus = false, clearSelection = true } = {}) {
            const r = runtime(this)
            this.closeFeatureTooltip?.()
            if(clearSelection && this.experienceSelection){this.experienceSelection=null;r.experience?.selected(null,null)}
            const previousFocus = r.popupPreviousFocus
            r.popup?.remove(); r.popup = null; r.popupCard = null; r.popupFeatureId = null; r.popupPreviousFocus = null
            if (restoreFocus && previousFocus?.isConnected) previousFocus.focus({ preventScroll: true })
        },
        showFeaturePopup(feature, coordinate) {
            if (!this.mapConfiguration.featurePopups || feature.properties?.clickable === false || this.__fffPickerDisposed) return
            const r = runtime(this)
            if (!r.map || !r.sdk) return
            this.closeFeaturePopup({clearSelection:false})
            this.closeFeatureTooltip()
            r.popupPreviousFocus = document.activeElement
            const properties = feature.properties ?? {}
            const card = document.createElement('section'); card.className = 'fff-location__popup'
            const heading = String(properties.label || feature.id || this.labels.selectedLocation)
            card.setAttribute('aria-label', heading)
            const close = document.createElement('button'); close.type = 'button'; close.className = 'fff-location__popup-close'
            close.setAttribute('aria-label', this.labels.close); close.title = this.labels.close
            const closeIcon = this.$refs?.popupCloseIcon?.content?.firstElementChild
            if (closeIcon) close.append(closeIcon.cloneNode(true))
            else close.textContent = '×'
            close.addEventListener('click', event => { event.stopPropagation(); this.closeFeaturePopup({ restoreFocus: true }) })
            card.append(close, textNode('h3', 'fff-location__popup-title', heading))
            for (const key of ['description', 'detail', 'value']) {
                if (properties[key]) card.append(textNode('p', `fff-location__popup-${key}`, properties[key]))
            }
            card.addEventListener('pointerdown', event => event.stopPropagation())
            const popup = new r.sdk.Popup({ closeButton: false, closeOnClick: false, focusAfterOpen: false, maxWidth: '320px', offset: this.mapConfiguration.compactControls ? (properties.value ? 22 : 18) : 24, className: 'fff-location-popup' })
                .setLngLat(coordinate).setDOMContent(card).addTo(r.map)
            r.popup = popup; r.popupCard = card; r.popupFeatureId = String(feature.id)
            popup.on('close', () => { if (r.popup === popup) { r.popup = null; r.popupCard = null; r.popupFeatureId = null; r.popupPreviousFocus = null } })
        },
        refreshFeaturePopup() {
            const r = runtime(this)
            if (!r.popup || !r.popupCard) return
            const feature = this.geometryFeatures().find(item => String(item.id) === r.popupFeatureId)
            if (!feature || feature.properties?.clickable === false) { this.closeFeaturePopup(); return }
            const heading = String(feature.properties?.label || feature.id)
            r.popupCard.setAttribute('aria-label', heading)
            r.popupCard.querySelector('.fff-location__popup-title').textContent = heading
            for (const key of ['description', 'detail', 'value']) {
                const text = feature.properties?.[key], node = r.popupCard.querySelector(`.fff-location__popup-${key}`)
                if (!text) node?.remove()
                else if (node) { node.textContent = text; r.popupCard.append(node) }
                else r.popupCard.append(textNode('p', `fff-location__popup-${key}`, text))
            }
        },
        closeFeatureTooltip() {
            const r = runtime(this)
            r.tooltip?.remove(); r.tooltip = null; r.tooltipIdentity = null
        },
        showFeatureTooltip(feature, coordinate) {
            const r = runtime(this)
            if (!this.mapConfiguration.featureTooltips || this.__fffPickerDisposed || !r.map || !r.sdk || this.drawingTool || r.popup) return
            const identity = `${feature.id}:${JSON.stringify(coordinate)}`
            if (r.tooltipIdentity === identity) return
            this.closeFeatureTooltip()
            const content = textNode('div', 'fff-location__tooltip', feature.properties?.tooltip || [feature.properties?.label || feature.id, feature.properties?.metric].filter(Boolean).join(' · '))
            content.setAttribute('role', 'tooltip')
            r.tooltip = new r.sdk.Popup({ closeButton: false, closeOnClick: false, focusAfterOpen: false, offset: 16, maxWidth: '240px', className: 'fff-location-tooltip' })
                .setLngLat(coordinate).setDOMContent(content).addTo(r.map)
            r.tooltipIdentity = identity
        },
        onMapHover(event) {
            this.previewDrawing([event.lngLat.lng, event.lngLat.lat])
            const r = runtime(this)
            if (!r.map || this.drawingTool || r.editing || r.popup) { this.closeFeatureTooltip(); return }
            const layers = ['fff-state-points', 'fff-overlays-points', 'fff-zone-points', 'fff-state-fill', 'fff-state-line', 'fff-overlays-fill', 'fff-overlays-line'].filter(id => r.map.getLayer(id))
            const hit = layers.length ? r.map.queryRenderedFeatures(event.point, { layers })[0] : null
            r.map.getCanvas().style.cursor = hit ? 'pointer' : ''
            if (!hit) { this.closeFeatureTooltip(); return }
            const owner = hit.properties?.__fffOwner ?? (hit.source === 'fff-state' ? 'state' : 'overlay')
            const collection = owner === 'state' ? this.geometryFeatures() : this.mapConfiguration.features.features
            const feature = collection.find(item => String(item.id) === String(hit.properties?.__fffIdentity ?? hit.id))
            if (feature && hit.geometry.type === 'Point') this.showFeatureTooltip(feature, hit.geometry.coordinates)
            else this.closeFeatureTooltip()
        },
    }
}
