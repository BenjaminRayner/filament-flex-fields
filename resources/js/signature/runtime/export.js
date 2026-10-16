import { polishStrokePoints, strokesToSvg, svgToStrokes, drawStrokeOnContext, countSvgPaths, byteSize, isDefaultSignatureBackground, resolveDisplayBackgroundColor, resolveDisplayPenColor } from '../geometry.js'

export function createSignatureExport(owner) {
    return {
        get canDownload() {
            return this.downloadFormat !== null && this.hasSignature
        },
        get previewSvgMarkup() {
            const svg = this.exportSvg()

            if (! svg) {
                return null
            }

            return svg
        },
        get signaturePreviewUrl() { return this.pdfPreviewSvg ? 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.pdfPreviewSvg) : null },
        openPdfPreview() {
            if (! this.pdfPreviewEnabled || owner.disposed || this.isPdfPreviewOpen) {
                return
            }
            this.pdfPreviewSvg = this.exportSvg()
            this.isPdfPreviewOpen = true
            this.$nextTick(() => {
                const dialog = this.$refs.pdfDialog
                if (!owner.disposed && this.isPdfPreviewOpen && dialog && !dialog.open) dialog.showModal()
            })
        },
        closePdfPreview() {
            this.isPdfPreviewOpen = false
            this.pdfPreviewSvg = null
            if (this.$refs.pdfDialog?.open) this.$refs.pdfDialog.close()
        },
        renderToExportCanvas(pixelRatio = 2) {
            const canvas = document.createElement('canvas')
            canvas.width = Math.max(1, Math.round(this.viewBoxWidth * pixelRatio))
            canvas.height = Math.max(1, Math.round(this.viewBoxHeight * pixelRatio))

            const context = canvas.getContext('2d')

            if (! context) {
                return null
            }

            if (this.backgroundColor) {
                context.fillStyle = this.backgroundColor
                context.fillRect(0, 0, canvas.width, canvas.height)
            } else {
                context.clearRect(0, 0, canvas.width, canvas.height)
            }

            context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0)
            context.strokeStyle = this.penColor
            context.fillStyle = this.penColor

            this.strokes.forEach((stroke) => {
                context.strokeStyle = context.fillStyle = stroke.color ?? this.penColor
                drawStrokeOnContext(context, stroke, this.penWidth)
            })

            return canvas
        },
        exportSvg() {
            return strokesToSvg(this.strokes, {
                penColor: this.penColor,
                penWidth: this.penWidth,
                backgroundColor: this.backgroundColor,
                viewBoxWidth: this.viewBoxWidth,
                viewBoxHeight: this.viewBoxHeight,
            })
        },
        validateClient() {
            const svg = this.exportSvg()

            if (! svg) {
                return this.required
                    ? (this.validationMessages?.required ?? null)
                    : null
            }

            if (countSvgPaths(svg) < this.minStrokes) {
                return this.validationMessages?.too_few_strokes ?? null
            }

            if (byteSize(svg) > this.maxSizeKb * 1024) {
                return this.validationMessages?.too_large ?? null
            }

            return null
        },
        commitState() {
            this.clientValidationError = this.validateClient()
            this.state = this.exportSvg()
        },
        triggerDownload(blob, filename) {
            if (owner.disposed) return
            const key = `download:${Date.now()}`
            const url = owner.objectUrl(key, blob)
            const anchor = document.createElement('a')

            anchor.href = url
            anchor.download = filename
            anchor.rel = 'noopener'
            anchor.click()

            const timer = window.setTimeout(() => { owner.releaseUrl(key); releaseTimer() }, 0)
            const releaseTimer = owner.defer(() => window.clearTimeout(timer))
        },
        downloadSignature() {
            if (! this.canDownload) {
                return
            }

            const baseName = this.downloadFilename || 'signature'

            if (this.downloadFormat === 'svg') {
                const svg = this.exportSvg()

                if (! svg) {
                    return
                }

                this.triggerDownload(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }), `${baseName}.svg`)

                return
            }

            if (this.downloadFormat === 'webp') {
                const canvas = this.renderToExportCanvas(2)

                if (! canvas) {
                    return
                }

                canvas.toBlob((blob) => {
                    if (blob && !owner.disposed) {
                        this.triggerDownload(blob, `${baseName}.webp`)
                    }
                    canvas.width = canvas.height = 1
                }, 'image/webp', this.webpQuality)
            }
        },
    }
}
