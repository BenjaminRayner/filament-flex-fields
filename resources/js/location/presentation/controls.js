import { runtime } from '../runtime/resources.js'
export function createMapControls() {
    return {
        zoomMap(delta) { const r=runtime(this);r.experience?.detach();const map = r.map; if (map) map.easeTo({ zoom: Math.min(this.mapConfiguration.maxZoom, Math.max(this.mapConfiguration.minZoom, map.getZoom() + delta)), duration: this.motionDuration() }) },
        resetMapBearing() { runtime(this).experience?.detach();runtime(this).map?.easeTo({ bearing: 0, pitch: 0, duration: this.motionDuration() }) },
        async toggleMapFullscreen() {
            const surface = this.$refs.surface
            try { if (document.fullscreenElement === surface) await document.exitFullscreen(); else await surface?.requestFullscreen?.() }
            catch { this.selectionError = this.labels.loadFailed }
        },
    }
}
