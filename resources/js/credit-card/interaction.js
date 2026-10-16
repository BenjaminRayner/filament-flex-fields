import { digits, formattedValue, replaceInputValue } from './format.js'

export function bindCardInteraction({ root, scope, allowed, update, flip, focusCvv }) {
    const inputFor = event => event.target?.matches?.('[data-card-input]') ? event.target : null
    scope.listen(root, 'input', event => {
        const input = inputFor(event)
        if (!input || !allowed() || event.isComposing) return
        const key = input.dataset.cardInput, formatted = formattedValue(key, input.value)
        replaceInputValue(input, formatted)
        update(key, key === 'number' ? digits(formatted) : formatted)
    })
    scope.listen(root, 'compositionend', event => {
        const input = inputFor(event)
        if (input && allowed()) update(input.dataset.cardInput, formattedValue(input.dataset.cardInput, input.value))
    })
    scope.listen(root, 'beforeinput', event => {
        const input = inputFor(event)
        if (!input || !allowed() || !['number', 'expiry'].includes(input.dataset.cardInput)) return
        const cursor = input.selectionStart
        if (cursor === null || cursor !== input.selectionEnd) return
        const backward = event.inputType === 'deleteContentBackward', forward = event.inputType === 'deleteContentForward'
        const separator = backward ? cursor - 1 : cursor
        if ((!backward && !forward) || !/[ /]/.test(input.value[separator] ?? '')) return
        event.preventDefault()
        const remove = backward ? separator - 1 : separator + 1
        if (remove < 0 || remove >= input.value.length) return
        input.value = input.value.slice(0, remove) + input.value.slice(remove + 1)
        input.setSelectionRange(backward ? remove : cursor, backward ? remove : cursor)
        const formatted = formattedValue(input.dataset.cardInput, input.value)
        replaceInputValue(input, formatted)
        update(input.dataset.cardInput, input.dataset.cardInput === 'number' ? digits(formatted) : formatted)
    })
    scope.listen(root, 'focusin', event => { if (inputFor(event)?.dataset.cardInput === 'cvv' && allowed()) focusCvv(true) })
    scope.listen(root, 'focusout', event => { if (inputFor(event)?.dataset.cardInput === 'cvv') focusCvv(false) })
    scope.listen(root, 'click', event => { if (event.target?.closest?.('[data-card-flip]') && allowed()) flip() })
}
