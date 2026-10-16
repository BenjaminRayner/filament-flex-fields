import { admitFragmentPlans } from '../assets/plan-dictionary.js'

/** Final Livewire HTML is opaque to this adapter: parse only dictionary-bearing
 * fragments for admission, then let the framework perform its own native morph.
 */
export function prepareLivewirePlanEffects(payload, document) {
    const html = payload?.effects?.html
    const fragments = [html, ...(Array.isArray(payload?.effects?.islandFragments) ? payload.effects.islandFragments : [])]
    for (const fragment of fragments) {
        if (typeof fragment !== 'string' || !fragment.includes('data-fff-plan-id=')) continue
        const template = document.createElement('template')
        template.innerHTML = fragment
        admitFragmentPlans(template.content, document)
    }
}
