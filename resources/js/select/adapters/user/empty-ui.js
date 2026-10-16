/** User-specific empty/search/skeleton projection over the shared request state. */
export function createUserSelectEmptyUiMixin() {
    return {
        headlessUserSelectDropdownState() {
            if (! this.isUserSelectField) {
                return null
            }

            const query = String(this.comboboxQuery ?? '').trim()
            const minLen = Number(this.minSearchLength ?? 0)
            const awaitsMinSearchLength = this.hasDynamicSearchResults
                && minLen > 0
                && query.length < minLen

            // Prefer the search prompt over skeleton while the query is still too short.
            // Opening the menu must not flash loading when the user has not typed yet.
            if (awaitsMinSearchLength) {
                const total = this.comboboxFilteredOptions().meta.total

                return total > 0 ? null : 'prompt'
            }

            if (this.optionsLoading) {
                return 'loading'
            }

            if (this.searchPending) {
                return 'searching'
            }

            const total = this.comboboxFilteredOptions().meta.total

            if (total > 0) {
                return null
            }

            if (this.hasDynamicSearchResults && query.length >= minLen) {
                return 'search'
            }

            return 'options'
        },

        shouldShowHeadlessUserSelectSkeleton() {
            if (this.searchError || this.optionsError) return false
            const state = this.headlessUserSelectDropdownState()

            return state === 'loading' || state === 'searching'
        },

        headlessUserSelectSkeletonAriaLabel() {
            const state = this.headlessUserSelectDropdownState()

            if (state === 'searching') {
                return this.searchingMessage ?? 'Searching users'
            }

            return this.loadingMessage ?? 'Loading users'
        },

        headlessUserSelectSkeletonRows() {
            return [0, 1, 2]
        },

        shouldShowHeadlessUserSelectEmptyState() {
            if (this.searchError || this.optionsError) return false
            const state = this.headlessUserSelectDropdownState()

            return state === 'prompt' || state === 'search' || state === 'options'
        },

        headlessUserSelectEmptyIconHtml() {
            const state = this.headlessUserSelectDropdownState()

            if (state === 'search') {
                return this.userSelectNoResultsIconHtml
            }

            return this.userSelectNoOptionsIconHtml
        },

        headlessUserSelectEmptyTitle() {
            const state = this.headlessUserSelectDropdownState()

            if (state === 'prompt') {
                return this.searchPrompt
            }

            if (state === 'search') {
                return this.noSearchResultsMessage
            }

            return this.noOptionsMessage
        },

        headlessUserSelectEmptyHint() {
            const hints = this.userSelectEmptyStateHints ?? {}
            const state = this.headlessUserSelectDropdownState()

            if (state === 'search') {
                return hints.tryDifferentSearch ?? ''
            }

            if (this.searchable && Number(this.minSearchLength ?? 0) > 0) {
                return hints.minSearchLength ?? ''
            }

            return hints.noUsersAvailable ?? ''
        },
    }
}
