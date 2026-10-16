import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'

/** Presentation only: native links and Filament still own navigation/actions. */
export function createItemCardInteraction() {
    let root, scope, ready, disposed = false
    const ripples = new Map()
    return {
        init() {
            if (ready || disposed) return ready
            root = this.$el
            scope = createResourceScope()
            ready = (async () => {
                if (!await waitForAssetActivation(root, { signal: scope.signal }) || disposed) return
                scope.listen(root, 'animationend', event => {
                    if (event.animationName === 'fff-item-card-ripple') ripples.get(event.target)?.()
                })
                scope.listen(root, 'click', event => {
                    if (!root.classList.contains('item-card--pressable') || root.disabled || root.getAttribute('aria-disabled') === 'true') return
                    const child = event.target.closest?.('a,button,input,select,textarea,[role="button"],[role="switch"]')
                    if (child && child !== root) return
                    if (root.ownerDocument.defaultView.matchMedia('(prefers-reduced-motion: reduce)').matches) return
                    if (ripples.size >= 4) ripples.values().next().value()
                    const rect = root.getBoundingClientRect()
                    const diameter = Math.max(rect.width, rect.height)
                    if (diameter <= 0) return
                    const circle = root.ownerDocument.createElement('span')
                    circle.className = 'item-card__ripple'
                    const x = event.detail === 0 ? rect.width / 2 : event.clientX - rect.left
                    const y = event.detail === 0 ? rect.height / 2 : event.clientY - rect.top
                    Object.assign(circle.style, { width: `${diameter}px`, height: `${diameter}px`, left: `${x - diameter / 2}px`, top: `${y - diameter / 2}px` })
                    root.appendChild(circle)
                    let cancelTimer
                    const remove = scope.defer(() => {
                        try { circle.remove() } finally { ripples.delete(circle) }
                    })
                    const retire = () => { cancelTimer?.(); remove() }
                    ripples.set(circle, retire)
                    cancelTimer = scope.timeout(retire, 650)
                })
            })().catch(error => { scope.dispose(); ready = null; throw error })
            return ready
        },
        destroy() {
            if (disposed) return
            disposed = true
            scope?.dispose()
            root = null
        },
    }
}
