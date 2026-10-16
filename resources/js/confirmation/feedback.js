/** The hold button keeps its original footprint while server work is pending. */
export function createHoldFeedback(host, config) {
    const button = host.querySelector('[data-confirm-role="hold"]'), feedback = button?.querySelector('[data-hold-feedback]')
    const label = button?.getAttribute('aria-label')
    return {
        show(state) {
            if (!button) return
            button.disabled = config.disabled || state === 'pending'
            button.setAttribute('aria-busy', state === 'pending' ? 'true' : 'false')
            button.dataset.result = state
            if (feedback) feedback.hidden = !['pending', 'done', 'error'].includes(state)
            for (const part of feedback?.querySelectorAll('[data-hold-state]') ?? []) part.hidden = part.dataset.holdState !== state
            if (state === 'error') button.setAttribute('aria-label', `${config.labels.error}. ${config.labels.retry}`)
            else if (state !== 'idle') button.setAttribute('aria-label', config.labels[state] ?? '')
            else if (label !== null && label !== undefined) button.setAttribute('aria-label', label)
            else button.removeAttribute('aria-label')
        },
    }
}
