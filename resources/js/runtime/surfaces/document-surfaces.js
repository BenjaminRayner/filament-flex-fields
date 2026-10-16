const registries = new WeakMap()

/** A document owns its surface adapter; no component or actor data crosses documents. */
export const customSurfacesFor = document => document ? registries.get(document)?.at(-1) ?? null : null

export function attachCustomSurfaces(document, registry) {
    const stack = registries.get(document) ?? []
    if (stack.length >= 16) throw Error('FLEX document surface adapter budget exhausted.')
    stack.push(registry); registries.set(document, stack)
    return () => {
        const index = stack.indexOf(registry)
        if (index >= 0) stack.splice(index, 1)
        if (!stack.length && registries.get(document) === stack) registries.delete(document)
    }
}
