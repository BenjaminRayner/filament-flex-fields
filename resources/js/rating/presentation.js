import { ratingFill } from './value.js'

export function createRatingPresentation(root) {
    const buttons = Array.from(root.querySelectorAll('[data-rating-index]'))
    const fills = buttons.map(button => button.querySelector('[data-rating-fill]'))
    let priorDisplay, priorSelected, priorDisabled
    return {
        buttons,
        paint(display, selected, disabled) {
            if (display !== priorDisplay) buttons.forEach((button, offset) => {
                const fill = ratingFill(display, offset + 1), active = fill > 0 ? 'true' : 'false'
                fills[offset].style.width = `${Math.round(fill * 1000000) / 10000}%`
                if (button.getAttribute('data-active') !== active) button.setAttribute('data-active', active)
            })
            if (selected !== priorSelected || disabled !== priorDisabled) buttons.forEach((button, offset) => {
                const index = offset + 1, checked = selected === index
                const text = checked ? 'true' : 'false'
                if (button.getAttribute('data-selected') !== text) button.setAttribute('data-selected', text)
                if (button.getAttribute('aria-checked') !== text) button.setAttribute('aria-checked', text)
                const focusable = checked || (index === 1 && (selected === null || selected < 1 || !Number.isInteger(selected)))
                button.tabIndex = !disabled && focusable ? 0 : -1
                button.disabled = disabled
            })
            if (disabled !== priorDisabled) {
                root.classList.toggle('is-disabled', disabled)
                root.setAttribute('aria-disabled', String(disabled))
            }
            priorDisplay = display; priorSelected = selected; priorDisabled = disabled
        },
        focus(index) { buttons[index - 1]?.focus({ preventScroll: true }) },
    }
}
