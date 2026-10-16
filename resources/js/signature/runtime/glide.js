import { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor } from '../geometry.js'

export function createSignatureGlide(owner) {
    return {
        get showGlidePill() {
            return this.trackpadGlideEnabled && ! this.readOnly
        },
        get trackpadGlideKeyLabel() {
            return String(this.trackpadGlideKey || 'd').toUpperCase()
        },
        get glidePillText() {
            const template = this.glideArmed
                ? this.labels.trackpad_pill_active
                : this.labels.trackpad_pill_paused

            return String(template || '').replace(':key', this.trackpadGlideKeyLabel)
        },
        isTypingTarget(element) {
            if (! element || ! (element instanceof HTMLElement)) {
                return false
            }

            const tag = element.tagName

            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return true
            }

            return element.isContentEditable
        },
        isTypingTargetOutsideSignature(element) {
            return this.isTypingTarget(element) && ! this.$refs.root?.contains(element)
        },
        matchesGlideKey(event) {
            const key = String(this.trackpadGlideKey || 'd').toLowerCase()

            return event.key.toLowerCase() === key
                || event.code === `Key${key.toUpperCase()}`
        },
        setupTrackpadGlideEngagement() {
            if (! this.trackpadGlideEnabled || this.readOnly) {
                return
            }

            this.glideOutsideClickHandler = (event) => {
                if (! this.$refs.root?.contains(event.target) && ! this.glideArmed) {
                    this.glideEngaged = false
                }
            }

            document.addEventListener('pointerdown', this.glideOutsideClickHandler, true)
        },
        engageGlide() {
            if (! this.showGlidePill) {
                return
            }

            this.glideEngaged = true
            this.$refs.root?.focus({ preventScroll: true })
        },
        handleGlideKeydown(event) {
            if (! this.showGlidePill) {
                return
            }

            if (event.metaKey || event.ctrlKey || event.altKey || event.repeat) {
                return
            }

            if (! this.matchesGlideKey(event)) {
                return
            }

            if (! this.glideArmed && ! this.glideEngaged) {
                return
            }

            if (this.isTypingTargetOutsideSignature(event.target)) {
                return
            }

            event.preventDefault()
            event.stopImmediatePropagation()
            this.toggleGlideArmed()
        },
        toggleGlideFromPill() {
            this.engageGlide()
            this.toggleGlideArmed()
        },
        toggleGlideArmed() {
            if (this.glideArmed) {
                this.disarmTrackpadGlide()

                return
            }

            this.glideArmed = true
        },
        disarmTrackpadGlide() {
            this.glideArmed = false
            this.glideAnchor = null
            this.lastGlidePause = null
            this.finishGlideStroke()
        },
        isGlidePointerId(pointerId) {
            return pointerId === 'glide'
        },
        shouldUseTrackpadGlide(event) {
            return this.trackpadGlideEnabled
                && this.glideArmed
                && ! this.readOnly
                && event.pointerType === 'mouse'
                && event.buttons === 0
        },
        clearGlideIdleTimer() {
            if (this.glideIdleTimer !== null) {
                window.clearTimeout(this.glideIdleTimer)
                this.glideIdleTimer = null
            }
        },
        resetGlideIdleTimer() {
            this.clearGlideIdleTimer()

            this.glideIdleTimer = window.setTimeout(() => {
                this.finishGlideStroke(true)
            }, 320)
        },
        appendPointToCurrentStroke(point, options = {}) {
            if (! this.currentStroke) {
                return
            }

            if (this.currentStroke.points.length >= Math.min(8192, this.maxSizeKb * 64)) {
                this.clientValidationError = this.validationMessages?.too_large
                return
            }
            if (this.currentStroke.ink) {
                point.t = Math.max(this.currentStroke.points.at(-1)?.t ?? 0, (point.t ?? performance.now()) - this.currentStroke.startedAt)
                if (point.p === -1 && this.currentStroke.points.at(-1)?.p > 0) point.p = this.currentStroke.points.at(-1).p
                delete this.currentStroke.path
            }
            const minDistance = options.minDistance ?? 0.15
            const bridge = options.bridge ?? false
            const last = this.currentStroke.points[this.currentStroke.points.length - 1]
            const distance = Math.hypot(point.x - last.x, point.y - last.y)

            if (distance < minDistance) {
                return
            }

            if (bridge && distance > 2.5) {
                const steps = Math.max(2, Math.min(16, Math.ceil(distance / 2.5)))

                for (let step = 1; step <= steps; step++) {
                    const progress = step / steps

                    this.currentStroke.points.push({
                        x: last.x + (point.x - last.x) * progress,
                        y: last.y + (point.y - last.y) * progress,
                        width: last.width + ((point.width ?? last.width) - last.width) * progress,
                        p: point.p ?? -1, t: (last.t ?? 0) + ((point.t ?? 0) - (last.t ?? 0)) * progress,
                    })
                }
            } else {
                this.currentStroke.points.push(point)
            }

            if (this.inkTrailEnabled) {
                this.inkTrail.push({
                    x: point.x,
                    y: point.y,
                    width: point.width ?? this.penWidth,
                    at: Date.now(),
                })

                if (this.inkTrail.length > 140) {
                    this.inkTrail.shift()
                }
            }

            this.scheduleRedraw()
        },
        canResumeGlideStroke(point) {
            if (! this.lastGlidePause) {
                return false
            }

            if (Date.now() - this.lastGlidePause.at > 220) {
                return false
            }

            if (this.strokes.length === 0) {
                return false
            }

            return Math.hypot(point.x - this.lastGlidePause.point.x, point.y - this.lastGlidePause.point.y) < 40
        },
        beginGlideStroke(point) {
            if (this.canResumeGlideStroke(point)) {
                this.historyBase = [...this.strokes]
                const existing = this.strokes.pop()
                const resumedStroke = existing ? { ...existing, points: existing.points.map(point => ({ ...point })) } : null

                if (resumedStroke) {
                    if (resumedStroke.ink) resumedStroke.startedAt = point.t - (resumedStroke.points.at(-1)?.t ?? 0)
                    this.activePointerId = 'glide'
                    this.isDrawing = true
                    this.currentStroke = resumedStroke
                    this.lastGlidePause = null
                    this.resetGlideIdleTimer()
                    this.appendPointToCurrentStroke(point, { bridge: true, minDistance: 0.05 })
                    this.scheduleRedraw()

                    return
                }
            }

            this.historyBase = [...this.strokes]
            this.stopReplay(); this.stopWipe()
            this.lastGlidePause = null
            this.activePointerId = 'glide'
            this.isDrawing = true
            const startedAt = point.t
            point.t = 0
            this.currentStroke = {
                ink: true, smoothing: this.smoothingEnabled, startedAt,
                points: [point],
                width: this.penWidth,
                color: this.penColor,
            }
            this.resetGlideIdleTimer()
            this.scheduleRedraw()
        },
        handleTrackpadGlideMove(event, canvas) {
            if (! this.glideArmed) {
                this.glideAnchor = null

                return
            }

            const point = this.normalizePoint(event, canvas)

            if (! point) {
                return
            }

            event.preventDefault()

            if (! this.isDrawing) {
                if (! this.glideAnchor) {
                    this.glideAnchor = { x: event.clientX, y: event.clientY }

                    return
                }

                const travel = Math.hypot(
                    event.clientX - this.glideAnchor.x,
                    event.clientY - this.glideAnchor.y,
                )

                if (travel < 4) {
                    return
                }

                this.glideAnchor = null
                this.beginGlideStroke(point)

                return
            }

            if (! this.isGlidePointerId(this.activePointerId)) {
                return
            }

            this.resetGlideIdleTimer()
            this.appendPointToCurrentStroke(point, { bridge: true, minDistance: 0.05 })
        },
        finishGlideStroke(recordPause = false) {
            if (! this.isGlidePointerId(this.activePointerId)) {
                return
            }

            const lastPoint = this.currentStroke?.points?.[this.currentStroke.points.length - 1] ?? null

            this.clearGlideIdleTimer()
            this.glideAnchor = null
            this.finishStroke({ pointerId: 'glide' })

            if (recordPause && lastPoint) {
                this.lastGlidePause = {
                    at: Date.now(),
                    point: { ...lastPoint },
                }
            } else {
                this.lastGlidePause = null
            }
        },
    }
}
