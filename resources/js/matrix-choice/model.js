import { choiceIdentity } from '../choice-lists/selection.js'

export function createMatrixSelection(config) {
    const rows = new Map(config.rows.map(row => [String(row.value),row]))
    const columns = new Map(config.columns.map(column => [String(column.value),column]))
    const multiple = config.mode === 'checkbox'
    const values = (state,row) => {
        const value = state && !Array.isArray(state) && typeof state === 'object' && Object.hasOwn(state,row) ? state[row] : null
        return new Set((multiple ? (Array.isArray(value) ? value : []) : [value]).map(choiceIdentity).filter(key => key !== null && columns.has(key)))
    }
    const snapshot = (state, disabled = false) => {
        const selected = new Map([...rows.keys()].map(row => [row,values(state,row)]))
        const blockedRows = new Set([...rows].filter(([,row]) => disabled || row.disabled).map(([key]) => key))
        const blockedColumns = new Set([...columns].filter(([,column]) => column.disabled).map(([key]) => key))
        const blockedCells = new Map([...rows.keys()].map(key => [key,new Set(config.disabledCells?.[key] ?? [])]))
        for (const rule of config.conditionalDisableRules ?? []) {
            if (!rule.when_columns.some(column => selected.get(rule.when_row)?.has(String(column)))) continue
            if (rule.type === 'row') blockedRows.add(rule.row)
            else blockedCells.get(rule.row)?.add(rule.column)
        }
        const blocked = (row,column) => !rows.has(row) || !columns.has(column) || blockedRows.has(row) || blockedColumns.has(column) || blockedCells.get(row).has(column)
        return {selected,blockedRows,blocked,
            canChange(row,column) {
                if (blocked(row,column)) return false
                const max = rows.get(row).max_selections
                return !multiple || selected.get(row).has(column) || max === null || max === undefined || selected.get(row).size < max
            },
        }
    }
    return {rows,columns,multiple,snapshot,
        change(state,row,column,disabled=false) {
            const view = snapshot(state,disabled)
            if (!view.canChange(row,column)) return state
            const selected = view.selected.get(row)
            if (selected.has(column)) selected.delete(column)
            else selected.add(column)
            return this.prune({...state,[row]:multiple ? [...selected] : column},disabled)
        },
        prune(state,disabled=false) {
            if (disabled || !state || typeof state !== 'object' || Array.isArray(state)) return state
            let next = state
            // Removing selections is monotone; even cyclic dependency rules terminate.
            for (let pass=0;pass<=rows.size * Math.max(1,columns.size);pass++) {
                const view = snapshot(next), patches = []
                for (const [row,selected] of view.selected) {
                    const rejected = new Set([...selected].filter(column => view.blocked(row,column)))
                    if (!rejected.size) continue
                    const original = next[row]
                    patches.push([row,multiple ? original.filter(column => !rejected.has(choiceIdentity(column))) : null])
                }
                if (!patches.length) break
                next = {...next,...Object.fromEntries(patches)}
            }
            return next
        },
    }
}
