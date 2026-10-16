export function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
    return `todo-${Date.now()}-${Math.random().toString(16).slice(2)}`
}
