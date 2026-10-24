import { getRuntimeState } from '../runtime-state.js'

const identity = /^[a-f0-9]{64}$/
const record = value => value !== null && typeof value === 'object' && !Array.isArray(value)
const components = new Set(['flex-textarea', 'select-catalog', 'phone-catalog', 'select-configuration'])
const maximumBytes = component => component === 'flex-textarea' ? 65536 : 4 * 1024 * 1024
const forbidden = new Set(['__proto__', 'prototype', 'constructor'])
const clone = value => Array.isArray(value) ? value.map(clone)
    : record(value) ? Object.fromEntries(Object.entries(value).map(([key, child]) => [key, clone(child)])) : value
const validate = (value, depth = 0) => {
    if (depth > 12) throw Error('Control configuration exceeds its nesting budget.')
    if (value && typeof value === 'object') {
        for (const [key, child] of Object.entries(value)) {
            if (forbidden.has(key)) throw Error('Invalid control configuration property.')
            validate(child, depth + 1)
        }
        Object.freeze(value)
    }
}

function dictionary(document) {
    const state = getRuntimeState()
    state.controlConfigurations ??= new WeakMap()
    let catalog = state.controlConfigurations.get(document)
    if (!catalog) { catalog = { values: new Map(), bytes: 0 }; state.controlConfigurations.set(document, catalog) }
    return catalog
}

function admit(catalog, id, raw) {
    if (!identity.test(id ?? '') || typeof raw !== 'string' || raw.length > 4 * 1024 * 1024) throw Error('Invalid control configuration identity or size.')
    const previous = catalog.values.get(id)
    if (previous) {
        if (previous.raw !== raw) throw Error('Conflicting immutable control configuration.')
        catalog.values.delete(id); catalog.values.set(id, previous)
        return previous
    }
    const value = JSON.parse(raw)
    if (!record(value) || Object.keys(value).sort().join(',') !== 'component,format,kind,options'
        || value.format !== 1 || value.kind !== 'control-configuration' || !components.has(value.component) || raw.length > maximumBytes(value.component)
        || !record(value.options) || ['initialState', 'statePath', 'configurationId'].some(key => Object.hasOwn(value.options, key))) {
        throw Error('Invalid control configuration definition.')
    }
    validate(value.options)
    const bytes = raw.length * 2
    while (catalog.values.size && (catalog.values.size >= 512 || catalog.bytes + bytes > 8 * 1024 * 1024)) {
        const key = catalog.values.keys().next().value
        catalog.bytes -= catalog.values.get(key).bytes
        catalog.values.delete(key)
    }
    const admitted = { raw, component: value.component, options: value.options, bytes }
    catalog.values.set(id, admitted); catalog.bytes += bytes
    return admitted
}

/** Admit response metadata before native morph; no field state or DOM is retained. */
export function admitControlConfigurations(root, document = root?.ownerDocument) {
    const definitions = root.querySelectorAll('[data-fff-control-configuration-definition]')
    if (definitions.length > 512) throw Error('Control configuration fragment exceeds its definition budget.')
    const catalog = dictionary(document)
    let bytes = 0
    for (const definition of definitions) {
        const raw = definition.content?.textContent ?? definition.textContent ?? ''
        bytes += raw.length * 2
        if (bytes > 8 * 1024 * 1024) throw Error('Control configuration fragment exceeds its byte budget.')
        admit(catalog, definition.getAttribute('data-fff-control-configuration-definition'), raw)
    }
}

export function resolveControlConfiguration(configuration, root) {
    if (!configuration || !Object.hasOwn(configuration, 'configurationId')) return configuration
    const id = configuration.configurationId
    if (!root?.ownerDocument || !identity.test(id ?? '') || typeof configuration.statePath !== 'string'
        || Object.keys(configuration).some(key => !['configurationId', 'statePath', 'initialState'].includes(key))) {
        throw Error('Invalid owner-local control configuration reference.')
    }
    const options = resolveControlMetadata(id, 'flex-textarea', root)
    return { ...clone(options), statePath: configuration.statePath, initialState: configuration.initialState }
}

/** Immutable public metadata only; callers construct their own mutable owner state. */
export function resolveControlMetadata(id, component, root) {
    const document = root?.ownerDocument ?? (root?.nodeType === 9 ? root : null)
    if (!document || !identity.test(id ?? '') || !components.has(component)) throw Error('Invalid control metadata reference.')
    const catalog = dictionary(document)
    let admitted = catalog.values.get(id)
    const tree = root.getRootNode()
    if (!admitted || tree !== document) {
        const definitions = tree.querySelectorAll(`[data-fff-control-configuration-definition="${id}"]`)
        if (definitions.length > 512) throw Error('Control configuration fragment exceeds its definition budget.')
        for (const definition of definitions) admitted = admit(catalog, id, definition.content?.textContent ?? definition.textContent ?? '')
    }
    if (!admitted || admitted.component !== component) throw Error('Missing or mismatched control metadata definition.')
    return admitted.options
}

export function materializeControlMetadata(id, component, root) {
    return clone(resolveControlMetadata(id, component, root))
}
