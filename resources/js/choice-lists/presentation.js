export function createChoicePresentation(root, multiple) {
    const rows = new Map([...root.querySelectorAll('[data-choice-option]')].map(element => [element.dataset.choiceValue, {
        element,
        input: element.querySelector('input'),
        indicator: element.querySelector('.fff-checklist-indicator'),
        selected: element.classList.contains('is-selected'),
        disabled: element.getAttribute('aria-disabled') === 'true',
    }]))
    let focusKey = [...rows].find(([, row]) => row.selected && !row.disabled)?.[0]
        ?? [...rows].find(([, row]) => !row.disabled)?.[0]
    return {
        rows,
        render(model, state, disabled) {
            if (root.classList.contains('is-disabled') !== disabled)
                root.classList.toggle('is-disabled', disabled)
            if (root.getAttribute('aria-disabled') !== String(disabled))
                root.setAttribute('aria-disabled', String(disabled))
            const selected = model.selected(state)
            const enabled = []
            for (const [key, row] of rows) {
                const checked = selected.has(key), blocked = !model.canChange(key, state, disabled, selected)
                if (checked !== row.selected) {
                    if (!multiple && root.classList.contains('is-choice-ready'))
                        row.element.dataset.choiceMotion = checked ? 'selected' : 'cleared'
                    row.selected = checked
                    row.element.classList.toggle('is-selected', checked)
                    row.element.setAttribute('aria-checked', String(checked))
                    row.input.checked = checked
                    row.indicator?.setAttribute('data-checked', String(checked))
                }
                if (blocked !== row.disabled) {
                    row.disabled = blocked
                    row.element.classList.toggle('is-disabled', blocked)
                    row.element.setAttribute('aria-disabled', String(blocked))
                    row.input.disabled = blocked
                }
                if (!blocked) enabled.push(key)
            }
            if (!enabled.includes(focusKey)) focusKey = enabled[0]
            if (!multiple && selected.size) {
                const selectedKey = [...selected][0]
                if (enabled.includes(selectedKey)) focusKey = selectedKey
            }
            for (const [key, row] of rows) {
                const tab = key === focusKey && !row.disabled ? 0 : -1
                if (row.element.tabIndex !== tab) row.element.tabIndex = tab
            }
            return enabled
        },
        focus(key) {
            if (!rows.has(key) || rows.get(key).disabled) return
            if (focusKey !== key && rows.has(focusKey)) rows.get(focusKey).element.tabIndex = -1
            focusKey = key
            rows.get(key).element.tabIndex = 0
            rows.get(key).element.focus()
        },
        dispose() { rows.clear() },
    }
}
