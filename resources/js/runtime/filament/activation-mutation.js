import { changesAssetActivation } from '../lifecycle/activation-mutation.js'

const classes = new Set(['hidden', 'invisible', 'fi-active', 'is-active', 'fi-collapsed', 'fi-modal-open', 'fi-modal', 'fi-modal-window', 'fi-modal-window-ctn', 'fi-sc-tabs-tab', 'fi-sc-wizard-step', 'fi-section-content-ctn', 'fff-segment-tabs__panel'])

/** Native activation and loading classes are adapter policy, not headless DOM semantics. */
export function changesFilamentActivation(mutation) {
    return changesAssetActivation(mutation, classes)
        || (mutation.attributeName === 'class' && (mutation.target.classList?.contains('fi-sc-loading')
            || /(?:^|\s)fi-sc-loading(?:\s|$)/.test(mutation.oldValue ?? '')))
}
