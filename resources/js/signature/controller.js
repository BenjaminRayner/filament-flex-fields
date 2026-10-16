import { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor } from './geometry.js'
import { createMediaOwner } from '../media/lifecycle/owner.js'
import { createSignatureGlide } from './runtime/glide.js'
import { createSignatureCanvas } from './runtime/canvas.js'
import { createSignatureDrawing } from './runtime/drawing.js'
import { createSignatureExport } from './runtime/export.js'
import { createSignatureHistory } from './runtime/history.js'
import { createSignatureOverlay } from './runtime/overlay.js'

export default function signatureFieldFormComponent({
    state,
    penColor,
    penWidth,
    backgroundColor,
    viewBoxWidth,
    viewBoxHeight,
    readOnly,
    fullscreenEnabled,
    undoable,
    smoothingEnabled,
    trackpadGlideEnabled,
    trackpadGlideKey,
    guidelinesEnabled,
    downloadFormat,
    downloadFilename,
    webpQuality,
    storedSvg = null,
    minStrokes = 1,
    maxSizeKb = 48,
    required = false,
    validationMessages = {},
    labels,
    inkTrailEnabled = false,
    pdfPreviewEnabled = false,
    legalAcknowledgment = null,
    penColors = ['#18181b', '#2563eb', '#7c3aed'],
    penWidths = [3.4, 5.6, 8.4],
    signer = null,
    replayEnabled = true,
}) {
    const owner = createMediaOwner()
    const runtime = {
        state,
        penColor,
        penWidth,
        backgroundColor,
        viewBoxWidth,
        viewBoxHeight,
        readOnly,
        fullscreenEnabled,
        undoable,
        smoothingEnabled,
        trackpadGlideEnabled,
        trackpadGlideKey,
        guidelinesEnabled,
        downloadFormat,
        downloadFilename,
        webpQuality,
        storedSvg,
        minStrokes,
        maxSizeKb,
        required,
        validationMessages,
        labels,
        inkTrailEnabled,
        pdfPreviewEnabled,
        legalAcknowledgment,
        strokes: [],
        inkTrail: [],
        isPdfPreviewOpen: false,
        pdfPreviewSvg: null,
        currentStroke: null,
        isDrawing: false,
        isFullscreen: false,
        activePointerId: null,
        glideIdleTimer: null,
        glideAnchor: null,
        glideArmed: false,
        glideEngaged: false,
        glideOutsideClickHandler: null,
        lastGlidePause: null,
        redrawFrame: null,
        resizeObserver: null,
        clientValidationError: null,
        displayReady: false,
        themeObserver: null,

        penColors, penWidths, signer, replayEnabled,
        initialized: false, undoStack: [], redoStack: [], historyBase: null,
        isReplaying: false, isClearing: false, replayStrokes: null,
        init() {
            if (this.initialized || owner.disposed) return
            this.initialized = true
            this.hydrateFromState()
            this.setupThemeObserver()
            this.setupTrackpadGlideEngagement()
            this.$watch('state', (value) => {
                if (value === this.exportSvg()) {
                    return
                }

                this.undoStack = []; this.redoStack = []
                this.hydrateFromState()
                this.scheduleRedraw()
            })

            this.$nextTick(() => {
                if (owner.disposed) return
                this.setupCanvasObservers()
                this.resizeCanvas()
                this.scheduleRedraw()
                this.displayReady = true
            })
        },
        hydrateFromState() {
            if (this.isStoredFileReference(this.state)) {
                if (this.storedSvg) {
                    this.strokes = svgToStrokes(this.storedSvg, this.penWidth, this.viewBoxWidth, this.viewBoxHeight)
                }

                // Tokenized mid-session without remount: keep current strokes so the pad
                // does not blank while Livewire state flips SVG → ffstage:.
                return
            }

            this.strokes = svgToStrokes(this.state || '', this.penWidth, this.viewBoxWidth, this.viewBoxHeight)
        },
        isStoredFileReference(value) {
            return typeof value === 'string' && (
                value.startsWith('ffstage:')
                || value.startsWith('ffmedia:')
                || (value.includes('flex-forms/submissions/') && ! value.includes('<'))
            )
        },
        get hasSignature() {
            return this.strokes.length > 0
        },
        destroy() {
            owner.dispose()
            this.stopReplay()
            this.stopWipe()
            this.resizeObserver?.disconnect()
            this.themeObserver?.disconnect()
            window.cancelAnimationFrame(this.redrawFrame)
            this.clearGlideIdleTimer()
            if (this.glideOutsideClickHandler) document.removeEventListener('pointerdown', this.glideOutsideClickHandler, true)
            this.closeFullscreen()
            this.closePdfPreview()
        },
    }
    for (const mixin of [createSignatureGlide(owner), createSignatureCanvas(owner), createSignatureDrawing(owner), createSignatureExport(owner), createSignatureHistory(owner), createSignatureOverlay(owner)]) {
        Object.defineProperties(runtime, Object.getOwnPropertyDescriptors(mixin))
    }
    return runtime
}
