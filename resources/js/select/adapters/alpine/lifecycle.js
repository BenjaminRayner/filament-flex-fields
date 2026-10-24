import { createSurfaceOpenSession } from '../../../overlay/surface-open-session.js'
import { normalizeInitialSelectedValues, resolveHeadlessBoundState, shouldHydrateInitialSelectionToWire } from '../../../components/select-field/headless-select-state.js'

/** Component initialization and teardown own every deferred trigger reveal. */
export function createSelectLifecycleMixin({ capabilities }) {
    let releaseReveal = () => {}
    return {
        _selectInitializationPromise: null,
        _selectInitializationError: null,
        init() {
            this._pickerHostElement ??= this.$el
            if (this._selectInitializationPromise) return this._selectInitializationPromise
            if (this._selectInitialized || this._selectDisposed) return Promise.resolve()
            this._selectInitializationError = null
            this._selectInitializationPromise = this.initializeSelect().catch((error) => {
                // Optional imports publish atomically. Keep SSR and allow a new
                // explicit open intent to retry before an engine was created.
                if (!this._engine) this._selectInitialized = false
                this._selectInitializationError = error
                this.syncSelectInitializationPhase()
                if (!this._optionalMixinsError && !this._selectDisposed) this.$dispatch?.('fff-select-load-error', { error })
            }).finally(() => { this._selectInitializationPromise = null })
            return this._selectInitializationPromise
        },
        async initializeSelect() {
            if (this._selectInitialized || this._selectDisposed) return
            this._selectInitialized = true
            this.syncSelectInitializationPhase()
            const selected = normalizeInitialSelectedValues(resolveHeadlessBoundState(this.state, this.initialState, this.multiple), this.multiple)
            if (!capabilities.users && !selected.length) {
                this.displayReady = true
                this.ensureHeadlessSsrHandoff()
            }
            this.comboboxSelectedValues = selected
            const optional = this.loadOptionalMixins()
            if (capabilities.users) await optional
            else void optional.catch(() => {})
            if (this._selectDisposed) return
            if (Number.isFinite(this.menuHeight) && this.menuHeight > 0) {
                this.resolveMenuElement()?.style.setProperty('--fff-select-menu-height', `${this.menuHeight}px`)
            }
            this.createSelectEngine(selected)
            this._syncFromEngine()
            this.syncInlineSearchInputAfterClose()
            this.bindSelectMenuLifecycle()
            if (shouldHydrateInitialSelectionToWire(this.state, this.initialState, this.multiple)) {
                this._programmaticSelectionWrite = true
                try { this.syncStateFromEngine(selected) }
                finally { this._programmaticSelectionWrite = false }
            }
            this.bindSelectReactiveObservers()
            this.seedInitialTriggerLabels()
            this._syncFromEngine()
            this.syncClearablePresentation()
            this.bumpTriggerLabelEpoch()
            this.markHeadlessDisplayReady()
            this.scheduleSelectTriggerReveal()
            this.syncSelectInitializationPhase()
            if (this.comboboxOpen) this.startSelectOpenEffects()
        },
        scheduleSelectTriggerReveal() {
            const host = this._pickerHostElement ?? this.$el
            host?.setAttribute?.('data-fff-select-reveal', 'scheduled')
            releaseReveal()
            const session = createSurfaceOpenSession({
                isCurrent: () => !this._selectDisposed,
                nextTick: (callback) => this.$nextTick(callback),
            })
            releaseReveal = this.ensurePickerResourceScope().defer(() => session.dispose())
            const reveal = () => { host?.setAttribute?.('data-fff-select-reveal', 'painted'); this.bumpTriggerLabelEpoch(); this.markHeadlessDisplayReady(); releaseReveal() }
            // Preserve the UserSelect chip paint before handing off the SSR trigger.
            session.frame(() => session.frame(reveal))
        },
        destroy() {
            if (this._selectDisposed) return
            this._selectDisposed = true
            this._selectOpenEffectsGeneration++
            releaseReveal()
            clearTimeout(this._inlineSearchBlurTimer)
            this._inlineSearchBlurTimer = null
            this.syncSelectInitializationPhase()
            this._selectSearchRequests?.dispose()
            this._selectOptionsRequests?.dispose()
            this._selectLabelRequests?.dispose()
            this._relationshipAdapter?.destroy?.()
            this.cancelRelationshipSearch?.()
            this.disconnectHeadlessLoadMoreObserver?.()
            this.teardownSelectedOptionLabelRefreshListener?.()
            this.teardownDynamicOptionsInvalidation?.()
            this.unbindVirtualRowMeasurements?.()
            this.destroyTeleportedMenuLifecycle()
            this.unbindDropdownScrollFadeObserver()
            this.unbindHeadlessMenuPositionObservers()
            this.disposeUserSelectIntegration?.()
            this._knownLabelEntries.clear()
            this._labelRepositoryStore?.clear()
            this._engine?.destroy()
            this._engine = null
        },
    }
}
