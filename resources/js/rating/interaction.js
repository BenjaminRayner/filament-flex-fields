import { keyboardRating } from './value.js'

export function bindRatingInteraction({ root, scope, max, allowed, preview, clear, select, presentation }) {
    const indexFor = event => {
        const button = event.target?.closest?.('[data-rating-index]')
        if (!button || !root.contains(button)) return null
        const index = Number(button.dataset.ratingIndex)
        return Number.isInteger(index) && index >= 1 && index <= max ? index : null
    }
    scope.listen(root, 'click', event => {
        const index = indexFor(event)
        if (index !== null && allowed()) select(index)
    })
    scope.listen(root, 'pointerover', event => {
        if (event.pointerType && event.pointerType !== 'mouse') return
        const index = indexFor(event)
        if (index !== null && allowed()) preview(index)
    })
    scope.listen(root, 'pointerleave', clear)
    scope.listen(root, 'pointercancel', clear)
    scope.listen(root, 'focusout', event => { if (!root.contains(event.relatedTarget)) clear() })
    scope.listen(root, 'keydown', event => {
        if (!allowed()) return
        const index = indexFor(event)
        if (index === null) return
        const rtl = root.ownerDocument.defaultView.getComputedStyle(root).direction === 'rtl'
        const target = keyboardRating(event.key, index, max, rtl)
        if (target !== null) {
            event.preventDefault(); select(target); presentation.focus(target)
        } else if (event.key === 'Escape') { clear() }
        else if ((event.key === ' ' || event.key === 'Enter') && event.repeat) event.preventDefault()
    })
}
