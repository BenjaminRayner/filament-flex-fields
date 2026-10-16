export function bindBubbleInteraction({ root, presentation, scope, toggle, allowed }) {
    const buttonFrom = event => {
        const button = event.target.closest?.('[data-bubble-value] button')
        return button && root.contains(button) ? button : null
    }
    scope.listen(root, 'click', event => {
        const button = buttonFrom(event)
        if (button && !button.disabled && allowed()) toggle(button.closest('[data-bubble-value]').dataset.bubbleValue)
    })
    scope.listen(root, 'keydown', event => {
        const button = buttonFrom(event)
        if (!button || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return
        const current = presentation.cells.find(cell => cell.button === button)
        const candidates = presentation.cells.filter(cell => !cell.button.disabled)
        if (!current || !candidates.length) return
        const rtl = root.ownerDocument.defaultView.getComputedStyle?.(root).direction === 'rtl'
            || root.closest('[dir]')?.getAttribute('dir') === 'rtl' || root.ownerDocument.documentElement.dir === 'rtl'
        let next
        if (event.key === 'Home' || event.key === 'End') next = candidates[event.key === 'Home' ? 0 : candidates.length - 1]
        else {
            const horizontal = event.key === 'ArrowLeft' || event.key === 'ArrowRight'
            const sign = (event.key === 'ArrowRight' || event.key === 'ArrowDown' ? 1 : -1) * (horizontal && rtl ? -1 : 1)
            const x = cell => cell.col + (presentation.cells.filter(item => item.row === cell.row).length % 2 === 1 ? .5 : 0)
            const currentX = x(current)
            const ranked = candidates.filter(cell => cell !== current && (horizontal
                ? cell.row === current.row && (x(cell) - currentX) * sign > 0
                : (cell.row - current.row) * sign > 0))
            ranked.sort((a, b) => horizontal ? Math.abs(x(a) - currentX) - Math.abs(x(b) - currentX)
                : Math.abs(a.row - current.row) - Math.abs(b.row - current.row) || Math.abs(x(a) - currentX) - Math.abs(x(b) - currentX))
            next = ranked[0]
        }
        event.preventDefault()
        if (next) presentation.centerButton(next.key)
    })
}
