export function dualKeyboard(event, { root, pane, key, model, presentation, commit }) {
    if (!model.interactive() || !pane) return
    const items = model.items(pane), index = items.findIndex(item => item.value === key)
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
        event.preventDefault(); model.all(pane); presentation.render(); return
    }
    if (event.key === 'Escape') { event.preventDefault(); model.clear(pane); presentation.render(); return }
    if (event.altKey && pane === 'selected' && ['ArrowUp', 'ArrowDown'].includes(event.key)) {
        event.preventDefault(); if (model.move(key, event.key === 'ArrowUp' ? -1 : 1)) { commit(); presentation.focus(pane, key) } return
    }
    let target
    if (event.key === 'Home') target = items[0]
    if (event.key === 'End') target = items.at(-1)
    if (event.key === 'ArrowDown') target = items[Math.min(items.length - 1, index + 1)]
    if (event.key === 'ArrowUp') target = items[Math.max(0, index - 1)]
    if (target) {
        event.preventDefault()
        if (event.shiftKey) { model.addMark(pane, key); model.mark(pane, target.value, { range: true, additive: !!event.ctrlKey }) }
        presentation.focus(pane, target.value); return
    }
    if (event.key === ' ' && key !== undefined) {
        event.preventDefault(); model.mark(pane, key, { additive: true, range: event.shiftKey }); presentation.render(); return
    }
    const rtl = root.ownerDocument.defaultView.getComputedStyle(root).direction === 'rtl'
    const towardOther = pane === 'available' ? (rtl ? 'ArrowLeft' : 'ArrowRight') : (rtl ? 'ArrowRight' : 'ArrowLeft')
    if (event.key === 'Enter' || event.key === towardOther) {
        event.preventDefault()
        const keys = model.marked[pane].size ? [...model.marked[pane]] : key === undefined ? [] : [key]
        const count = model.transfer(pane, keys)
        if (count) { commit(count); presentation.focus(pane === 'available' ? 'selected' : 'available', keys[0]) }
    }
}
