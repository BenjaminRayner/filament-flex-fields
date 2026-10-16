/** Controls are owned by the field root, even when an Alpine action runs on a child button. */
export function createTodoChrome(root) {
    const label = root.querySelector('[data-todo-page-label]')
    const status = root.querySelector('[data-todo-load-status]')
    const retry = root.querySelector('[data-todo-retry]')
    return {
        update(runtime) {
            const pageText = runtime.remoteLoader
                ? `${runtime.page}`
                : `${runtime.page} / ${runtime.pages}`
            if (label && label.textContent !== pageText)
                label.textContent = pageText
            const hidden = !(runtime.loadingMore || runtime.loadError)
            if (status && status.hidden !== hidden) status.hidden = hidden
            const text = runtime.loadError
                ? runtime.labels.loadFailed || 'Unable to load tasks.'
                : (runtime.labels.loadingMore ?? '')
            if (status && status.textContent !== text) status.textContent = text
            if (retry && retry.hidden !== !runtime.loadError)
                retry.hidden = !runtime.loadError
        },
    }
}
