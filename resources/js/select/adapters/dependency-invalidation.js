const READ_METHODS = new Set([
    'getOptionsForJs', 'getSearchResultsForJs', 'getSearchResultsPageForJs',
    'getOptionLabel', 'getOptionLabelsForJs', 'getIconPickerSearchResults', 'getIconPickerSvgPreviews',
])

/** Read-only schema calls cannot change the source of dynamic option closures. */
export function isReadOnlySelectCommit(commit) {
    if (commit == null || typeof commit !== 'object') return false
    if (Object.keys(commit.updates ?? {}).length > 0) return false
    const calls = Array.isArray(commit.calls) ? commit.calls : []
    return calls.length === 0 || calls.every((call) => (
        call.method === 'callSchemaComponentMethod' && READ_METHODS.has(call.params?.[1])
    ))
}

/** Explicit dependencies react to state, never to arbitrary transport timing. */
export function createDependencyInvalidationMixin() {
    return {
        _selectDependenciesBound: false,
        bindSelectDependencies() {
            if (this._selectDependenciesBound || !this.dependencyStatePaths?.length) return
            this._selectDependenciesBound = true
            for (const path of this.dependencyStatePaths) {
                this.$watch(`$wire.get(${JSON.stringify(path)})`, () => {
                    if (this._selectDisposed) return
                    this.ensureHeadlessSsrHandoff?.()
                    this._selectLabelRequests?.invalidate()
                    this.cancelRelationshipSearch?.()
                    if (this.resetOnDependencyChange) this.resetDependentSelection()
                    this.invalidateDynamicOptionsCache({ clearStaleOptions: true })
                    if (!this.comboboxOpen) return
                    if (this.hasDynamicOptions) this.scheduleDynamicOptionsRefresh({ soft: false })
                    else if (this.hasDynamicSearchResults) this.scheduleAsyncSearch(this.comboboxQuery)
                })
            }
        },
        resetDependentSelection() {
            if (this._selectDisposed || !this._engine) return
            // This is dependency reconciliation, not a user clear command: it
            // also works for non-clearable fields and must not restore SSR state.
            this._userHasMutatedSelection = true
            this.reconcileSelectWireState([])
            this.syncStateFromEngine([])
        },
    }
}
