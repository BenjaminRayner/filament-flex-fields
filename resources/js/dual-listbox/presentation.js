import { virtualWindow } from './model.js'

/** Rows are keyed and only the bounded visible window is retained in the DOM. */
export function createDualPresentation(root, config, model) {
    const document = root.ownerDocument
    const status = root.querySelector('[data-dual-status]')
    let activeDrop = null
    const panes = new Map(['available', 'selected'].map(pane => {
        const panel = root.querySelector(`[data-dual-pane="${pane}"]`), list = panel.querySelector('[data-dual-list]')
        return [pane, { panel, list, template: panel.querySelector('template'), top: list.querySelector('[data-dual-spacer="top"]'), bottom: list.querySelector('[data-dual-spacer="bottom"]'),
            search: panel.querySelector('[data-dual-search]'), markAll: panel.querySelector('[data-dual-action="select-all"]'), clear: panel.querySelector('[data-dual-action="clear"]'),
            empty: list.querySelector('[data-dual-empty]'), count: panel.querySelector('[data-dual-count]'), marks: panel.querySelector('[data-dual-highlight-count]'),
            nodes: new Map([...list.querySelectorAll('[data-dual-row]')].map(row => [row.dataset.value, capture(row)])), focused: null }]
    }))
    function capture(node) { return { node, indicator: node.querySelector('.fff-checklist-indicator'), label: node.querySelector('[data-dual-label]'), description: node.querySelector('[data-dual-description]'), lock: node.querySelector('[data-dual-lock]'), up: node.querySelector('[data-dual-action="up"]'), down: node.querySelector('[data-dual-action="down"]') } }
    const buttons = [...root.querySelectorAll('.fff-dual-listbox__toolbar button')]
    const visibleHeight = list => list.clientHeight || 256
    const token = value => encodeURIComponent(value)
    const api = {
        panes,
        render(side) {
            for (const pane of side ? [side] : ['available', 'selected']) {
                const p = panes.get(pane), items = model.items(pane), height = visibleHeight(p.list)
                // Query changes and removals can shrink an already scrolled list.
                p.list.scrollTop = Math.min(p.list.scrollTop, Math.max(0, items.length * config.rowHeight - height))
                const window = virtualWindow(items, p.list.scrollTop, height, config.rowHeight, config.virtualThreshold)
                p.list.classList.toggle('has-more', p.list.scrollTop + height + 1 < items.length * config.rowHeight)
                p.list.classList.toggle('has-scrolled', p.list.scrollTop > 1)
                const visible = new Set(window.items.map(item => item.value))
                for (const [key, row] of p.nodes) if (!visible.has(key)) { row.node.remove(); p.nodes.delete(key) }
                let cursor = p.top.nextSibling
                window.items.forEach((item, i) => {
                    let row = p.nodes.get(item.value)
                    if (!row) { row = capture(p.template.content.firstElementChild.cloneNode(true)); row.node.dataset.value = item.value; p.nodes.set(item.value, row) }
                    if (!row.prepared) {
                        row.node.id = `${root.dataset.dualOwner}-${pane}-${token(item.value)}`
                        row.label.textContent = item.label
                        row.description.textContent = item.description ?? ''; row.description.hidden = !item.description
                        row.node.title = item.description ? `${item.label}\n${item.description}` : item.label
                        row.lock.hidden = !item.disabled
                        row.node.setAttribute('aria-disabled', String(!model.interactive() || (pane === 'available' && !!item.disabled)))
                        row.prepared = true
                    }
                    const chosen = model.marked[pane].has(item.value)
                    row.node.setAttribute('aria-selected', String(chosen))
                    row.node.setAttribute('aria-posinset', String(window.start + i + 1)); row.node.setAttribute('aria-setsize', String(items.length))
                    const focusKey = visible.has(p.focused) ? p.focused : window.items[0]?.value
                    row.node.setAttribute('aria-disabled', String(!model.interactive() || (pane === 'available' && !!item.disabled)))
                    row.node.tabIndex = model.interactive() && item.value === focusKey ? 0 : -1
                    row.node.classList.toggle('is-selected', chosen)
                    if (row.indicator.dataset.checked !== String(chosen)) row.indicator.dataset.checked = String(chosen)
                    row.node.classList.toggle('is-locked', !!item.disabled)
                    row.node.classList.toggle('is-disabled', !model.interactive() || (pane === 'available' && !!item.disabled))
                    const index = model.position(item.value)
                    if (row.up) { row.up.disabled = !model.interactive() || index <= 0; row.up.setAttribute('aria-label', `${config.moveUpLabel} ${item.label}`) }
                    if (row.down) { row.down.disabled = !model.interactive() || index >= model.selectedCount - 1; row.down.setAttribute('aria-label', `${config.moveDownLabel} ${item.label}`) }
                    if (cursor !== row.node) p.list.insertBefore(row.node, cursor ?? p.bottom)
                    cursor = row.node.nextSibling
                })
                p.top.style.height = `${window.top}px`; p.bottom.style.height = `${window.bottom}px`
                p.search?.toggleAttribute('disabled', !model.interactive())
                p.empty.hidden = items.length > 0
                p.count.textContent = String(items.length)
                p.marks.textContent = config.labels.selection_count.replace(':count', model.marked[pane].size)
                p.list.tabIndex = items.length === 0 ? 0 : -1
                p.markAll.disabled = !model.interactive() || !items.length
                p.clear.disabled = !model.interactive() || !model.marked[pane].size
            }
            for (const button of buttons) {
                const action = button.dataset.dualAction
                button.disabled = action === 'swap' ? !model.canSwap() : !model.canTransfer(action.includes('remove') ? 'selected' : 'available', action.startsWith('all-'))
            }
        },
        focus(pane, key) {
            const p = panes.get(pane), index = model.items(pane).findIndex(item => item.value === key)
            if (index < 0) return
            p.focused = key
            const start = index * config.rowHeight, end = start + config.rowHeight
            if (start < p.list.scrollTop) p.list.scrollTop = start
            else if (end > p.list.scrollTop + visibleHeight(p.list)) p.list.scrollTop = end - visibleHeight(p.list)
            api.render(pane); p.nodes.get(key)?.node.focus({ preventScroll: true })
        },
        announce(count) { status.textContent = config.labels.moved_count.replace(':count', count) },
        resetFocus() { for (const p of panes.values()) p.focused = null },
        clearDrop() {
            if (!activeDrop) return
            activeDrop.panel.classList.remove('is-drop-pane')
            activeDrop.row?.classList.remove(activeDrop.position)
            activeDrop = null
        },
        drop(pane, value, after) {
            const panel = panes.get(pane)?.panel, row = panes.get(pane)?.nodes.get(value)?.node
            const position = after ? 'is-drop-after' : 'is-drop-before'
            if (activeDrop?.panel === panel && activeDrop?.row === row && activeDrop?.position === position) return
            api.clearDrop()
            if (!panel) return
            panel.classList.add('is-drop-pane'); row?.classList.add(position)
            activeDrop = { panel, row, position }
        },
        ghost(keys) {
            const ghost = document.createElement('div'); ghost.className = 'fff-dual-listbox__ghost'; ghost.dir = root.closest('[dir]')?.dir ?? document.documentElement.dir
            keys.slice(0, 3).forEach((key, index) => { const card = document.createElement('div'); card.className = 'fff-dual-listbox__ghost-card'; card.textContent = model.map.get(key)?.label ?? key; card.style.setProperty('--stack', index); ghost.append(card) })
            if (keys.length > 1) { const badge = document.createElement('b'); badge.textContent = String(keys.length); ghost.append(badge) }
            document.body.append(ghost); return ghost
        },
        destroy() { api.clearDrop(); for (const p of panes.values()) p.nodes.clear(); panes.clear() },
    }
    return api
}
