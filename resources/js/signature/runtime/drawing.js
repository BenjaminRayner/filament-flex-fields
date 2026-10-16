import { inkPath } from '../ink/outline.js'
import { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor } from '../geometry.js'

export function createSignatureDrawing(owner) {
    return {
        resolvePointerPressure(event) {
            return event.pointerType === 'pen' && Number.isFinite(event.pressure) && event.pressure > 0
                ? Math.max(.05, Math.min(1, event.pressure)) : -1
        },
        normalizePoint(event, canvas) {
            const rect = canvas.getBoundingClientRect()

            if (rect.width === 0 || rect.height === 0) {
                return null
            }

            const scale = Math.min(rect.width / this.viewBoxWidth, rect.height / this.viewBoxHeight)
            const offsetX = (rect.width - this.viewBoxWidth * scale) / 2
            const offsetY = (rect.height - this.viewBoxHeight * scale) / 2
            const x = (event.clientX - rect.left - offsetX) / scale
            const y = (event.clientY - rect.top - offsetY) / scale
            const pressure = this.resolvePointerPressure(event)
            const width = this.penWidth

            return {
                x: Math.max(0, Math.min(this.viewBoxWidth, x)),
                y: Math.max(0, Math.min(this.viewBoxHeight, y)),
                width, p: pressure, t: Number.isFinite(event.timeStamp) ? event.timeStamp : performance.now(),
            }
        },
        onPointerDown(event) {
            if (this.readOnly || owner.disposed) {
                return
            }

            this.stopReplay(); this.stopWipe(); this.historyBase = [...this.strokes]
            this.engageGlide()

            if (this.isGlidePointerId(this.activePointerId)) {
                this.finishGlideStroke()
            }

            if (this.activePointerId !== null) {
                return
            }

            const canvas = this.activeCanvas

            if (! canvas) {
                return
            }

            const point = this.normalizePoint(event, canvas)

            if (! point) {
                return
            }

            event.preventDefault()
            this.glideAnchor = null
            canvas.setPointerCapture(event.pointerId)
            this.activePointerId = event.pointerId
            this.isDrawing = true
            const startedAt = point.t
            point.t = 0
            this.currentStroke = {
                ink: true, smoothing: this.smoothingEnabled, startedAt,
                points: [point],
                width: this.penWidth,
                color: this.penColor,
            }
            this.scheduleRedraw()
        },
        onPointerMove(event) {
            const canvas = this.activeCanvas

            if (! canvas) {
                return
            }

            if (this.shouldUseTrackpadGlide(event)) {
                this.handleTrackpadGlideMove(event, canvas)

                return
            }

            if (! this.isDrawing || event.pointerId !== this.activePointerId || ! this.currentStroke) {
                return
            }

            event.preventDefault()
            const samples = event.getCoalescedEvents?.()
            for (const sample of (samples?.length ? samples : [event]).slice(-64)) {
                const point = this.normalizePoint(sample, canvas)
                if (point) this.appendPointToCurrentStroke(point)
            }
        },
        onPointerUp(event) {
            if (this.isGlidePointerId(this.activePointerId)) {
                return
            }

            if (event.pointerId !== this.activePointerId) {
                return
            }

            const point = this.normalizePoint(event, this.activeCanvas)
            if (point) this.appendPointToCurrentStroke(point)
            this.finishStroke(event)
        },
        onPointerLeave(event) {
            if (this.isGlidePointerId(this.activePointerId)) {
                this.finishGlideStroke(false)

                return
            }

            if (this.trackpadGlideEnabled && ! this.isDrawing) {
                this.glideAnchor = null
            }

            if (event.pointerId !== this.activePointerId) {
                return
            }

            if (!this.activeCanvas?.hasPointerCapture?.(event.pointerId)) this.finishStroke(event)
        },
        onPointerCancel(event) {
            if (this.isGlidePointerId(this.activePointerId)) {
                this.finishGlideStroke(false)

                return
            }

            if (event.pointerId !== this.activePointerId) {
                return
            }

            this.finishStroke(event, true)
        },
        finishStroke(event, cancelled = false) {
            const canvas = this.activeCanvas
            const pointerId = event?.pointerId

            if (canvas && pointerId !== undefined && ! this.isGlidePointerId(pointerId) && canvas.hasPointerCapture?.(pointerId)) {
                canvas.releasePointerCapture(pointerId)
            }

            this.clearGlideIdleTimer()
            this.glideAnchor = null
            this.activePointerId = null
            this.isDrawing = false

            if (! cancelled && this.currentStroke && this.currentStroke.points.length > 0) {
                if (this.currentStroke.ink) this.currentStroke.path = inkPath(this.currentStroke)
                else this.currentStroke.points = polishStrokePoints(this.currentStroke.points, this.smoothingEnabled)
                this.rememberHistory(this.historyBase ?? this.strokes)
                this.strokes.push(this.currentStroke)
                this.commitState()
            }

            this.currentStroke = null
            this.historyBase = null
            this.scheduleRedraw()
        },
    }
}
