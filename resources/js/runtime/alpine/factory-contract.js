import { resolveAssetUrl } from '../assets/asset-url.js'

const ID = /^[a-z][a-z0-9]*(?:-[a-z0-9]+)*(?![\s\S])/
const PACKAGE = /^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?\/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?(?![\s\S])/
const EXPORT = /^(?:default|[A-Za-z_$][\w$]*)(?![\s\S])/

/** Factory ownership is separate from the global Alpine alias and module cache. */
export function factoryIdentity(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || !['package', 'component', 'export', 'abi', 'module'].every(key => Object.hasOwn(value, key))
        || Object.keys(value).some(key => !['package', 'component', 'export', 'abi', 'module'].includes(key))
        || typeof value.package !== 'string' || value.package.length > 192 || !PACKAGE.test(value.package)
        || typeof value.component !== 'string' || value.component.length > 96 || !ID.test(value.component)
        || typeof value.export !== 'string' || value.export.length > 128 || !EXPORT.test(value.export) || ['__proto__', 'constructor', 'prototype'].includes(value.export)
        || value.abi !== 1 || typeof value.module !== 'string' || value.module.length > 4096) {
        throw new Error('Invalid FLEX JavaScript factory identity or unsupported ABI.')
    }
    let module = resolveAssetUrl(value.module)
    // Node compatibility probes import installed source directly. This is an
    // identity only; the browser transport still accepts HTTP(S) resources alone.
    if (!module && typeof document === 'undefined') {
        try { const local = new URL(value.module); if (local.protocol === 'file:') module = local.href } catch { /* Invalid identity. */ }
    }
    if (!module) throw new Error('FLEX factory identity requires an HTTP(S) module URL.')
    return Object.freeze({ package: value.package, component: value.component, export: value.export, abi: value.abi, module })
}

export const sameFactoryIdentity = (a, b) => Boolean(a && b)
    && ['package', 'component', 'export', 'abi', 'module'].every(key => a[key] === b[key])

