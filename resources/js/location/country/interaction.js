import { WORLD_WIDTH, WORLD_HEIGHT, cameraBounds, zoomCamera, cameraViewBox } from './camera.js'

function pointInWorld(world, clientX, clientY, matrix = world?.getScreenCTM()?.inverse()) {
    if (!matrix || !Number.isFinite(clientX) || !Number.isFinite(clientY)) return null
    const point = new DOMPoint(clientX, clientY).matrixTransform(matrix)
    return [point.x, point.y]
}

/** Camera lifetime is owned by this factory, independently of Alpine's merged scope. */
export function createCountryInteraction(configuration, { onDispose = () => {} } = {}) {
    const session = { frame: null, target: null, pan: null, disposed: false }
    function cancelMotion() {
        if (session.frame !== null) cancelAnimationFrame(session.frame)
        session.frame = null
        session.target = null
    }
    function releasePan(world) {
        const id = session.pan?.id
        session.pan = null
        if (id !== undefined && world?.hasPointerCapture?.(id)) world.releasePointerCapture(id)
    }
    return {
        zoom: configuration.minZoom,
        centerX: WORLD_WIDTH / 2,
        centerY: WORLD_HEIGHT / 2,
        dragged: false,
        panning: false,
        tooltip: null,
        get viewBox() { return cameraViewBox({ zoom: this.zoom, x: this.centerX, y: this.centerY }) },
        get tooltipStyle() { return this.tooltip ? { left: `${this.tooltip.x}px`, top: `${this.tooltip.y}px` } : {} },
        setCamera(camera) {
            if (session.disposed) return
            const next = cameraBounds(camera, configuration)
            this.zoom = next.zoom
            this.centerX = next.x
            this.centerY = next.y
        },
        stopCamera() { cancelMotion() },
        animateCamera(target) {
            if (session.disposed) return
            cancelMotion()
            this.tooltip = null
            const next = cameraBounds(target, configuration)
            const from = { zoom: this.zoom, x: this.centerX, y: this.centerY }
            const duration = matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : configuration.animationDuration
            if (!duration) { this.setCamera(next); return }
            session.target = next
            const started = performance.now()
            const advance = now => {
                if (session.disposed) return
                const progress = Math.min(1, Math.max(0, (now - started) / duration))
                const eased = 1 - (1 - progress) ** 3
                this.setCamera({ zoom: 1 / (1 / from.zoom + (1 / next.zoom - 1 / from.zoom) * eased),
                    x: from.x + (next.x - from.x) * eased, y: from.y + (next.y - from.y) * eased })
                session.frame = progress < 1 ? requestAnimationFrame(advance) : null
                if (progress === 1) session.target = null
            }
            session.frame = requestAnimationFrame(advance)
        },
        zoomBy(delta, anchor = [this.centerX, this.centerY]) {
            if (session.disposed || !Number.isFinite(delta)) return
            this.animateCamera(zoomCamera({ zoom: this.zoom, x: this.centerX, y: this.centerY },
                (session.target?.zoom ?? this.zoom) * 2 ** delta, anchor, configuration))
        },
        resetView() { this.animateCamera({ zoom: configuration.minZoom, x: WORLD_WIDTH / 2, y: WORLD_HEIGHT / 2 }) },
        onWorldWheel(event) {
            if (session.disposed || !configuration.scrollZoom && !event.ctrlKey && !event.metaKey || event.deltaY === 0) return
            const anchor = pointInWorld(this.$refs.world, event.clientX, event.clientY)
            if (!anchor) return
            event.preventDefault()
            this.zoomBy(-Math.sign(event.deltaY) * .25, anchor)
        },
        beginPan(event) {
            this.dragged = false
            this.tooltip = null
            if (session.disposed || this.zoom <= 1 || event.button !== 0 || event.isPrimary === false) return
            cancelMotion()
            releasePan(this.$refs?.world)
            const matrix = this.$refs.world.getScreenCTM()?.inverse()
            const start = pointInWorld(this.$refs.world, event.clientX, event.clientY, matrix)
            if (!start) return
            session.pan = { start, matrix, x: this.centerX, y: this.centerY,
                clientX: event.clientX, clientY: event.clientY, id: event.pointerId }
        },
        movePan(event) {
            if (session.disposed) return
            const pan = session.pan
            if (!pan || event.pointerId !== pan.id) { this.showCountryTooltip(event); return }
            if (!this.dragged && Math.hypot(event.clientX - pan.clientX, event.clientY - pan.clientY) <= 5) return
            const point = pointInWorld(this.$refs.world, event.clientX, event.clientY, pan.matrix)
            if (!point) return
            if (!this.dragged) {
                this.dragged = true
                this.panning = true
                this.$refs.world.setPointerCapture(event.pointerId)
            }
            this.setCamera({ zoom: this.zoom, x: pan.x + pan.start[0] - point[0], y: pan.y + pan.start[1] - point[1] })
        },
        endPan(event) {
            if (event && session.pan && event.pointerId !== session.pan.id) return
            releasePan(this.$refs?.world)
            this.panning = false
        },
        showCountryTooltip(event) {
            if (session.disposed || !configuration.tooltips || this.panning) return
            const path = event.target?.closest('[data-country]')
            if (!path) { this.tooltip = null; return }
            const surface = this.$refs.world.parentElement.getBoundingClientRect()
            const rect = path.getBoundingClientRect()
            const x = Number.isFinite(event.clientX) ? event.clientX : rect.x + rect.width / 2
            const y = Number.isFinite(event.clientY) ? event.clientY : rect.y + rect.height / 2
            this.tooltip = { name: path.getAttribute('aria-label'), code: path.dataset.country,
                x: Math.max(80, Math.min(surface.width - 80, x - surface.left)),
                y: Math.max(40, Math.min(surface.height - 8, y - surface.top - 12)) }
        },
        onWorldKey(event) {
            if (session.disposed) return
            if (event.key === 'Escape') { this.tooltip = null; return }
            if (event.key === '+' || event.key === '=') { event.preventDefault(); this.zoomBy(.5) }
            else if (event.key === '-') { event.preventDefault(); this.zoomBy(-.5) }
            else if (event.key === '0') { event.preventDefault(); this.resetView() }
        },
        destroy() {
            if (session.disposed) return
            session.disposed = true
            cancelMotion()
            releasePan(this.$refs?.world)
            this.panning = false
            this.tooltip = null
            onDispose()
        },
    }
}
