const identity = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?![\s\S])/
const assertName = (value, limit) => {
    if (typeof value !== 'string' || value.length > limit || !identity.test(value)) {
        throw new TypeError('Invalid bounded FLEX appearance name.')
    }
    return value
}

/** Pure owner-derived names; no listeners, global counters, retained DOM or cache. */
export function createAppearanceNamespace(namespace) {
    assertName(namespace, 48)
    const name = (kind, local) => `fff-ext-${namespace}--${kind}-${assertName(local, 64)}`
    return Object.freeze({
        namespace,
        token: local => `--${name('token', local)}`,
        font: local => name('font', local),
        keyframe: local => name('motion', local),
        svgId(instance, local) {
            assertName(local, 64)
            if (typeof instance !== 'string' || !instance || /[\u0000-\u001f\u007f]/.test(instance)) {
                throw new TypeError('SVG identity requires the rendered instance owner key.')
            }
            if (instance.length > 512) throw new TypeError('SVG instance identity exceeds its budget.')
            const bytes = new TextEncoder().encode(instance)
            if (bytes.length > 512 || new TextDecoder('utf-8', { fatal: true, ignoreBOM: true }).decode(bytes) !== instance) {
                throw new TypeError('SVG instance identity requires bounded valid UTF-8.')
            }
            const encoded = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('')
            return `fff-ext-${namespace}--svg-${encoded}--${local}`
        },
    })
}
