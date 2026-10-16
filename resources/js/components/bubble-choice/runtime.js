import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../../runtime/lifecycle/asset-activation.js'
import { mergeLayoutOptions, buildBubbleRows } from './layout.js'
import { selectedKeys, toggleKey } from './selection.js'
import { createBubblePresentation, bubbleFaceStyle } from './presentation.js'
import { bindBubbleInteraction } from './interaction.js'
import { easeForMorph, MORPH_SELECT_MS, MORPH_DESELECT_MS } from './shape-morph.js'

export function createBubbleRuntime(configuration) {
    let scope, ready, presentation, disposed = false, cancelFrame = null, geometryDirty = false, selected = [], entrance = null
    const animations = new Map(), progress = new Map()
    const layout = mergeLayoutOptions({ ...configuration.layout, _optionCount: configuration.options.length })
    const rows = buildBubbleRows(configuration.options, layout.numCols)
    const options = configuration.options.map(option => ({...option, value: String(option.value)}))
    const config = { ...configuration, options }
    return {
        state: configuration.state, disabled: !!configuration.disabled, readOnly: !!configuration.readOnly,
        toggle(key) {
            if (disposed) return
            const next = toggleKey(this.state, key, options, configuration.maxItems, this.disabled || this.readOnly)
            if (JSON.stringify(next) === JSON.stringify(selectedKeys(this.state, options))) return
            this.state = next
        },
        bubbleFaceStyle: option => bubbleFaceStyle(option, config),
        init() {
            if (ready || disposed) return ready
            scope = createResourceScope({ onEffectError: error => this.$dispatch?.('fff-bubble-choice-error', { error }) })
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || disposed) return
                presentation = createBubblePresentation(this.$el, layout, rows, config)
                const reduced = this.$el.ownerDocument.defaultView.matchMedia('(prefers-reduced-motion: reduce)')
                const schedule = () => {
                    if (cancelFrame || disposed) return
                    cancelFrame = scope.frame(now => {
                        cancelFrame = null
                        if (geometryDirty) { presentation.paintGeometry(); geometryDirty = false }
                        for (const [key, animation] of animations) {
                            const t = reduced.matches ? 1 : Math.min(1, (now - animation.start) / animation.duration)
                            const value = animation.from + (animation.to - animation.from) * easeForMorph(t, animation.to === 1)
                            progress.set(key, value); presentation.morph(key, value, animation.to === 1)
                            if (t >= 1) { animations.delete(key); if (value === 0) progress.delete(key) }
                        }
                        if (animations.size) schedule()
                    })
                }
                const synchronize = (animate = true) => {
                    const next = selectedKeys(this.state, options), active = new Set(next), previous = new Set(selected)
                    presentation.paintSelection(next, this.disabled, this.readOnly, configuration.maxItems)
                    for (const key of new Set([...next, ...selected])) {
                        const to = active.has(key) ? 1 : 0
                        if (animate && active.has(key) === previous.has(key)) continue
                        const from = progress.get(key) ?? (previous.has(key) ? 1 : 0)
                        if (!animate || reduced.matches || from === to) {
                            animations.delete(key); progress.set(key, to); presentation.morph(key, to, to === 1)
                        } else animations.set(key, { from, to, start: performance.now(), duration: to ? MORPH_SELECT_MS : MORPH_DESELECT_MS })
                    }
                    selected = next
                    if (animations.size) schedule()
                }
                synchronize(false)
                presentation.resize()
                scope.frame(() => {
                    if (disposed) return
                    entrance = presentation.reveal(reduced.matches)
                    if (entrance) {
                        const release = scope.defer(() => { entrance?.cancel(); entrance = null })
                        entrance.finished.then(release, release)
                    }
                })
                scope.listen(presentation.scroll, 'scroll', () => { geometryDirty = true; schedule() }, { passive: true })
                const observer = new ResizeObserver(() => { if (!disposed) presentation.resize() })
                scope.trackObserver(observer); observer.observe(presentation.scroll)
                bindBubbleInteraction({root: this.$el, presentation, scope, toggle: key => this.toggle(key),
                    allowed: () => !disposed && !this.disabled && !this.readOnly })
                for (const key of ['state', 'disabled', 'readOnly']) {
                    const stop = this.$watch(key, () => synchronize())
                    if (typeof stop === 'function') scope.defer(stop)
                }
                scope.listen(reduced, 'change', () => { if (reduced.matches) entrance?.finish(); synchronize(false) })
                this.$el.setAttribute('data-bubble-choice-ready', '')
            })().catch(error => { scope.dispose(); ready = null; presentation = null; throw error })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true; scope?.dispose(); animations.clear(); progress.clear(); presentation = null; cancelFrame = null
        },
    }
}
