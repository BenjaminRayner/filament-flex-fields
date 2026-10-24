import { runAfterSheetEnter } from '../../../core/searchable-select-menu.js'
import { seedHeadlessKnownSelected } from '../../../components/select-field/headless-select-selection-ux.js'
import { normalizeInitialSelectedValues, resolveHeadlessBoundState, shouldIgnoreEmptyHeadlessWireSync } from '../../../components/select-field/headless-select-state.js'

/** Reactive observers use scalar generations, including when Alpine wraps sessions. */
export function createSelectReactiveSyncMixin() {
    return {
        _selectObserversBound: false,
        _selectOpenEffectsGeneration: 0,
        bindSelectReactiveObservers() {
            if (this._selectObserversBound || this._selectDisposed) return
            this._selectObserversBound = true
            this.$watch('comboboxOpen', (open) => {
                if (this._selectDisposed) return
                if (open) this.startSelectOpenEffects()
                else this.stopSelectOpenEffects()
            })
            this.$watch('disabled', (disabled) => {
                if (this._selectDisposed || !disabled) return
                this.cancelRelationshipSearch?.()
                this.comboboxCloseMenu({ immediate: true, reason: 'disabled' })
            })
            this.$watch('state', (nextState) => {
                if (this._selectDisposed) return
                if (shouldIgnoreEmptyHeadlessWireSync(nextState, this.initialState, this.multiple, this._userHasMutatedSelection)) {
                    this.syncStateFromEngine(this.comboboxSelectedValues)
                    return
                }
                const values = normalizeInitialSelectedValues(resolveHeadlessBoundState(nextState, this.initialState,
                    this.multiple, { fallbackToInitial: false }), this.multiple)
                if (!this._valuesEqual(values, this.comboboxSelectedValues)) this.reconcileSelectWireState(values)
            })
            this.$watch('comboboxQuery', () => {
                if (this._selectDisposed) return
                this.virtualRowWindowStart = 0
                this.invalidateVirtualRowProjection()
                this._virtualPrefixSums = []
                this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1
                this.applyComboboxQueryToEngine()
                this.queueSelectLayoutRefresh({ checks: true })
            })
        },
        queueSelectLayoutRefresh({ checks = false } = {}) {
            if (this._selectDisposed || !this.comboboxOpen || this.__fffSheetClosing) return
            this._selectLayoutChecks = this._selectLayoutChecks || checks
            if (this._selectLayoutQueued) return
            this._selectLayoutQueued = true
            const generation = this._selectOpenEffectsGeneration
            this.$nextTick(() => {
                this._selectLayoutQueued = false
                const markChecks = this._selectLayoutChecks
                this._selectLayoutChecks = false
                if (this._selectDisposed || !this.comboboxOpen || this.__fffSheetClosing
                    || generation !== this._selectOpenEffectsGeneration) return
                this.syncDropdownOverflowChrome()
                if (markChecks) this.markKnownOptionChecksVisible()
                this.scheduleMenuPositionAfterLayout()
            })
        },
        startSelectOpenEffects() {
            if (this._selectDisposed || !this.comboboxOpen) return
            const generation = ++this._selectOpenEffectsGeneration
            const current = () => !this._selectDisposed && !this.disabled && this.comboboxOpen
                && !this.__fffSheetClosing && this._selectOpenEffectsGeneration === generation
            this.knownSelectedChecks = seedHeadlessKnownSelected(this.comboboxSelectedValues)
            this.clearAllOptionCheckExiting()
            this.checkExitCancel?.()
            this.checkExitCancel = null
            this.bindHeadlessMenuPositionObservers()
            const menu = this.resolveMenuElement?.() ?? this.$refs?.headlessMenu
            runAfterSheetEnter(menu, () => {
                if (!current()) return
                // A menu can open while the remote capability chunk is still loading.
                // Await it without blocking the surface animation or calling a stub.
                void this.loadOptionalMixins().then(() => {
                    if (!current()) return
                    this.onHeadlessMenuOpenedForLivewire()
                    this.$nextTick(() => {
                        if (!current()) return
                        this.bindDropdownScrollFadeObserver()
                        this.markKnownOptionChecksVisible()
                        if (this.searchable) this.focusHeadlessSearchInput()
                        this.observeHeadlessLoadMore?.()
                    })
                }).catch(() => { /* The capability boundary dispatches the failure once. */ })
            }, { isCurrent: current })
        },
        onMenuSurfaceAnchored() {
            if (!this._focusFirstMountedMenu) return
            this._focusFirstMountedMenu = false
            const generation = this._selectOpenEffectsGeneration
            const current = () => !this._selectDisposed && !this.disabled && this.comboboxOpen
                && !this.__fffSheetClosing && generation === this._selectOpenEffectsGeneration
            this.$nextTick(() => {
                if (!current()) return
                // First-mounted markup was hidden during the normal open effect.
                // Focus only after real anchoring/enter, never after a guessed delay.
                const cancel = runAfterSheetEnter(this.resolveMenuElement(), () => {
                    if (current() && this.searchable) this.focusHeadlessSearchInput()
                }, { isCurrent: current })
                this.ensurePickerResourceScope().defer(cancel)
            })
        },
        stopSelectOpenEffects() {
            this._selectOpenEffectsGeneration++
            this.unbindDropdownScrollFadeObserver()
            this.unbindHeadlessMenuPositionObservers()
            this.disconnectHeadlessLoadMoreObserver?.()
            this.freezeOptionChecksForMenuClose()
            this.teardownHeadlessMenuPosition()
        },
    }
}
