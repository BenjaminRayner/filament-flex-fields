import { colorRuntime } from './lifetime.js'
import { resolveColorPalette } from './palette.js'
import { mountOwnedMenuPortal } from '../overlay/menu/portal-owner.js'

/** Only the optional panel is deferred. Every trigger/state owner initializes. */
export function createColorPanelLifecycle(configuration) {
    return {
        panelMounted: false, panelPreparing: false, panelLoadError: false, lazyPanel: false,
        prepareColorPanel() {
            const runtime = colorRuntime(this)
            if (runtime.panelPending) return runtime.panelPending
            runtime.panelExitCleanup?.()
            runtime.panelExitCleanup = null
            const epoch = ++runtime.panelEpoch
            this.panelPreparing = true
            this.panelLoadError = false
            if (!this.panelMounted) {
                this.gridPalette = resolveColorPalette(configuration)
                this.panelMounted = true
            }
            const current = () => !runtime.disposed && this.__colorOwner.isConnected !== false && runtime.panelEpoch === epoch && !this.readOnly
            const pending = (async () => {
                await new Promise(resolve => this.$nextTick(resolve))
                if (!current()) return false
                const panel = this.$refs.panel
                if (!panel) throw Error('Color panel did not mount.')
                mountOwnedMenuPortal(this.__colorOwner, panel)
                const injector = panel.ownerDocument.defaultView.FffAssetInjector
                if (!injector?.registerAssetSurface || !injector.isElementActive(this.__colorOwner)) return false
                const surface = runtime.panelSurface ??= injector.registerAssetSurface(panel)
                const ready = await surface.activate()
                if (current() && ready && injector.isElementActive(this.__colorOwner)) return true
                surface.dispose()
                if (runtime.panelSurface === surface) runtime.panelSurface = null
                return false
            })().catch(error => {
                if (current()) {
                    this.panelLoadError = true
                    this.$dispatch?.('fff-color-load-error', { error })
                }
                return false
            }).finally(() => {
                if (runtime.panelPending === pending) runtime.panelPending = null
                if (runtime.panelEpoch === epoch) this.panelPreparing = false
            })
            runtime.panelPending = pending
            return pending
        },
        cancelColorPanelPreparation({ afterExit = false } = {}) {
            const runtime = colorRuntime(this)
            const epoch = ++runtime.panelEpoch
            const exiting = afterExit || Boolean(runtime.panelExitCleanup)
            runtime.panelExitCleanup?.()
            runtime.panelExitCleanup = null
            runtime.panelIntent?.cancel()
            runtime.panelIntent = null
            const surface = runtime.panelSurface
            const release = () => {
                if (runtime.panelEpoch !== epoch || runtime.panelSurface !== surface) return
                surface?.dispose()
                runtime.panelSurface = null
            }
            const panel = this.$refs.panel
            if (surface && exiting && !runtime.disposed && panel && panel.style.display !== 'none') {
                // Alpine can release nextTick before its leave transition starts.
                // Retain resources until x-show actually commits the hidden state.
                let observer = null, frame = null
                const cleanup = () => {
                    observer?.disconnect()
                    if (frame !== null) cancelAnimationFrame(frame)
                    if (runtime.panelExitCleanup === cleanup) runtime.panelExitCleanup = null
                }
                const check = () => {
                    if (runtime.panelEpoch !== epoch || runtime.panelSurface !== surface) { cleanup(); return }
                    if (!panel.isConnected || panel.style.display === 'none') { cleanup(); release(); return }
                    if (!observer) frame = requestAnimationFrame(check)
                }
                const Observer = panel.ownerDocument.defaultView.MutationObserver
                if (typeof Observer === 'function') {
                    observer = new Observer(check)
                    observer.observe(panel, { attributes: true, attributeFilter: ['style'] })
                }
                runtime.panelExitCleanup = cleanup
                check()
            } else release()
            runtime.panelPending = null
            this.panelPreparing = false
        },
    }
}
