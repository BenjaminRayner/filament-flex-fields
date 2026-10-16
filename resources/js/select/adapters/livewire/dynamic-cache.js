import { isReadOnlySelectCommit } from '../dependency-invalidation.js'
export const DYNAMIC_OPTIONS_INVALIDATION_DEBOUNCE_MS = 75

/** Parent commits invalidate data, not selection, and never prefetch a closed menu. */
export function createSelectDynamicCacheMixin({ livewireId }) {
    return {
        bindDynamicOptionsInvalidation() {
            if (!this.hasDynamicOptions || this.dependencyStatePaths.length || this._dynamicOptionsInvalidationBound) {
                return
            }

            if (typeof Livewire === 'undefined' || typeof Livewire.hook !== 'function') {
                return
            }

            this._dynamicOptionsInvalidationBound = true

            // Parent ->live() / dependsOn updates remorph the Livewire component while
            // this shell stays wire:ignore. Invalidate the cached list — but never on
            // commits caused by our own getOptionsForJs fetch (that loops forever and
            // leaves the dropdown stuck flickering Loading…).
            this._dynamicOptionsCommitHook = Livewire.hook('commit', ({ component, commit, succeed }) => {
                if (! this.hasDynamicOptions) {
                    return
                }

                if (livewireId && component?.id && component.id !== livewireId) {
                    return
                }

                succeed(() => {
                    // Always re-assert SSR handoff — parent live() remorphs can
                    // strip `.is-replaced` and freeze the trigger under CSS.
                    this.ensureHeadlessSsrHandoff?.()

                    if (this._selectDisposed || isReadOnlySelectCommit(commit)) {
                        return
                    }

                    this.invalidateDynamicOptionsCache({
                        // Keep painted options so the next open is soft (no hard
                        // Loading freeze while getOptionsForJs waits on Livewire).
                        clearStaleOptions: false,
                    })

                    if (this.comboboxOpen) {
                        // Parent ->live() changed while open — hard refresh, no stale list.
                        this.scheduleDynamicOptionsRefresh({ soft: false })
                    }
                    // Closed: do NOT prefetch. A background getOptionsForJs rides the
                    // same Livewire pipeline as Country ->live() and freezes sibling
                    // triggers under wire:loading / main-thread morph for seconds.
                })
            })
        },

        /**
         * @deprecated No-op. Background option prefetch blocked Region clicks via
         * Livewire loading; options load when the menu opens instead.
         */
        scheduleIdleDynamicOptionsPrefetch() {
            if (this._dynamicOptionsIdlePrefetchTimer) {
                clearTimeout(this._dynamicOptionsIdlePrefetchTimer)
                this._dynamicOptionsIdlePrefetchTimer = null
            }
        },

        /**
         * Mark the client option cache stale. When the menu is closed, drop the
         * painted list so a later open cannot flash the previous parent’s options
         * (Country USA → Poland) and never kick off a Livewire fetch in the
         * background — that blocks the trigger for seconds via wire:loading.
         */
        invalidateDynamicOptionsCache({ clearStaleOptions = false } = {}) {
            this.dynamicOptionsLoaded = false
            this._selectOptionsRequests.invalidate()
            if (this._dynamicOptionsFetchInFlight) {
                this._fetchDynamicOptionsQueued = true
                this._fetchDynamicOptionsQueuedSoft = false
            }

            if (
                clearStaleOptions
                && Array.isArray(this.flatOptions)
                && this.flatOptions.length > 0
            ) {
                this.applyRemoteOptions([], { pruneSelection: false })
            }
        },

        scheduleDynamicOptionsRefresh({ soft = true } = {}) {
            if (! this.hasDynamicOptions) {
                return
            }

            if (this._dynamicOptionsRefreshTimer) {
                clearTimeout(this._dynamicOptionsRefreshTimer)
            }

            this._dynamicOptionsRefreshTimer = setTimeout(() => {
                this._dynamicOptionsRefreshTimer = null

                if (
                    this._selectDisposed
                    || ! this.comboboxOpen
                    || this.optionsLoading
                    || this._dynamicOptionsFetchInFlight
                ) {
                    return
                }

                if (this.dynamicOptionsLoaded) {
                    return
                }

                this.fetchDynamicOptions({ soft })
            }, DYNAMIC_OPTIONS_INVALIDATION_DEBOUNCE_MS)
        },

        teardownDynamicOptionsInvalidation() {
            if (typeof this._dynamicOptionsCommitHook === 'function') {
                this._dynamicOptionsCommitHook()
                this._dynamicOptionsCommitHook = null
            }

            if (this._dynamicOptionsRefreshTimer) {
                clearTimeout(this._dynamicOptionsRefreshTimer)
                this._dynamicOptionsRefreshTimer = null
            }

            if (this._dynamicOptionsIdlePrefetchTimer) {
                clearTimeout(this._dynamicOptionsIdlePrefetchTimer)
                this._dynamicOptionsIdlePrefetchTimer = null
            }

            this._dynamicOptionsInvalidationBound = false
        },

    }
}
