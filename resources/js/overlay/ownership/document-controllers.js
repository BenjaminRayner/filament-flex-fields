import { createFlexDropdownRegistry } from './controller-registry.js'

// The DOM document is the lifetime boundary, including isolated browser frames.
const key = Symbol.for('filament-flex-fields.dropdown-ownership.v4')
let serverContext = null

export function documentDropdownOwnership(document) {
    if (!document || (typeof document !== 'object' && typeof document !== 'function')) {
        return serverContext ??= { registry: createFlexDropdownRegistry(), ids: new WeakMap(), sequence: 0 }
    }
    // Independent lazy entry bundles must share the same ownership service.
    if (!document[key]) Object.defineProperty(document, key, { value: { registry: createFlexDropdownRegistry(), ids: new WeakMap(), sequence: 0 } })
    return document[key]
}

export function documentDropdownControllers(document) {
    return documentDropdownOwnership(document).registry
}
