/** IDs are literal strings, including dotted Livewire/modal state paths. */
export function findContainedId(root, id) {
    if (!root || typeof id !== 'string') return null
    const candidate = root.ownerDocument?.getElementById(id)
    return candidate && root.contains(candidate) ? candidate : null
}
