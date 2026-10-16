/** Query intentions invalidate old search/page flights before debounce starts. */
export function createSelectSearchIntentMixin() {
    return {
        scheduleAsyncSearch(query) {
            if (this._selectDisposed) return
            clearTimeout(this._searchDebounceTimer)
            const requests = this._selectSearchRequests
            const epoch = requests.invalidate()
            this._relationshipAdapter?.cancel?.()
            this.resetPaginatedSearchState()
            this.searchError = null
            // Closing resets query after cancellation. That watcher must not
            // create a fresh background search once the owner is already closed.
            if (!this.comboboxOpen || this.disabled) {
                this.searchPending = false
                return
            }
            // Search owns the painted collection. A pending default-suggestions
            // response must not replace it or leave its error covering new results.
            this._selectOptionsRequests?.invalidate()
            this.optionsError = null
            this.optionsLoading = false
            this._fetchDynamicOptionsQueued = false
            this._fetchDynamicOptionsQueuedSoft = undefined
            const raw = String(query ?? '').trim()
            if (this.isSelectRetryBlocked?.()) {
                this.searchPending = false
                this.searchError = { status: 429 }
                return
            }
            const term = typeof this.comboboxEntityMentionSearchTerm === 'function'
                ? this.comboboxEntityMentionSearchTerm() : raw
            if (term.length < this.minSearchLength) {
                this.searchPending = false
                this.disconnectHeadlessLoadMoreObserver()
                this.applyRemoteOptions([])
                return
            }
            this.searchPending = true
            this._searchDebounceTimer = setTimeout(() => {
                this._searchDebounceTimer = null
                if (!this._selectDisposed && this.comboboxOpen && !this.disabled && requests.epoch === epoch) {
                    void this.fetchSearchResults(term, raw)
                }
            }, this._relationshipAdapter?.debounceMs ?? this.searchDebounce)
        },

        cancelRelationshipSearch() {
            this._selectSearchRequests?.invalidate()
            this._relationshipAdapter?.cancel?.()
            clearTimeout(this._searchDebounceTimer)
            this._searchDebounceTimer = null
            this.searchPending = false
            this.resetPaginatedSearchState()
            this.disconnectHeadlessLoadMoreObserver()
        },
    }
}
