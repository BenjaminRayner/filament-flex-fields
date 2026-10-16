/** Adapt an existing control without replacing its value, binding or event handlers. */
export function mountTextInputPresentation(root, input, { size = 'sm', focusOutline = true } = {}) {
    root.classList.add('fff-flex-text-input', `fff-flex-text-input--${size}`)
    root.classList.toggle('has-focus-outline', focusOutline)
    input.classList.add('fff-flex-text-input__input')
    let shell = root.querySelector('[data-fff-input-shell]')
    if (!shell) {
        shell = root.ownerDocument.createElement('div')
        shell.className = 'fff-flex-text-input__shell'
        shell.dataset.fffInputShell = ''
        shell.append(input)
        root.append(shell)
    }
    return shell
}
