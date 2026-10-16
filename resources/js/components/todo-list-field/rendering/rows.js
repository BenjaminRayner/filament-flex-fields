import { cancelRowMotion } from '../motion.js'
import { childProgressLabel } from '../helpers.js'
import { createTodoRowReserve } from './recycling.js'

/** Adopt SSR rows and reuse keyed nodes. Only the visible TanStack window has DOM owners. */
export function createTodoRows(root, config) {
    const list = root.querySelector('[data-todo-list]'),
        template = root.querySelector('[data-todo-group-template]')
    const rowTemplate = root.querySelector('[data-todo-row-template]'),
        groups = new Map(),
        rows = new Map(),
        reorderMotion = new Map()
    let sequence = 0,
        geometryChanged = false
    const owner = root.dataset.todoOwner
    const top = root.querySelector('[data-todo-spacer="top"]'),
        bottom = root.querySelector('[data-todo-spacer="bottom"]')
    const getNodes = (element) => ({
        element,
        checkbox: element.querySelector('input'),
        label: element.querySelector('[data-todo-label]'),
        description: element.querySelector('[data-todo-description]'),
        progress: element.querySelector('[data-todo-progress]'),
        date: element.querySelector('[data-todo-date]'),
        edit: element.querySelector('[data-todo-action="edit"]'),
        remove: element.querySelector('[data-todo-action="delete"]'),
        drag: element.querySelector('[data-todo-drag]'),
        editIcon: element.querySelector('[data-todo-action="edit"] .fff-todo-list-field__action-icon'),
        editSpinner: element.querySelector('[data-todo-action="edit"] .fff-todo-list-field__action-spinner'),
        removeIcon: element.querySelector('[data-todo-action="delete"] .fff-todo-list-field__action-icon'),
        removeSpinner: element.querySelector('[data-todo-action="delete"] .fff-todo-list-field__action-spinner'),
    })
    const text = (node, value) => {
        if (node && node.textContent !== String(value ?? ''))
            node.textContent = String(value ?? '')
    }
    const attribute = (node, name, value) => {
        value = String(value)
        if (node.getAttribute(name) !== value) node.setAttribute(name, value)
    }
    const hidden = (node, value) => {
        if (node.hasAttribute('hidden') !== value) node.toggleAttribute('hidden', value)
    }
    const toggle = (node, name, value) => {
        if (node.classList.contains(name) !== value)
            node.classList.toggle(name, value)
    }
    const setRow = (entry, item, runtime) => {
        const el = entry.element,
            done = Boolean(item.done),
            blocked = runtime.disabled || item.disabled
        const signature = JSON.stringify([
            item,
            runtime.disabled,
            runtime.isSettledId(item.id),
            runtime.enteringIds[String(item.id)],
            runtime.exitingIds[String(item.id)],
            runtime.search,
            runtime.actionBusy?.[String(item.id)],
            runtime.actionMountPending,
        ])
        if (entry.signature === signature) return
        const geometry = JSON.stringify([
            item.label,
            item.description,
            childProgressLabel(item).length,
            item.date,
            runtime.canEditItem(item),
            runtime.canDeleteItem(item),
            runtime.canReorderItem(item),
        ])
        if (geometry !== entry.geometry) {
            entry.geometry = geometry
            geometryChanged = true
        }
        entry.signature = signature
        attribute(el, 'data-todo-id', item.id)
        attribute(el, 'tabindex', blocked ? -1 : 0)
        attribute(el, 'aria-checked', done)
        attribute(el, 'aria-disabled', !!blocked)
        attribute(el, 'aria-label', item.label)
        toggle(el, 'is-done', done)
        toggle(el, 'is-disabled', !!blocked)
        toggle(el, 'is-settled', done && runtime.isSettledId(item.id))
        toggle(el, 'is-entering', !!runtime.enteringIds[String(item.id)])
        toggle(el, 'is-exiting', !!runtime.exitingIds[String(item.id)])
        entry.checkbox.checked = done
        entry.checkbox.disabled = !!blocked
        text(entry.label, item.label)
        text(entry.description, item.description)
        hidden(entry.description, !item.description)
        text(entry.progress, childProgressLabel(item))
        hidden(entry.progress.parentElement, !item.children?.length)
        text(entry.date, item.date)
        hidden(entry.date.parentElement, !item.date)
        hidden(entry.edit, !runtime.canEditItem(item))
        hidden(entry.remove, !runtime.canDeleteItem(item))
        const operation = runtime.actionBusy?.[String(item.id)]
        for (const [button, name, icon, spinner] of [
            [entry.edit, 'edit', entry.editIcon, entry.editSpinner],
            [entry.remove, 'delete', entry.removeIcon, entry.removeSpinner],
        ]) {
            const busy = operation === name
            button.disabled = Boolean(operation || runtime.actionMountPending)
            attribute(button, 'aria-busy', busy)
            toggle(button, 'is-busy', busy)
            hidden(icon, busy)
            hidden(spinner, !busy)
        }
        hidden(
            entry.drag,
            el.classList.contains('fff-todo-list-field__row--child') ||
                !runtime.canReorderItem(item),
        )
    }
    const adoptRow = (element) => {
        const entry = getNodes(element)
        rows.set(String(element.dataset.todoId), entry)
        return entry
    }
    const makeRow = (child) => {
        const element = rowTemplate.content.firstElementChild.cloneNode(true)
        element.classList.toggle('fff-todo-list-field__row--child', child)
        const tickId = `${owner}-tick-${++sequence}`,
            maskId = `${tickId}-mask`
        element.querySelector('[data-todo-tick-mask]').id = maskId
        element
            .querySelector('[data-todo-tick-fill]')
            .setAttribute('mask', `url(#${maskId})`)
        return getNodes(element)
    }
    for (const element of list.querySelectorAll('[data-todo-group-id]')) {
        groups.set(String(element.dataset.todoGroupId), element)
        for (const row of element.querySelectorAll('[data-todo-id]'))
            adoptRow(row)
    }
    const releaseRow = (id) => {
        const row = rows.get(id)
        if (row) {
            cancelRowMotion(row.element)
            rows.delete(id)
        }
    }
    const reserve = createTodoRowReserve({
        reset({ group, parent }) {
            for (const element of group.querySelectorAll('[data-todo-id]'))
                releaseRow(String(element.dataset.todoId))
            const children = group.querySelector('[data-todo-children]')
            children.replaceChildren()
            parent.signature = null
            parent.geometry = null
            parent.element.removeAttribute('data-todo-id')
            parent.element.classList.add('is-window-presented')
            parent.element.style.removeProperty('--checkbox-lines-offset')
            parent.element.style.setProperty('--text-x', '0px')
        },
    })
    return {
        list,
        row: (id) => rows.get(String(id))?.element ?? null,
        render(projected, runtime) {
            geometryChanged = false
            const virtual = Boolean(runtime.virtualizing)
            if (virtual) {
                list.classList.add('is-windowed')
                const height = `${projected.totalSize + 10}px`
                if (list.style.height !== height) list.style.height = height
                top.hidden = true
                bottom.hidden = true
            }
            const ordered = projected.rows.map((row) => String(row.item.id))
            const previous = [...groups.keys()]
            const moved =
                runtime.reorderable &&
                !runtime.reducedMotion &&
                root.classList.contains('is-motion-ready') &&
                previous.length === ordered.length &&
                previous.some((id, index) => id !== ordered[index])
            const positions = moved
                ? new Map(
                      [...groups].map(([id, group]) => [
                          id,
                          group.getBoundingClientRect().top,
                      ]),
                  )
                : null
            const desired = new Set(
                projected.rows.map((row) => String(row.item.id)),
            )
            for (const [id, group] of groups)
                if (!desired.has(id)) {
                    config.releaseMeasurement?.(group)
                    if (runtime.virtualizing)
                        reserve.keep({ group, parent: rows.get(id) })
                    else {
                        for (const row of group.querySelectorAll('[data-todo-id]'))
                            releaseRow(String(row.dataset.todoId))
                        group.remove()
                    }
                    reorderMotion.get(id)?.cancel()
                    reorderMotion.delete(id)
                    geometryChanged = true
                    groups.delete(id)
                }
            if (top.style.height !== `${projected.before}px`)
                top.style.height = `${projected.before}px`
            if (bottom.style.height !== `${projected.after}px`)
                bottom.style.height = `${projected.after}px`
            let cursor = top.nextElementSibling
            for (const { item, index, start } of projected.rows) {
                const id = String(item.id)
                let group = groups.get(id)
                let recycled = null
                if (!group) {
                    recycled = runtime.virtualizing ? reserve.take() : null
                    group = recycled?.group ?? template.content.firstElementChild.cloneNode(true)
                    group.dataset.todoGroupId = id
                    group.hidden = false
                    groups.set(id, group)
                }
                if (group.dataset.index !== String(index))
                    group.dataset.index = String(index)
                if (virtual) {
                    const position = `${start + 4}px`
                    if (group.style.top !== position) group.style.top = position
                }
                let parent = rows.get(id)
                if (!parent) {
                    parent = recycled?.parent ?? makeRow(false)
                    parent.element.dataset.todoId = id
                    rows.set(id, parent)
                    if (!recycled) group.prepend(parent.element)
                    if (runtime.virtualizing) {
                        parent.element.classList.add('is-window-presented')
                        parent.element.style.setProperty('--text-line-scale', item.done ? '1' : '0')
                    }
                }
                setRow(parent, item, runtime)
                const children = group.querySelector('[data-todo-children]'),
                    childIds = new Set(
                        (item.children ?? []).map((child) => String(child.id)),
                    )
                for (const element of [...children.children])
                    if (!childIds.has(String(element.dataset.todoId))) {
                        releaseRow(String(element.dataset.todoId))
                        element.remove()
                    }
                let childCursor = children.firstElementChild
                for (const child of item.children ?? []) {
                    const key = String(child.id)
                    let row = rows.get(key)
                    if (!row) {
                        row = makeRow(true)
                        row.element.dataset.todoId = key
                        rows.set(key, row)
                    }
                    setRow(row, child, runtime)
                    if (row.element !== childCursor)
                        children.insertBefore(row.element, childCursor)
                    childCursor = row.element.nextElementSibling
                }
                if (children.hidden !== !childIds.size)
                    children.hidden = !childIds.size
                if (virtual) {
                    // Position changes do not disconnect SVG masks or shrink the
                    // scroll surface between removing and inserting a window.
                    if (group !== cursor) {
                        if (list.moveBefore && group.parentElement === list)
                            list.moveBefore(group, cursor ?? bottom)
                        else list.insertBefore(group, cursor ?? bottom)
                    }
                } else if (group !== cursor) {
                    // Atomic moves preserve connected SVG masks and native state.
                    if (list.moveBefore && group.parentElement === list)
                        list.moveBefore(group, cursor ?? bottom)
                    else list.insertBefore(group, cursor ?? bottom)
                }
                cursor = group.nextElementSibling
            }
            if (positions)
                for (const id of ordered) {
                    const group = groups.get(id),
                        delta =
                            positions.get(id) -
                            group.getBoundingClientRect().top
                    if (!delta || !group.animate) continue
                    reorderMotion.get(id)?.cancel()
                    const animation = group.animate(
                        [
                            { transform: `translateY(${delta}px)` },
                            { transform: 'translateY(0)' },
                        ],
                        {
                            duration: runtime.reorderAnimationDuration,
                            easing: 'cubic-bezier(.22,1,.36,1)',
                        },
                    )
                    reorderMotion.set(id, animation)
                    animation.finished.then(
                        () => {
                            if (reorderMotion.get(id) === animation)
                                reorderMotion.delete(id)
                        },
                        () => {},
                    )
                }
            // Map order tracks current DOM order for subsequent FLIP comparisons.
            for (const id of ordered) {
                const group = groups.get(id)
                groups.delete(id)
                groups.set(id, group)
            }
            const empty = root.querySelector('[data-todo-empty]')
            if (empty.hidden !== desired.size > 0)
                empty.hidden = desired.size > 0
            return { geometryChanged }
        },
        dispose() {
            reserve.clear()
            for (const animation of reorderMotion.values()) animation.cancel()
            reorderMotion.clear()
            for (const row of rows.values()) cancelRowMotion(row.element)
            rows.clear()
            groups.clear()
        },
    }
}
