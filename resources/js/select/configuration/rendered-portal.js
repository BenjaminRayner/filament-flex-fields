const definitions = new WeakMap()
const identity = /^[a-f0-9]{64}$/

/** Compiled stock presentation only. No document-global field/selection cache. */
export function prepareRenderedSelectPortal(root) {
    const template = root?.children ? [...root.children].find(child => child.tagName === 'TEMPLATE' && child.hasAttribute('data-fff-portal-template-owner'))
        : root?.querySelector?.(':scope > template[data-fff-portal-template-owner]')
    const id = root?.getAttribute?.('data-fff-portal-template-id') ?? template?.getAttribute('data-fff-portal-template-id')
    if (id == null) return () => {}
    if (!identity.test(id)) throw Error('Invalid Select portal identity.')
    let definition = null
    for (let owner = root; owner; owner = owner.parentElement) {
        // Only direct metadata siblings are eligible. A scoped selector still
        // walks the large descendant tree in browsers; never repeat that walk
        // at every ancestor of every field in a 300-owner form.
        const candidates = owner.children ? [] : [...(owner.querySelectorAll?.(`:scope > template[data-fff-portal-template-definition="${id}"], :scope > [data-fff-fragment-dictionary] > template[data-fff-portal-template-definition="${id}"]`) ?? [])]
        for (const child of owner.children ?? []) {
            if (child.tagName === 'TEMPLATE' && child.getAttribute('data-fff-portal-template-definition') === id) candidates.push(child)
            if (child.hasAttribute('data-fff-fragment-dictionary')) {
                for (const definition of child.children) {
                    if (definition.tagName === 'TEMPLATE' && definition.getAttribute('data-fff-portal-template-definition') === id) candidates.push(definition)
                }
            }
        }
        if (candidates.length > 1) throw Error('Conflicting Select portal definitions.')
        if (candidates.length === 1) { definition = candidates[0]; break }
    }
    if (!definition) throw Error('Select portal definition is missing from its fragment.')
    const raw = definition.content?.textContent ?? definition.textContent ?? ''
    if (raw.length > 524288) throw Error('Select portal definition exceeds its budget.')
    const previous = definitions.get(definition)
    if (previous && previous.raw !== raw) throw Error('Conflicting immutable Select portal definition.')
    let html = previous?.html
    if (html === undefined) {
        const value = JSON.parse(raw)
        if (!value || typeof value !== 'object' || Array.isArray(value)
            || Object.keys(value).sort().join(',') !== 'format,html,kind'
            || value.format !== 1 || value.kind !== 'alpine-portal' || typeof value.html !== 'string'
            || value.html.length > 131072
            || !/^\s*<template x-teleport="body">\s*<div\b/.test(value.html)
            || !value.html.trimEnd().endsWith('</template>')) throw Error('Invalid compiled Select portal.')
        html = value.html
        definitions.set(definition, {raw, html})
    }
    if (!template) throw Error('Select portal has no independent owner template.')
    let mounted = false
    return () => {
        if (mounted) return
        // This template is still inert; Alpine's existing x-if/teleport directives
        // initialize its separate DOM only when this owner's first intent opens it.
        template.innerHTML = html
        mounted = true
    }
}
