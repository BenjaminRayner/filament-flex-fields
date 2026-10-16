import { mountTextInputPresentation } from '../components/text-input/presentation.js'

/** Native emoji search semantics inside the shared FLEX input presentation. */
export function adaptEmojiContent(picker) {
    const root = picker.shadowRoot
    root.querySelector('.picker')?.classList.toggle('dark', picker.classList.contains('dark'))
    const wrapper = root.querySelector('.search-wrapper')
    const input = root.querySelector('input.search')
    if (!wrapper || !input) return
    input.dir = 'auto'
    const shell = mountTextInputPresentation(wrapper, input)
    const icon = wrapper.querySelector('.fff-search-icon')
    if (icon && icon.parentElement !== shell) shell.prepend(icon)
    alignSkinToneSelection(root)
}

/** Freeze the selected row at the trigger; keyboard movement must not move the popup. */
export function alignSkinToneSelection(root) {
    const button = root.querySelector('#skintone-button')
    const list = root.querySelector('#skintone-list')
    if (!button || !list) return
    const expanded = button.getAttribute('aria-expanded') === 'true'
    if (expanded && list.dataset.fffToneExpanded !== 'true') {
        const options = [...list.querySelectorAll('[role=option]')]
        const selected = options.findIndex(option => option.textContent.trim() === button.textContent.trim())
        list.dataset.fffToneAnchor = String(Math.max(0, selected))
    }
    // The library rewrites its inline transform on selection. Reapply the
    // frozen anchor during exit, before paint, rather than falling back to zero.
    list.style.setProperty('--fff-skin-selected-index', list.dataset.fffToneAnchor ?? '0')
    list.dataset.fffToneExpanded = String(expanded)
}
