/** Selection commands have one canonical ISO authority shared by map and group actions. */
export function createCountrySelection({ allowed, multiple, readOnly, isDisposed = () => false }) {
    const allowedSet = new Set(allowed)
    function values(state) {
        const candidates = Array.isArray(state) ? state : typeof state === 'string' ? [state] : []
        return [...new Set(candidates.filter(code => allowedSet.has(code)))]
    }
    return {
        readOnly,
        selectedCodes() { return values(this.state) },
        selected(code) { return values(this.state).includes(code) },
        toggleCountry(code, keyboard = false) {
            if (isDisposed() || this.readOnly || !keyboard && this.dragged || !allowedSet.has(code)) { this.dragged = false; return }
            if (!multiple) { this.state = this.state === code ? null : code; return }
            const selected = new Set(values(this.state))
            if (selected.has(code)) selected.delete(code)
            else selected.add(code)
            this.state = [...selected]
        },
        groupSelected(codes) { const group = values(codes), selected = new Set(values(this.state)); return group.length > 0 && group.every(code => selected.has(code)) },
        groupPartiallySelected(codes) { const group = values(codes), selected = new Set(values(this.state)); const count = group.filter(code => selected.has(code)).length; return count > 0 && count < group.length },
        groupCount(codes) { const group = values(codes), selected = new Set(values(this.state)); return `${group.filter(code => selected.has(code)).length} / ${group.length}` },
        toggleGroup(codes) {
            if (isDisposed() || this.readOnly || !multiple) return
            const group = values(codes), selected = new Set(values(this.state))
            if (group.length === 0) return
            const remove = group.every(code => selected.has(code))
            for (const code of group) { if (remove) selected.delete(code); else selected.add(code) }
            this.state = [...selected]
        },
    }
}
