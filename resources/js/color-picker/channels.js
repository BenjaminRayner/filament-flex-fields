import { clamp } from './value.js'
import { colorRuntime, cancelColorDrag } from './lifetime.js'
export function createColorChannels() {
    return {
        startSaturationDrag(event) {
            if (this.readOnly || event.button !== 0 || event.isPrimary === false) return
            cancelColorDrag(this)
            const area = this.$refs.saturationArea, r = colorRuntime(this)
            r.drag = { id: event.pointerId, rect: area.getBoundingClientRect(), before: { ...this.hsva } }
            area.focus({ preventScroll: true }); area.setPointerCapture(event.pointerId); this.moveSaturation(event)
        },
        moveSaturation(event) {
            const r = colorRuntime(this), drag = r.drag
            if (!drag || event.pointerId !== drag.id) return
            const { width, height, left, top } = drag.rect
            if (!width || !height) return
            r.pendingChannels = { s: clamp((event.clientX - left) / width * 100, 0, 100), v: 100 - clamp((event.clientY - top) / height * 100, 0, 100) }
            if (r.frame != null) return
            r.frame = requestAnimationFrame(() => { r.frame = null; if (r.drag && !r.disposed) { this.hsva = { ...this.hsva, ...r.pendingChannels }; this.syncInputs() } })
        },
        endSaturation(event, cancelled = false) {
            const r = colorRuntime(this)
            if (!r.drag || event.pointerId !== r.drag.id) return
            const before = r.drag.before
            this.hsva = cancelled ? before : { ...this.hsva, ...r.pendingChannels }
            cancelColorDrag(this); this.syncInputs()
            if (!cancelled) this.commitState()
        },
        onSaturationKey(event) {
            if (this.readOnly) return
            const key = event.key, step = event.shiftKey ? 10 : 1
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(key)) return
            event.preventDefault()
            this.hsva = { ...this.hsva, s: clamp(this.hsva.s + (key === 'ArrowRight' ? step : key === 'ArrowLeft' ? -step : 0), 0, 100), v: clamp(this.hsva.v + (key === 'ArrowUp' ? step : key === 'ArrowDown' ? -step : 0), 0, 100) }
            if (key === 'Home' || key === 'End') this.hsva.s = key === 'Home' ? 0 : 100
            this.syncInputs(); this.commitState()
        },
        setChannel(channel, value, commit = false) {
            if (this.readOnly || channel === 'a' && !this.alphaEnabled) return
            const number = Number(value)
            if (!Number.isFinite(number)) return
            this.hsva = { ...this.hsva, [channel]: clamp(number, 0, channel === 'h' ? 360 : channel === 'a' ? 1 : 100) }
            this.syncInputs(); if (commit) this.commitState()
        },
    }
}
