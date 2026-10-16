import { defaultOptionValue, optionKey } from './option-collection.js'

export const HEADLESS_DROPDOWN_ROW_HEIGHTS = { option: 36, create: 36, section: 24, 'group-header': 24, separator: 17 }
const valueOf = (option) => optionKey(defaultOptionValue(option))
const isGroup = (node) => node != null && Array.isArray(node.options)
const rowKey = (...parts) => JSON.stringify(parts)

/** Group identity does not contain its filtered children or the current query. */
export function buildHeadlessDropdownRows(options, {
    multiple = false, keepSelectedOptionsInDropdown = false,
    isOptionSelected = () => false, withSeparators = true,
} = {}, ancestry = []) {
    const rows = []
    let groups = 0
    for (const node of options ?? []) {
        if (!isGroup(node)) {
            const value = valueOf(node)
            if (multiple && !keepSelectedOptionsInDropdown && isOptionSelected(value)) continue
            rows.push({ type: 'option', option: node, key: rowKey(...ancestry, 'option', value) })
            continue
        }
        const label = String(node.label ?? '')
        const path = [...ancestry, 'group', label]
        const children = buildHeadlessDropdownRows(node.options, { multiple, keepSelectedOptionsInDropdown, isOptionSelected, withSeparators }, path)
        const groupOptions = flattenHeadlessDropdownRowOptions(children)
        if (!groupOptions.length) continue
        if (withSeparators && groups++) rows.push({ type: 'separator', key: rowKey(...path, 'separator') })
        rows.push({ type: 'group', label, options: groupOptions, rows: children, key: rowKey(...path) })
    }
    return rows
}

export function flattenHeadlessDropdownRowsForVirtualization(rows) {
    const flat = []
    for (const row of rows ?? []) {
        if (row.type === 'group') {
            flat.push({ type: 'group-header', label: row.label, key: `${row.key}:header`, height: HEADLESS_DROPDOWN_ROW_HEIGHTS['group-header'] })
            const children = row.rows ?? (row.options ?? []).map((option) => ({ type: 'option', option, key: rowKey(row.key, 'option', valueOf(option)) }))
            flat.push(...flattenHeadlessDropdownRowsForVirtualization(children))
        } else flat.push({ ...row, height: HEADLESS_DROPDOWN_ROW_HEIGHTS[row.type] ?? HEADLESS_DROPDOWN_ROW_HEIGHTS.option })
    }
    return flat
}

export function flattenHeadlessDropdownRowOptions(rows) {
    const options = []
    for (const row of rows ?? []) {
        if (row.type === 'group') options.push(...(row.options ?? []))
        else if (row.option != null) options.push(row.option)
    }
    return options
}

function retainValues(tree, allowed) {
    const result = []
    for (const node of tree ?? []) {
        if (!isGroup(node)) { if (allowed.has(valueOf(node))) result.push(node); continue }
        const options = retainValues(node.options, allowed)
        if (options.length) result.push({ ...node, options })
    }
    return result
}

/** Smart sections keep the same group projection and selected-item policy as normal lists. */
export function buildSmartDropdownRows(sections, optionTree, config) {
    const rows = []
    for (const section of sections) {
        if (section.type === 'create') {
            rows.push({ type: 'create', key: rowKey('create', section.value), value: section.value, label: section.label })
            continue
        }
        const tree = section.type === 'options'
            ? retainValues(optionTree, new Set((section.options ?? []).map(valueOf)))
            : section.options
        const children = buildHeadlessDropdownRows(tree, config, ['section', section.type])
        if (!children.length) continue
        if (section.label && section.type !== 'options') rows.push({ type: 'section', key: rowKey('section', section.type), label: section.label })
        rows.push(...children)
    }
    return rows
}
