import { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor } from '../geometry.js'

export function createSignatureCanvas(owner) {
    return {
        setupThemeObserver() {
            this.themeObserver = new MutationObserver(() => {
                this.scheduleRedraw()
            })

            this.themeObserver.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class'],
            })
        },
        setupCanvasObservers() {
            const canvases = [this.$refs.canvas, this.$refs.fullscreenCanvas].filter(Boolean)

            if (canvases.length === 0) {
                return
            }

            this.resizeObserver = new ResizeObserver(() => {
                this.resizeCanvas()
                this.scheduleRedraw()
            })

            canvases.forEach((canvas) => this.resizeObserver.observe(canvas))
        },
        get activeCanvas() {
            return this.isFullscreen ? this.$refs.fullscreenCanvas : this.$refs.canvas
        },
        get padInlineBackgroundStyle() {
            if (isDefaultSignatureBackground(this.backgroundColor)) {
                return null
            }

            return this.backgroundColor ? { backgroundColor: this.backgroundColor } : null
        },
        get aspectRatio() {
            return `${this.viewBoxWidth} / ${this.viewBoxHeight}`
        },
        resizeCanvas() {
            ;[this.$refs.canvas, this.$refs.fullscreenCanvas].forEach((canvas) => {
                if (! canvas) {
                    return
                }

                const rect = canvas.getBoundingClientRect()
                const ratio = Math.min(3, window.devicePixelRatio || 1)
                const width = Math.max(1, Math.floor(rect.width * ratio))
                const height = Math.max(1, Math.floor(rect.height * ratio))

                if (canvas.width !== width || canvas.height !== height) {
                    canvas.width = width
                    canvas.height = height
                }
            })
        },
        scheduleRedraw() {
            if (owner.disposed || this.redrawFrame !== null) return
            this.redrawFrame = window.requestAnimationFrame(() => { this.redrawFrame = null; if (!owner.disposed) this.redraw() })
        },
        redraw() {
            if (owner.disposed || this.isClearing) return
            this.redrawCanvas(this.$refs.canvas)
            this.redrawCanvas(this.$refs.fullscreenCanvas)
        },
        redrawCanvas(canvas) {
            if (! canvas) {
                return
            }

            const context = canvas.getContext('2d')

            if (! context) {
                return
            }

            const width = canvas.width
            const height = canvas.height
            const scale = Math.min(width / this.viewBoxWidth, height / this.viewBoxHeight)
            const offsetX = (width - this.viewBoxWidth * scale) / 2
            const offsetY = (height - this.viewBoxHeight * scale) / 2

            context.setTransform(1, 0, 0, 1, 0, 0)
            context.clearRect(0, 0, width, height)

            const displayBackgroundColor = resolveDisplayBackgroundColor(this.backgroundColor, canvas.closest?.('dialog') ? false : undefined)
            const displayPenColor = resolveDisplayPenColor(this.penColor, canvas.closest?.('dialog') ? false : undefined)

            if (displayBackgroundColor && ! this.guidelinesEnabled) {
                context.fillStyle = displayBackgroundColor
                context.fillRect(0, 0, width, height)
            }

            context.setTransform(scale, 0, 0, scale, offsetX, offsetY)
            context.strokeStyle = displayPenColor
            context.fillStyle = displayPenColor

            this.drawInkTrail(context);

            (this.replayStrokes ?? this.strokes).forEach((stroke) => {
                context.strokeStyle = context.fillStyle = resolveDisplayPenColor(stroke.color ?? this.penColor, canvas.closest?.('dialog') ? false : undefined)
                drawStrokeOnContext(context, stroke, this.penWidth)
            })

            if (this.currentStroke) {
                context.strokeStyle = context.fillStyle = resolveDisplayPenColor(this.currentStroke.color ?? this.penColor, canvas.closest?.('dialog') ? false : undefined)
                drawStrokeOnContext(context, this.currentStroke, this.penWidth, false)
            }
        },
        drawInkTrail(context) {
            if (! this.inkTrailEnabled || this.inkTrail.length === 0) {
                return
            }

            const now = Date.now()
            const maxAge = 900

            this.inkTrail = this.inkTrail.filter((dot) => now - dot.at <= maxAge)

            this.inkTrail.forEach((dot, index) => {
                const age = now - dot.at
                const fade = Math.max(0, 1 - age / maxAge)
                const trailAlpha = fade * (0.15 + (index / Math.max(1, this.inkTrail.length)) * 0.35)

                context.save()
                context.globalAlpha = trailAlpha
                context.beginPath()
                context.arc(dot.x, dot.y, (dot.width ?? this.penWidth) * 0.35, 0, Math.PI * 2)
                context.fill()
                context.restore()
            })
        },
    }
}
