import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createDualModel } from './model.js'
import { createDualPresentation } from './presentation.js'
import { createDualGestures } from './gestures.js'
import { dualKeyboard } from './keyboard.js'

export function createDualListboxRuntime(binding, dependencies = {}) {
    let root, config, scope, model, presentation, gestures, initialization, disposed = false, pendingScroll = new Set(), scrollFrame = false
    const commit = vm => count => {
        const next = model.state
        if (JSON.stringify(next) !== JSON.stringify(vm.state)) vm.state = next
        presentation.resetFocus(); presentation.render()
        if (count) presentation.announce(count)
    }
    return {
        state: binding.state, disabled: false, readOnly: false,
        init() {
            if (initialization || disposed) return initialization
            root = this.$el; config = { ...JSON.parse(root.dataset.dualConfig ?? '{}'), ...binding }
            this.disabled = !!config.disabled; this.readOnly = !!config.readOnly
            scope = createResourceScope()
            initialization = (async () => {
                if (!await (dependencies.activate ?? waitForAssetActivation)(root, { signal: scope.signal }) || disposed) return
                model = createDualModel(config, this.state)
                scope.defer(() => model.dispose())
                presentation = (dependencies.presentation ?? createDualPresentation)(root, config, model)
                scope.defer(() => presentation.destroy())
                gestures = (dependencies.gestures ?? createDualGestures)({ root, scope, model, presentation, commit: commit(this) })
                presentation.render()
                const alpine = dependencies.alpine ?? globalThis.Alpine
                watchOwnedAlpineValue(scope, () => this.state, value => {
                    if (JSON.stringify(value) === JSON.stringify(model.state)) return
                    gestures.cancel(); model.sync(value); presentation.resetFocus(); presentation.render()
                }, alpine)
                watchOwnedAlpineValue(scope, () => [this.disabled, this.readOnly], () => {
                    config.disabled = this.disabled; config.readOnly = this.readOnly
                    gestures.cancel(); model.clear(); presentation.render()
                }, alpine)
                const paneFor = target => target.closest('[data-dual-pane]')?.dataset.dualPane
                const rowFor = target => target.closest('[data-dual-row]')
                scope.listen(root, 'click', event => {
                    if (!model.interactive() || gestures.ignoreClick()) return
                    const button = event.target.closest('[data-dual-action]'), row = rowFor(event.target), pane = paneFor(event.target)
                    if (button) {
                        if (button.disabled) return
                        const action = button.dataset.dualAction
                        if (action === 'select-all') { model.all(pane); presentation.render(); return }
                        if (action === 'clear') { model.clear(pane); presentation.render(); return }
                        if (action === 'up' || action === 'down') { if (model.move(row.dataset.value, action === 'up' ? -1 : 1)) commit(this)(); return }
                        if (action === 'swap') { if (model.swap()) commit(this)(); return }
                        const from = action.includes('remove') ? 'selected' : 'available'
                        const keys = action.startsWith('all-') ? model.items(from).map(item => item.value) : [...model.marked[from]]
                        const count = model.transfer(from, keys); if (count) commit(this)(count)
                        return
                    }
                    if (!row) return
                    const coarse = event.pointerType === 'touch' || event.pointerType === 'pen' || root.ownerDocument.defaultView.matchMedia('(pointer: coarse)').matches
                    model.mark(pane, row.dataset.value, { additive: coarse || event.ctrlKey || event.metaKey || !!event.target.closest('.fff-dual-listbox__check'), range: event.shiftKey })
                    presentation.focus(pane, row.dataset.value)
                })
                scope.listen(root, 'dblclick', event => {
                    const row = rowFor(event.target), pane = paneFor(event.target)
                    if (!row || !config.moveOnDoubleClick || !model.interactive() || event.target.closest('button')) return
                    const count = model.transfer(pane, [row.dataset.value]); if (count) commit(this)(count)
                })
                scope.listen(root, 'keydown', event => {
                    if (event.target.matches('input') || event.target.closest('button')) return
                    const row = rowFor(event.target)
                    dualKeyboard(event, { root, pane: paneFor(event.target), key: row?.dataset.value, model, presentation, commit: commit(this) })
                })
                scope.listen(root, 'pointerdown', event => {
                    if (event.target.closest('button,input')) return
                    const row = rowFor(event.target)
                    if (row) gestures.pointerDown(event, row, paneFor(event.target))
                })
                scope.listen(root, 'input', event => {
                    const pane = event.target.dataset.dualSearch
                    if (!pane || !model.interactive()) return
                    gestures.cancel(); model.query(pane, event.target.value)
                    presentation.panes.get(pane).list.scrollTop = 0
                    presentation.panes.get(pane).focused = null
                    presentation.render(pane)
                })
                scope.listen(root, 'scroll', event => {
                    const pane = event.target.dataset?.dualList
                    if (!pane) return
                    pendingScroll.add(pane)
                    if (scrollFrame) return
                    scrollFrame = true
                    scope.frame(() => { scrollFrame = false; for (const side of pendingScroll) presentation.render(side); pendingScroll.clear() })
                }, { capture: true, passive: true })
                const Observer = root.ownerDocument.defaultView.ResizeObserver
                if (Observer) {
                    const observer = new Observer(() => {
                        for (const side of ['available', 'selected']) pendingScroll.add(side)
                        if (scrollFrame) return
                        scrollFrame = true
                        scope.frame(() => { scrollFrame = false; presentation.render(); pendingScroll.clear() })
                    })
                    observer.observe(root); scope.defer(() => observer.disconnect())
                }
                root.setAttribute('data-dual-ready', '')
            })().catch(error => { scope.dispose(); initialization = null; throw error })
            return initialization
        },
        destroy() { if (disposed) return; disposed = true; scope?.dispose(); pendingScroll.clear(); root = config = gestures = presentation = model = null },
    }
}
