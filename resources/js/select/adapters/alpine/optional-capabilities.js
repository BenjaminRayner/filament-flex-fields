import { publishSelectCapabilities } from './capability-owner.js'

/** Import first, publish once. A failed chunk never leaves a half-installed adapter. */
export function createSelectCapabilitiesMixin({ capabilities, livewireConfig, entityMentionConfig, userSelectConfig,
    importLivewire = () => import('../../../components/select-field/headless-combobox-livewire.js'),
    importMentions = () => import('../../../core/entity-mention.js'),
    importUsers = () => import('../../../components/select-field/headless-user-select.js'),
}) {
    return {
        _optionalMixinsLoaded: false,
        _optionalMixinsPromise: null,
        _optionalMixinsError: null,
        loadOptionalMixins() {
            if (this._optionalMixinsLoaded || this._selectDisposed) return Promise.resolve()
            if (this._optionalMixinsPromise) return this._optionalMixinsPromise
            this._optionalMixinsError = null
            this._optionalMixinsPromise = (async () => {
                const [livewire, mentions, users] = await Promise.all([
                    capabilities.livewire ? importLivewire() : null,
                    capabilities.mentions ? importMentions() : null,
                    capabilities.users ? importUsers() : null,
                ])
                if (this._selectDisposed) return
                const additions = {
                    ...(livewire ? livewire.createHeadlessComboboxLivewireMixin(livewireConfig) : {}),
                    ...(mentions ? mentions.createEntityMentionMixin(entityMentionConfig) : {}),
                    ...(users ? users.createHeadlessUserSelectMixin(userSelectConfig) : {}),
                }
                publishSelectCapabilities(this, additions)
                this._optionalMixinsLoaded = true
                if (capabilities.livewire) this.initLivewireIntegration()
                if (capabilities.users) this.initUserSelectIntegration()
                if (this._engine) {
                    // A selected deferred field can reveal before its label adapter
                    // arrives. Retry the SSR handoff after that repository is seeded.
                    this.seedInitialTriggerLabels?.()
                    this.bumpTriggerLabelEpoch?.()
                    this.scheduleSelectTriggerReveal?.()
                }
                this.syncSelectInitializationPhase()
            })().catch((error) => {
                this._optionalMixinsPromise = null
                if (this._selectDisposed) return
                this._optionalMixinsError = error
                this.syncSelectInitializationPhase()
                this.$dispatch?.('fff-select-load-error', { error })
                throw error
            })
            return this._optionalMixinsPromise
        },
        syncSelectInitializationPhase() {
            const phase = this._selectDisposed ? 'disposed'
                : this._optionalMixinsError || this._selectInitializationError ? 'failed'
                : this._engine && this._optionalMixinsLoaded ? 'ready'
                : this._engine ? 'capabilities' : 'initializing'
            this.$el?.setAttribute?.('data-fff-select-phase', phase)
        },
    }
}
