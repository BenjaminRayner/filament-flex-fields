const RUNTIME_KEY = Symbol.for('janczakb.filament-flex-fields.runtime.abi-1')

/** Compatibility bridge while legacy IIFE entrypoints migrate to one ESM graph. */
export function getRuntimeState() {
    globalThis[RUNTIME_KEY] ??= {
        preparedModules: new Set(),
        imports: new Map(),
        factories: new Map(),
        registeredNames: new Set(),
        alpine: null,
        alpineListenerPending: false,
        gate: null,
        fallbackStore: null,
    }

    // Existing bundles may have initialized the transitional ABI before this entry.
    globalThis[RUNTIME_KEY].preparedModules ??= new Set()
    return globalThis[RUNTIME_KEY]
}
