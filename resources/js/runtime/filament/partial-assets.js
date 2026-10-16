import { isAssetElementActive } from '../lifecycle/asset-activation.js'
import { preserveMountedFactoryReadiness } from '../../core/alpine-readiness.js'
import { admitFragmentPlans } from '../assets/plan-dictionary.js'

/** Filament's partial renderer calls Alpine.morph directly, without Livewire morph hooks.
 * Prepare only FLEX metadata in the response before that renderer's microtask runs.
 * This does not render, acquire detached consumers, or override native partial HTML.
 */
export function prepareFilamentPartials({ message, payload, document, surfaces, presentation = null, primePreparedStyles, onDeferredPanel }) {
    if (payload?.effects?.html || !payload?.effects?.partials || !message?.component?.el) return
    const owner = message.component.el
    const candidates = [...owner.querySelectorAll('[wire\\:partial]')]
        .filter(node => node.closest('[wire\\:id]') === owner)
    for (const [name, html] of Object.entries(payload.effects.partials)) {
        if (typeof html !== 'string' || !html.includes('data-fff-')) continue
        const targets = candidates.filter(node => node.getAttribute('wire:partial') === name)
        if (targets.length !== 1) continue
        const live = targets[0]
        const container = document.createElement(live.parentElement?.tagName ?? 'div')
        container.innerHTML = html
        const incoming = container.firstElementChild
        if (!incoming) continue
        admitFragmentPlans(container, document)
        const panel = live.closest('[data-fff-deferred-panel]')
        if (panel && !live.querySelector('[data-fff-asset-batch]') && incoming.querySelector('[data-fff-asset-batch]')) {
            // The user may leave before this response arrives. Preserve the
            // native loading presentation even while hidden; its adapter waits
            // for activation and does not acquire the dormant field assets.
            onDeferredPanel?.(panel)
        }
        if (isAssetElementActive(live, { document, surfaces })) {
            primePreparedStyles(incoming, batch => isAssetElementActive(batch, { document, surfaces, allowDetached: true }))
        }
        // Stable native keys identify retained factories; new descendants keep
        // their regular lazy activation and modal readiness barrier.
        const retained = new Map([live, ...live.querySelectorAll('[wire\\:key]')]
            .filter(node => node.hasAttribute('wire:key')).map(node => [node.getAttribute('wire:key'), node]))
        for (const next of [incoming, ...incoming.querySelectorAll('[wire\\:key]')]) {
            const previous = retained.get(next.getAttribute('wire:key'))
            if (!previous) continue
            preserveMountedFactoryReadiness(previous, next)
            presentation?.preserveMorph(previous, next)
        }
        payload.effects.partials[name] = container.innerHTML
    }
}
