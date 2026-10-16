/** Weak ancestor lookup; updating a subtree visits only owners previously indexed there. */
export function createOwnerTreeIndex() {
    let ancestors = new WeakMap()
    const records = new Map()
    const pathFor = element => {
        const path = []
        for (let node = element; node; node = node.parentNode ?? node.parentElement) path.push(node)
        return path
    }
    const remove = id => {
        const record = records.get(id)
        if (!record) return
        records.delete(id)
        for (const node of record.path) {
            const members = ancestors.get(node)
            members?.delete(id)
            if (!members?.size) ancestors.delete(node)
        }
    }
    return {
        bind(id, element) {
            if (!element) { remove(id); return }
            const previous = records.get(id)
            if (previous?.element === element) {
                let node = element, index = 0
                while (node && node === previous.path[index]) {
                    node = node.parentNode ?? node.parentElement
                    index++
                }
                if (!node && index === previous.path.length) return
            }
            const path = pathFor(element)
            remove(id)
            for (const node of path) {
                let members = ancestors.get(node)
                if (!members) { members = new Set(); ancestors.set(node, members) }
                members.add(id)
            }
            records.set(id, { element, path })
        },
        remove,
        within: root => [...(ancestors.get(root) ?? [])],
        element: id => records.get(id)?.element ?? null,
        ids: () => [...records.keys()],
        size: () => records.size,
        clear() { records.clear(); ancestors = new WeakMap() },
    }
}
