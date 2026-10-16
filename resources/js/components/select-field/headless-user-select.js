import { createUserSelectRepositoryMixin } from '../../select/adapters/user/repository.js'
import { createUserSelectPresentationMixin } from '../../select/adapters/user/presentation.js'
import { createUserSelectChipsMixin } from '../../select/adapters/user/chips.js'
import { createUserSelectEmptyUiMixin } from '../../select/adapters/user/empty-ui.js'

/** Compatibility factory: record lifetime, keyed chips and presentation are separate. */
export function createHeadlessUserSelectMixin({
    isUserSelectField = false, verifiedIconHtml = '', tagRemoveIconHtml = '',
    userSelectNoOptionsIconHtml = '', userSelectNoResultsIconHtml = '', userSelectEmptyStateHints = {},
} = {}) {
    return {
        isUserSelectField, verifiedIconHtml, tagRemoveIconHtml,
        userSelectNoOptionsIconHtml, userSelectNoResultsIconHtml, userSelectEmptyStateHints,
        ...createUserSelectRepositoryMixin(),
        ...createUserSelectPresentationMixin(),
        ...createUserSelectChipsMixin(),
        ...createUserSelectEmptyUiMixin(),
        _userIntegrationInitialized: false,
        initUserSelectIntegration() {
            if (!this.isUserSelectField || this._userIntegrationInitialized || this._selectDisposed) return
            this._userIntegrationInitialized = true
            this.ensurePickerResourceScope().defer(() => this.disposeUserSelectIntegration())
            this.seedUserRepository()
            this.syncUserSelectTags()
            this.bumpTriggerLabelEpoch?.()
            const refresh = () => {
                if (this._selectDisposed) return
                this.syncUserSelectTags()
                this.bumpTriggerLabelEpoch?.()
            }
            this.$watch('comboboxSelectedValues', refresh)
            this.$watch('disabled', refresh)
        },
        disposeUserSelectIntegration() {
            this.releaseUserSelectTagListener()
            this.clearUserSelectChipProjection()
            this.clearUserSelectRecords()
        },
        removeUserSelectTag(value) {
            if (!this.disabled && !this._selectDisposed) this.comboboxDeselectValue(value)
        },
    }
}
