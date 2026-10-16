/** Locked identities retain their absolute slot while movable identities change order. */
export function planTodoOrder(items, from, to) {
    if (!Number.isInteger(from) || !Number.isInteger(to) || from === to ||
        from < 0 || to < 0 || from >= items.length || to >= items.length || items[from].disabled) return null
    const direction = Math.sign(to - from)
    while (items[to]?.disabled) to += direction
    if (to < 0 || to >= items.length) return null
    const movable = items.filter(item => !item.disabled)
    const start = movable.indexOf(items[from]), target = movable.indexOf(items[to])
    movable.splice(target, 0, movable.splice(start, 1)[0])
    let index = 0
    return items.map(item => item.disabled ? item : movable[index++])
}

/** Choose a valid slot from measured movable rows, including the first and last. */
export function nearestTodoSlot(slots, center) {
    let nearest = null, distance = Infinity
    for (const slot of slots) {
        if (slot.disabled) continue
        const delta = Math.abs(center - (slot.top + slot.height / 2))
        if (delta < distance) {
            nearest = slot
            distance = delta
        }
    }
    return nearest
}
