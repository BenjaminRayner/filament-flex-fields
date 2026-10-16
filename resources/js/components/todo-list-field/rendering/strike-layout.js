import { mergeTextLineRects } from '../helpers.js'

/** Read all visible text geometry before writing SVG positions. Completion never changes this geometry. */
export function measureTodoStrikes(root) {
    if (!root) return () => {}
    const document = root.ownerDocument
    const plans = []
    for (const wrap of root.querySelectorAll(
        '.fff-todo-list-field__title-wrap',
    )) {
        const label = wrap.querySelector('.fff-todo-list-field__label')
        const host = wrap.querySelector('.fff-todo-list-field__strikes')
        const template = host?.querySelector('.fff-todo-list-field__strike')
        if (!label || !host || !template) continue
        const rect = wrap.getBoundingClientRect()
        let lines = []
        try {
            const range = document.createRange()
            range.selectNodeContents(label)
            lines = mergeTextLineRects(range.getClientRects())
            range.detach?.()
        } catch {
            /* Hidden text uses its box only if it has measurable dimensions. */
        }
        if (!lines.length) {
            const fallback = label.getBoundingClientRect()
            if (fallback.width >= 0.5 && fallback.height >= 0.5)
                lines = [fallback]
        }
        plans.push({ host, template, rect, lines })
    }
    return () => {
        const style = (node, key, value) => {
            if (value === null) {
                if (node.style[key]) node.style.removeProperty(key)
            } else if (node.style[key] !== value) node.style[key] = value
        }
        for (const { host, template, rect, lines } of plans) {
            const needed = Math.max(lines.length, 1)
            const strikes = [
                ...host.querySelectorAll('.fff-todo-list-field__strike'),
            ]
            while (strikes.length < needed) {
                const clone = template.cloneNode(true)
                clone.removeAttribute('style')
                host.appendChild(clone)
                strikes.push(clone)
            }
            while (strikes.length > needed) strikes.pop().remove()
            for (let index = 0; index < strikes.length; index++) {
                const strike = strikes[index],
                    line = lines[index]
                const hidden = !line
                if (strike.hidden !== hidden) strike.hidden = hidden
                style(
                    strike,
                    'top',
                    line ? `${line.top - rect.top + line.height / 2}px` : '50%',
                )
                style(strike, 'transform', 'translateY(-50%)')
                style(
                    strike,
                    'left',
                    lines.length <= 1
                        ? '0px'
                        : `${Math.max(0, line.left - rect.left)}px`,
                )
                style(
                    strike,
                    'width',
                    lines.length <= 1
                        ? null
                        : `${Math.max(Math.ceil(line.width) + 4, 8)}px`,
                )
            }
        }
    }
}

export function layoutTodoStrikes(root) {
    measureTodoStrikes(root)()
}

/** Unchecked rows need no SVG line geometry in the scrolling hot path. */
export function measureCompletedTodoStrikes(root) {
    const plans = Array.from(
        root.querySelectorAll('.fff-todo-list-field__row.is-done'),
        (row) => measureTodoStrikes(row),
    )
    return () => {
        for (const apply of plans) apply()
    }
}
