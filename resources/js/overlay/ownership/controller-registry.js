/**
 * One exclusivity registry; controllers and anchored surfaces share an owner.
 * Registration identity is independent of stable DOM identity. Each owner can
 * expose a host controller and a surface lifecycle without replacing the other.
 */
export function createFlexDropdownRegistry({ onControllerError = () => {} } = {}) {
    const owners = new Map()
    let openGeneration = 0
    const closing = new Set()
    return {
        register(ownerId, factory, { layer = 'controller' } = {}) {
            if (!ownerId || typeof factory !== 'function') return () => {}
            let layers = owners.get(ownerId)
            if (!layers) { layers = new Map(); owners.set(ownerId, layers) }
            const registration = { factory }
            layers.set(layer, registration)
            let released = false
            return () => {
                if (released) return
                released = true
                if (owners.get(ownerId) !== layers || layers.get(layer) !== registration) return
                layers.delete(layer)
                if (!layers.size) owners.delete(ownerId)
            }
        },
        unregister(ownerId) { owners.delete(ownerId) },
        openExclusive(ownerId) {
            if (!ownerId) return null
            const generation = ++openGeneration
            let cancelled = false
            const retained = new Set([ownerId])
            let current = ownerId
            // Opening a nested select retains its picker ancestor, never siblings.
            while (current) {
                let parent = null
                for (const { factory } of owners.get(current)?.values() ?? []) {
                    try { parent = factory()?.parentOwnerId; if (parent) break } catch { /* Unavailable controller. */ }
                }
                if (!parent || retained.has(parent)) break
                retained.add(parent); current = parent
            }
            // A close callback may unregister or replace another layer synchronously.
            const snapshot = [...owners].map(([id, layers]) => [id, layers, [...layers]])
            for (const [otherId, layers, registrations] of snapshot) {
                if (generation !== openGeneration) break
                if (retained.has(otherId) || owners.get(otherId) !== layers) continue
                for (const [layer, registration] of registrations) {
                    if (generation !== openGeneration) break
                    if (owners.get(otherId) !== layers || layers.get(layer) !== registration) continue
                    if (closing.has(registration)) continue
                    closing.add(registration)
                    try {
                        const controller = registration.factory()
                        if (generation === openGeneration && controller?.isOpen?.()
                            && generation === openGeneration) controller.close()
                    } catch (error) {
                        try { onControllerError(error, otherId) } catch { /* Continue closing siblings. */ }
                    } finally { closing.delete(registration) }
                }
            }
            // Also arbitrate pending opens: a slower catalog/import must never
            // displace a newer picker when its asynchronous work completes.
            return {
                current: () => !cancelled && generation === openGeneration,
                cancel() { cancelled = true },
            }
        },
        size: () => owners.size,
    }
}
