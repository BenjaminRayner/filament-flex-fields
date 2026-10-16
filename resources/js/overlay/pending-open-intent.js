import { openExclusiveFlexDropdown, resolveDropdownOwnerId } from '../core/flex-dropdown-coordinator.js'
import { shouldHandlePickerKey } from './keyboard-policy.js'

/** Shared arbitration for pickers that await catalogs/imports before painting. */
export function createPendingPickerOpenMixin() {
    return {
        _pendingPickerOpenGeneration: 0,
        _pendingPickerOpen: false,
        _pendingPickerOpenIntent: null,
        _pendingPickerOpenBound: false,
        bindPendingPickerOpen() {
            if (this._pendingPickerOpenBound) return
            this._pendingPickerOpenBound = true
            const scope = this.ensurePickerResourceScope()
            scope.defer(() => this.cancelPendingPickerOpen())
            scope.listen(document, 'livewire:navigated', () => this.cancelPendingPickerOpen())
            scope.listen(document, 'keydown', (event) => {
                if (this._pendingPickerOpen && event.key === 'Escape' && shouldHandlePickerKey(event)) this.cancelPendingPickerOpen()
            })
            scope.listen(document, 'pointerdown', (event) => {
                const host = this._pickerHostElement ?? this.$el
                if (this._pendingPickerOpen && host && !host.contains(event.target)) this.cancelPendingPickerOpen()
            }, { capture: true })
        },
        cancelPendingPickerOpen() {
            this._pendingPickerOpenGeneration++
            this._pendingPickerOpenIntent?.cancel()
            this._pendingPickerOpenIntent = null
            this._pendingPickerOpen = false
        },
        async runPendingPickerOpen({openKey, ownerIdPrefix, prepare, commit, locked = () => this.isLocked}) {
            if (locked() || this.__fffPickerDisposed) return
            this.bindPendingPickerOpen()
            if (this[openKey] || this._pendingPickerOpen) {
                this.cancelPendingPickerOpen()
                this.closeTeleportedMenu()
                return
            }
            const generation = ++this._pendingPickerOpenGeneration
            const owner = resolveDropdownOwnerId(this._pickerHostElement ?? this.$el, ownerIdPrefix)
            const intent = owner ? openExclusiveFlexDropdown(owner) : null
            if (!intent) return
            this._pendingPickerOpenIntent = intent
            this._pendingPickerOpen = true
            try {
                await prepare()
                if (this.__fffPickerDisposed || locked() || generation !== this._pendingPickerOpenGeneration || !intent.current()) return
                commit()
            } finally {
                if (generation === this._pendingPickerOpenGeneration) {
                    this._pendingPickerOpen = false
                    this._pendingPickerOpenIntent = null
                }
            }
        },
        focusPendingPickerSearch(openKey, ref) {
            const generation = this._pendingPickerOpenGeneration
            this.$nextTick(() => {
                if (!this.__fffPickerDisposed && this[openKey] && generation === this._pendingPickerOpenGeneration) this.$refs[ref]?.focus()
            })
        },
    }
}
