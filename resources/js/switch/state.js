export function checkedValue(value) {
    return value === true || value === 1 || value === '1'
}

export function colorClasses(checked, on, off) {
    const selected = new Set(checked ? on : off)
    return Object.fromEntries([...new Set([...on, ...off])].map(name => [name, selected.has(name)]))
}
