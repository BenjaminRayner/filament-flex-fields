/** Composition and modified shortcuts belong to the platform text editor. */
export function shouldHandlePickerKey(event) {
    return event != null && !event.defaultPrevented && !event.isComposing
        && event.keyCode !== 229 && !event.ctrlKey && !event.metaKey && !event.altKey
}

export function isEditableKeyTarget(target) {
    return target?.isContentEditable || ['INPUT', 'TEXTAREA'].includes(target?.tagName)
}

export function canRestorePickerFocus(previous, menu, active) {
    return previous?.isConnected && (active == null || active === menu?.ownerDocument?.body || menu?.contains(active))
}
