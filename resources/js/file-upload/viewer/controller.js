import { createMediaOwner } from '../../media/lifecycle/owner.js'
import { createSharedElementMotion } from './motion/shared-element.js'

/** The browser displays the original directly; zoom never rasterizes or re-encodes it. */
export default function fileUploadViewer({ items = [], selected, schemaComponentKey, cancelParentActions = null, labels = {} }) {
    const owner = createMediaOwner(), points = new Map()
    let frame = null, start = null, imageEpoch = 0, imageAnimation = null, closing = false, direction = 0
    let navigation = null
    let motion = null
    let field = null, intent = null
    const warmImages = new Map()
    let releaseInformation = () => {}
    const clamp = (n, a, b) => Math.max(a, Math.min(b, n))
    return {
        items, selected, displayed: selected, imageUrl: null, previewVisible: false, scale: 1, panX: 0, panY: 0, busy: false, loading: true, animating: false, error: null,
        current() { return this.items.find(item => item.key === this.displayed) ?? this.items[0] },
        position() { return this.items.findIndex(item => item.key === this.displayed) + 1 },
        isSaving() { return !!this.$el?.closest('.fff-upload-lightbox-window')?.querySelector?.('.fff-upload-lightbox-save.is-saving') },
        init() {
            field = [...document.querySelectorAll('[data-fff-viewer-owner]')]
                .find(element => element.getAttribute('data-fff-viewer-owner') === schemaComponentKey)
            intent = field?.getAttribute('data-fff-viewer-intent')
            const cancellation = field?.getAttribute('data-fff-viewer-cancelling')
            const cancelled = cancellation != null && (cancellation === intent || cancellation === 'true')
            field?.removeAttribute('data-fff-viewer-cancelling')
            const notify = name => field?.dispatchEvent(new CustomEvent(name, { detail: { intent } }))
            owner.defer(() => notify('fff-viewer-retired'))
            owner.listen(field, 'fff-viewer-cancel-request', () => { void this.close(true) })
            // Upload completion can order server state differently from the
            // displayed FilePond list. Reorder authorized items only, by that list.
            const order = new Map([...(field?.querySelectorAll('img[data-fff-viewer-file-key]') ?? [])]
                .map((image, index) => [image.getAttribute('data-fff-viewer-file-key'), index]))
            this.items = [...this.items].sort((a, b) => (order.get(a.key) ?? Infinity) - (order.get(b.key) ?? Infinity))
            this.initialize()
            notify('fff-viewer-mounted')
            // The mounted owner reconciles its own action. Never leave an inert
            // factory waiting for its opener's mount promise to resolve first.
            // init remains synchronous for the asset readiness barrier.
            if (cancelled) queueMicrotask(() => { if (!owner.disposed) void this.close(true) })
        },
        initialize() {
            const stage = this.$refs.stage
            motion = createSharedElementMotion({ owner, surface: this.$el.closest('.fff-upload-lightbox-window'),
                image: this.$refs.image, ownerKey: schemaComponentKey, selected: () => this.displayed, onClose: () => this.close() })
            owner.defer(() => { releaseInformation(); for (const img of warmImages.values()) img.removeAttribute('src'); warmImages.clear() })
            // Initialization stays synchronous for the asset readiness barrier.
            // Original/adjacent image work starts only after the preview settles.
            const preparation = motion.prepareOpening()
            this.previewVisible = motion.hasPreview()
            void preparation.then(() => {
                if (owner.disposed || closing) return
                this.imageUrl = this.current()?.url ?? null
            }).catch(() => {
                if (owner.disposed || closing) return
                this.error = labels.failed
                motion.reveal()
            })
            owner.defer(() => imageAnimation?.cancel())
            owner.listen(this.$el.closest('.fff-upload-lightbox-window'), 'keydown', event => {
                if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); void this.close() }
            }, { capture: true })
            owner.listen(stage, 'wheel', event => {
                if (this.busy) return
                event.preventDefault()
                this.setScale(this.scale * Math.exp(-event.deltaY * .002))
            }, { passive: false })
            owner.listen(stage, 'dblclick', event => {
                if (!this.busy && !this.loading && event.target === this.$refs.image) this.setScale(this.scale > 1 ? 1 : 2)
            })
            owner.listen(stage, 'keydown', event => {
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault(); void this.navigate(event.key === 'ArrowRight' ? 1 : -1)
                } else if (event.key === '+' || event.key === '=') this.setScale(this.scale * 1.2)
                else if (event.key === '-') this.setScale(this.scale / 1.2)
                else if (event.key === '0') this.resetZoom()
            })
            const span = () => { const pair = [...points.values()]; return Math.hypot(pair[1].x - pair[0].x, pair[1].y - pair[0].y) }
            owner.listen(stage, 'pointerdown', event => {
                if (this.busy || points.size >= 2 || event.button > 0 || event.target?.closest?.('button')) return
                points.set(event.pointerId, { x: event.clientX, y: event.clientY })
                stage.setPointerCapture(event.pointerId)
                start = { x: event.clientX, y: event.clientY, panX: this.panX, panY: this.panY, scale: this.scale, span: points.size === 2 ? span() : 0 }
            })
            owner.listen(stage, 'pointermove', event => {
                if (!start || !points.has(event.pointerId)) return
                points.set(event.pointerId, { x: event.clientX, y: event.clientY })
                if (points.size === 2 && start.span > 0) this.setScale(start.scale * span() / start.span)
                else if (this.scale > 1) { this.panX = start.panX + event.clientX - start.x; this.panY = start.panY + event.clientY - start.y; this.paint() }
            })
            const end = event => {
                if (start && points.size === 1 && this.scale === 1 && event.pointerType === 'touch') {
                    const delta = event.clientX - start.x
                    if (Math.abs(delta) > 64 && Math.abs(event.clientY - start.y) < 60) void this.navigate(delta < 0 ? 1 : -1)
                }
                points.delete(event.pointerId); start = null
                try { stage.releasePointerCapture(event.pointerId) } catch { /* Pointer already released. */ }
            }
            owner.listen(stage, 'pointerup', end)
            owner.listen(stage, 'pointercancel', event => { points.delete(event.pointerId); start = null })
            const observer = new ResizeObserver(() => this.paint())
            observer.observe(stage); owner.defer(() => observer.disconnect())
            owner.defer(() => { if (frame !== null) cancelAnimationFrame(frame); points.clear(); start = null })
        },
        warmNeighbours() {
            if (typeof Image !== 'function' || owner.disposed || this.items.length < 2) return
            const index = this.items.findIndex(item => item.key === this.displayed)
            const neighbours = new Set([-1, 1].map(step => this.items[(index + step + this.items.length) % this.items.length]?.url).filter(Boolean))
            for (const [url, img] of warmImages) if (!neighbours.has(url)) { img.removeAttribute('src'); warmImages.delete(url) }
            // Only the two neighbouring authorized originals, never the whole gallery.
            for (const url of neighbours) if (!warmImages.has(url)) {
                const img = new Image(); img.decoding = 'async'; img.src = url
                warmImages.set(url, img)
            }
        },
        lockInformation() {
            releaseInformation()
            const panel = this.$el?.closest('.fff-upload-lightbox-window')?.querySelector('.fff-upload-lightbox-information')
            if (!panel) return
            const previous = panel.inert, busy = panel.getAttribute('aria-busy')
            panel.inert = true; panel.setAttribute('aria-busy', 'true')
            releaseInformation = () => { panel.inert = previous; busy === null ? panel.removeAttribute('aria-busy') : panel.setAttribute('aria-busy', busy); releaseInformation = () => {} }
        },
        setScale(value) {
            this.scale = clamp(value, 1, 6)
            if (this.scale === 1) this.panX = this.panY = 0
            this.paint()
        },
        resetZoom() { this.scale = 1; this.panX = this.panY = 0; this.paint() },
        paint() {
            if (frame !== null || owner.disposed) return
            frame = requestAnimationFrame(() => {
                frame = null
                if (owner.disposed) return
                const stage = this.$refs.stage, image = this.$refs.image
                const ratio = image.naturalWidth / image.naturalHeight || 1
                const width = Math.min(image.clientWidth || stage.clientWidth, (image.clientHeight || stage.clientHeight) * ratio)
                const height = width / ratio
                this.panX = clamp(this.panX, -Math.max(0, width * this.scale - stage.clientWidth) / 2, Math.max(0, width * this.scale - stage.clientWidth) / 2)
                this.panY = clamp(this.panY, -Math.max(0, height * this.scale - stage.clientHeight) / 2, Math.max(0, height * this.scale - stage.clientHeight) / 2)
                image.style.transform = `translate3d(${this.panX}px,${this.panY}px,0) scale(${this.scale})`
            })
        },
        async imageReady() {
            const epoch = imageEpoch
            if (closing || owner.disposed) return
            const image = this.$refs.image, url = this.imageUrl
            try { await image.decode?.() }
            catch {
                if (!owner.disposed && !closing && epoch === imageEpoch && url === this.imageUrl) this.imageFailed()
                return
            }
            if (owner.disposed || closing || epoch !== imageEpoch || url !== this.imageUrl) return
            this.loading = false; this.paint()
            if (motion) {
                this.busy = true
                this.animating = true
                await motion.open(direction)
                if (!owner.disposed && !closing && epoch === imageEpoch) {
                    this.previewVisible = false; this.animating = false; this.busy = !!navigation; this.warmNeighbours()
                }
                return
            }
            imageAnimation?.cancel()
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) imageAnimation = this.$refs.image.animate([
                { opacity: 0, transform: `translate3d(${direction * 24}px,0,0) scale(.96)` },
                { opacity: 1, transform: 'translate3d(0,0,0) scale(1)' },
            ], { duration: 320, easing: 'cubic-bezier(.22,1,.36,1)' })
        },
        async close(preserveParents = false) {
            if (closing || owner.disposed || this.isSaving()) return
            closing = true
            this.busy = true
            const surface = this.$el.closest('.fff-upload-lightbox-window')
            let animation
            try {
                if (motion) await motion.close()
                else if (surface && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    animation = surface.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 180, easing: 'ease-out', fill: 'forwards' })
                    owner.defer(() => animation.cancel())
                    await animation.finished
                }
                if (navigation) await navigation.catch(() => {})
                if (owner.disposed) return
                // Keep the owning overlay until the action stack is reconciled.
                // Closing locally first exposes the opener before unmount completes.
                await this.$wire.unmountAction(preserveParents ? false : cancelParentActions)
            } catch {
                if (owner.disposed) return
                animation?.cancel()
                motion?.reveal()
                closing = false; this.busy = false; this.error = labels.saveFailed
                if (!this.imageUrl) { this.loading = true; this.imageUrl = this.current()?.url ?? null }
            }
        },
        imageFailed() {
            if (owner.disposed || closing) return
            this.previewVisible = false; this.loading = false; this.animating = false; this.busy = !!navigation; this.error = labels.failed; motion?.reveal()
        },
        async navigate(step) {
            if (this.busy || this.loading || closing || this.isSaving() || this.items.length < 2 || owner.disposed) return
            const index = this.items.findIndex(item => item.key === this.selected)
            const target = this.items[(index + step + this.items.length) % this.items.length]
            this.busy = true; this.error = null
            const epoch = ++imageEpoch
            // Presentation responds to intent immediately. The authoritative
            // selected key and metadata remain unchanged until validation succeeds.
            this.lockInformation()
            motion?.prepareNavigation()
            direction = step; this.displayed = target.key; this.loading = !!motion; this.resetZoom()
            this.imageUrl = this.current()?.url ?? null
            try {
                navigation = this.$wire.callSchemaComponentMethod(schemaComponentKey, 'navigateFileViewer', [target.key])
                const accepted = await navigation
                if (owner.disposed || closing || epoch !== imageEpoch) return
                // Livewire validation responses can resolve without a result,
                // rather than rejecting the browser promise. Require acceptance.
                if (accepted !== true) throw Error('Viewer navigation was not accepted.')
                this.selected = target.key
                this.warmNeighbours()
            } catch {
                if (!owner.disposed && !closing && epoch === imageEpoch) {
                    imageEpoch++
                    this.animating = false
                    motion?.prepareNavigation()
                    direction = -step; this.displayed = this.selected; this.loading = !!motion; this.resetZoom()
                    this.imageUrl = this.current()?.url ?? null
                    this.error = labels.saveFailed
                }
            }
            finally {
                navigation = null; releaseInformation()
                if (!owner.disposed && !closing) this.busy = this.loading || this.animating
            }
        },
        destroy() { imageEpoch++; owner.dispose(); this.$refs.image?.removeAttribute('src') },
    }
}
