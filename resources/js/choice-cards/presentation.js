export function createCardPresentation(root) {
    const cards = [...root.querySelectorAll('[data-card-option]')].map(element => ({
        element, key: element.dataset.cardOption, input: element.querySelector('input'),
        indicator: element.querySelector('.fff-selection-indicator'),
    }))
    return {
        cards,
        render(model, state, disabled, multiple) {
            root.classList.toggle('is-disabled', disabled)
            const selected = model.selected(state)
            const enabled = []
            for (const card of cards) {
                const checked = selected.has(card.key), blocked = !model.canChange(card.key, state, disabled, selected)
                if (card.input.checked !== checked) card.input.checked = checked
                if (card.input.disabled !== blocked) card.input.disabled = blocked
                if (card.element.classList.contains('is-selected') !== checked) {
                    // Motion marks user/external changes after activation, never SSR hydration.
                    if (root.classList.contains('is-hydrated')) card.element.dataset.cardMotion = checked ? 'selected' : 'cleared'
                    card.element.classList.toggle('is-selected', checked)
                }
                if (card.element.classList.contains('is-disabled') !== blocked) card.element.classList.toggle('is-disabled', blocked)
                if (card.indicator && card.indicator.dataset.checked !== String(checked)) card.indicator.dataset.checked = String(checked)
                if (!blocked) enabled.push(card.key)
            }
            if (!multiple) {
                const focus = enabled.find(key => selected.has(key)) ?? enabled[0]
                for (const card of cards) card.input.tabIndex = card.key === focus ? 0 : -1
            }
            return enabled
        },
        focus(key) { cards.find(card => card.key === key)?.input.focus() },
    }
}
