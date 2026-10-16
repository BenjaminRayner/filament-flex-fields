/** Instance-local state; Alpine owns the DOM bindings and their disposal. */
export default function colorSwatchFormComponent({ state, keys = [], disabled = false }) {
    const values = [...keys].map(String)
    const allowed = new Set(values)
    return {
        state,
        disabled,
        isSelected(value) { return this.state === value },
        tabIndex(value) {
            if (this.disabled) return -1
            return value === (allowed.has(this.state) ? this.state : values[0]) ? 0 : -1
        },
        select(value) {
            if (!this.disabled && allowed.has(value) && value !== this.state) this.state = value
        },
        keydown(event) {
            if (this.disabled || event.altKey || event.ctrlKey || event.metaKey) return
            const target = event.target.closest?.('[data-swatch-value]')
            if (!target || !this.$el.contains(target)) return
            const key = event.key
            if (!['Home', 'End', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key)) return
            event.preventDefault()
            const rtl = this.$el.matches(':dir(rtl)')
            const forward = key === 'ArrowDown' || key === (rtl ? 'ArrowLeft' : 'ArrowRight')
            const current = values.indexOf(target.dataset.swatchValue)
            const next = key === 'Home' ? 0 : key === 'End' ? values.length - 1
                : (current + (forward ? 1 : -1) + values.length) % values.length
            const value = values[next]
            this.select(value)
            // Compare attributes rather than interpolate arbitrary keys into selectors.
            const button = [...this.$el.querySelectorAll('[data-swatch-value]')]
                .find(node => node.dataset.swatchValue === value)
            button?.focus({ preventScroll: true })
        },
    }
}
