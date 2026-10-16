import { resolveAssetUrl } from '../assets/asset-url.js'
import { componentAssetScope, directAssetBatches } from '../assets/component-scope.js'

import { factoryIdentity, sameFactoryIdentity } from './factory-contract.js'
import { readReferencedPlan } from '../assets/plan-dictionary.js'
export { factoryIdentity, sameFactoryIdentity } from './factory-contract.js'

/** SSR owns the installed module identity. A module declaration may confirm it,
 * but cannot select another package/component or silently change its ABI.
 */
export function resolveModuleFactoryIdentity(element, component, entry, module) {
    const url = resolveAssetUrl(entry)
    const scope = componentAssetScope(element, component)
    const batches = directAssetBatches(scope)
    let declared = null
    let hasInstalledOwners = false
    for (const batch of batches) {
        const projection = readReferencedPlan(batch)
        let owners = projection?.factoryOwners
        if (!owners) {
            const raw = batch.getAttribute('data-fff-factory-owners')
            if (raw === null) continue
            if (raw.length > 131072) throw new Error('FLEX factory owner metadata exceeds its budget.')
            try { owners = JSON.parse(raw) } catch { throw new Error('Invalid FLEX factory owner metadata.') }
            if (!owners || typeof owners !== 'object' || Array.isArray(owners) || Object.keys(owners).length > 128) throw new Error('Invalid FLEX factory owner metadata.')
        }
        hasInstalledOwners = true
        for (const [ownerUrl, identity] of Object.entries(owners)) {
            if (resolveAssetUrl(ownerUrl) !== url) continue
            const next = factoryIdentity({ ...identity, module: url })
            if (declared && !sameFactoryIdentity(declared, next)) throw new Error('Conflicting FLEX factory owner boundaries.')
            declared = next
        }
    }
    if (hasInstalledOwners && !declared) throw new Error('Module URL is not declared by this FLEX owner boundary.')
    // Compatibility imports have URL ownership, never last-registration-wins.
    const identity = declared ?? factoryIdentity({ package: 'legacy/registered', component: component ?? 'unscoped', export: 'default', abi: 1, module: url })
    if (module.fffFactoryIdentity !== undefined) {
        const exported = factoryIdentity({ ...module.fffFactoryIdentity, module: url })
        if (declared && !sameFactoryIdentity(identity, exported)) throw new Error('Module factory declaration disagrees with the installed owner.')
        if (!declared) return exported
    }
    return identity
}
