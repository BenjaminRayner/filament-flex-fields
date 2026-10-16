import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { isMountedFactoryReady } from '../../core/alpine-readiness.js'
import { createDisclosureMotion } from './disclosure-motion.js'

const PREPARED = 'fff-map-editor-prepared'

/** Per-card readiness and ownership; sibling editors exchange height in the same frame. */
export function createFeatureDisclosure(id) {
    let scope = null
    let frame = null
    let retry = () => {}
    return {
        editorMounted: false,
        editorActive: false,
        editorExpanded: false,
        editorFailed: false,
        init() {
            const root = this.$el
            const group = root.closest('.fff-location__features')
            const reveal = root.querySelector('.fff-location__feature-reveal')
            if (!reveal) return
            const inner = reveal.querySelector('.fff-location__feature-reveal-inner')
            scope = createResourceScope()
            const selected = () => String(this.selectedFeatureId) === String(id)
            const close = () => {
                const wasExpanded = this.editorExpanded
                this.editorExpanded = false
                motion.setExpanded(false)
                // A not-yet-open editor has no closing paint to retain.
                if (!wasExpanded && !selected()) this.editorActive = false
            }
            const motion = createDisclosureMotion(reveal, inner, { onClosed: () => {
                if (!selected()) this.editorActive = false
            } })
            scope.defer(() => motion.destroy())
            const prepare = () => {
                if (frame !== null) return
                frame = requestAnimationFrame(() => {
                    frame = null
                    if (scope.disposed || !root.isConnected || !selected()) return
                    group.dispatchEvent(new CustomEvent(PREPARED, { detail: { id: String(id) } }))
                })
            }
            const check = () => {
                if (scope.disposed || !root.isConnected || !selected()) return
                const editor = root.querySelector('.fff-location__feature-edit')
                if (!editor) {
                    if (this.readOnly || !this.mapConfiguration.editableZones && this.geometryFeatures().find(feature => String(feature.id) === String(id))?.geometry.type !== 'Point') prepare()
                    return
                }
                const factories = [...editor.querySelectorAll('[x-fff-load]')]
                const batches = [...editor.querySelectorAll('[data-fff-asset-batch]')]
                this.editorFailed = factories.some(field => field._x_async === 'failed')
                    || batches.some(batch => batch.getAttribute('data-fff-assets-ready') === 'failed')
                if (this.editorFailed) { close(); prepare(); return }
                if (factories.some(field => !isMountedFactoryReady(field))
                    || batches.some(batch => batch.getAttribute('data-fff-assets-ready') !== '')) return
                if (!this.editorExpanded) prepare()
            }
            const activate = () => {
                if (frame !== null) cancelAnimationFrame(frame)
                frame = null
                if (selected()) {
                    this.editorActive = true
                    this.editorMounted = true
                    this.$nextTick(check)
                } else if (this.selectedFeatureId === null || this.selectedFeatureId === undefined) close()
                // A previous editor retains its height/CSS until the next card is ready.
            }
            scope.listen(group, PREPARED, event => {
                if (String(this.selectedFeatureId) !== event.detail.id) return
                if (!selected() || this.editorFailed) { close(); return }
                this.editorExpanded = Boolean(root.querySelector('.fff-location__feature-edit'))
                motion.setExpanded(this.editorExpanded)
            })
            retry = async () => {
                if (scope.disposed || !selected()) return
                this.editorFailed = false
                try {
                    await root.ownerDocument.defaultView.FffAssetInjector.ensureAssets(root)
                    if (scope.disposed || !selected()) return
                    for (const field of root.querySelectorAll('[x-fff-load]')) field.dispatchEvent(new CustomEvent('fff-load-retry'))
                    check()
                } catch { if (!scope.disposed && selected()) { this.editorFailed = true; close(); prepare() } }
            }
            const observer = new MutationObserver(check)
            observer.observe(root, { childList: true, subtree: true, attributes: true,
                attributeFilter: ['data-fff-assets-ready', 'data-fff-alpine-ready'] })
            scope.defer(() => observer.disconnect())
            scope.defer(() => { if (frame !== null) cancelAnimationFrame(frame) })
            scope.listen(root, 'fff-load-ready', check)
            scope.listen(root, 'fff-load-error', check)
            this.$watch('selectedFeatureId', activate)
            activate()
        },
        retryEditor() { void retry() },
        destroy() { scope?.dispose() },
    }
}
