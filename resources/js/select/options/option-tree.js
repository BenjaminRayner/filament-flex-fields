import { renderCatalogOption } from './catalog-presentation.js'
import { defaultOptionValue } from './option-collection.js'
import { normalizeSearchQuery } from '../../core/search-normalize.js'

export const isHeadlessOptionGroup = option => option != null && typeof option === 'object' && Array.isArray(option.options)
export const headlessOptionIsDisabled = option => Boolean(option?.disabled || option?.isDisabled)
export const headlessOptionValue = option => String(defaultOptionValue(option))
export function headlessOptionLabelHtml(option, context = 'dropdown') {
    if (option?.fffCatalogRender) return renderCatalogOption(option, context)
    return String(context === 'trigger' ? (option?.triggerLabel ?? option?.label ?? option?.value ?? '')
        : (option?.label ?? option?.name ?? option?.value ?? ''))
}

const limits = () => ({remaining: 100000, ancestors: new Set()})
function enter(nodes, depth, budget) {
    if (depth > 32) throw new RangeError('Select option groups exceed the maximum depth of 32.')
    if (budget.ancestors.has(nodes)) throw new TypeError('Select option groups contain a cycle.')
    budget.ancestors.add(nodes)
}
function count(budget) { if (--budget.remaining < 0) throw new RangeError('Select option tree exceeds the node budget.') }

/** Bounded generator avoids recursive spread/argument limits for large groups. */
export function* optionLeaves(options, budget = limits(), depth = 0) {
    if (!Array.isArray(options)) return
    enter(options, depth, budget)
    try {
        for (const option of options) {
            count(budget)
            if (isHeadlessOptionGroup(option)) yield* optionLeaves(option.options, budget, depth + 1)
            else yield option
        }
    } finally { budget.ancestors.delete(options) }
}
export const flattenHeadlessOptions = options => [...optionLeaves(options)]
export function findHeadlessOptionRecord(options, value) {
    const key = String(value)
    for (const option of optionLeaves(options)) if (headlessOptionValue(option) === key) return option
    return null
}
function matches(option, query, label, fields) {
    return (fields.includes('label') && normalizeSearchQuery(option?.fffCatalogRender ? option.label : label(option)).includes(query))
        || (fields.includes('description') && normalizeSearchQuery(String(option?.description ?? '')).includes(query))
        || (fields.includes('value') && normalizeSearchQuery(headlessOptionValue(option)).includes(query))
}

/** Preserve rich record identity and metadata; only group containers are projected. */
export function filterHeadlessOptionTree(options, query, label = headlessOptionLabelHtml, searchableOptionFields = ['label']) {
    if (!Array.isArray(options)) return []
    const term = normalizeSearchQuery(String(query ?? '').trim())
    if (!term) return options.slice()
    // An explicit empty array means searchLabels(false)+searchValues(false).
    // Only an absent/invalid configuration receives the default label policy.
    const fields = Array.isArray(searchableOptionFields) ? searchableOptionFields : ['label']
    const budget = limits()
    const visit = (nodes, depth, inherited = false) => {
        enter(nodes, depth, budget)
        try {
            const output = []
            for (const node of nodes) {
                count(budget)
                const matched = inherited || matches(node, term, label, fields)
                if (isHeadlessOptionGroup(node)) {
                    const children = visit(node.options, depth + 1, matched)
                    if (children.length || inherited) output.push({...node, options: children})
                } else if (matched) output.push(node)
            }
            return output
        } finally { budget.ancestors.delete(nodes) }
    }
    return visit(options, 0)
}

/** Leaf budget remains independent of group/header count. */
export function limitHeadlessOptionTree(options, limit) {
    if (!Array.isArray(options)) return []
    const max = Number(limit)
    if (!Number.isFinite(max) || max <= 0) return options
    let remaining = Math.ceil(max)
    const budget = limits()
    const visit = (nodes, depth) => {
        enter(nodes, depth, budget)
        try {
            const result = []
            for (const node of nodes) {
                if (remaining <= 0) break
                count(budget)
                if (isHeadlessOptionGroup(node)) {
                    const children = visit(node.options, depth + 1)
                    if (children.length) result.push({...node, options: children})
                } else { result.push(node); remaining-- }
            }
            return result
        } finally { budget.ancestors.delete(nodes) }
    }
    return visit(options, 0)
}
