
export function createSelectRemoteMessagesMixin() {
    return {
        headlessDropdownMessage() {
            if (this.isUserSelectField || this.shouldShowHeadlessSelectEmptyState()) {
                return ''
            }

            const query = String(this.comboboxQuery ?? '').trim()
            const minLen = Number(this.minSearchLength ?? 0)

            // Same rule as empty-UI states: do not advertise loading while the user
            // still needs to type the minimum search length.
            if (this.hasDynamicSearchResults && minLen > 0 && query.length < minLen) {
                return this.searchPrompt
            }

            if (this.optionsLoading) {
                return this.loadingMessage
            }

            if (this.searchPending) {
                return this.searchingMessage
            }

            const total = this.comboboxFilteredOptions().meta.total

            if (total > 0) {
                return ''
            }

            if (this.hasDynamicSearchResults && query.length >= this.minSearchLength) {
                return this.noSearchResultsMessage
            }

            if (this.hasInitialNoOptionsMessage || this.hasDynamicOptions) {
                return this.noOptionsMessage
            }

            return this.noSearchResultsMessage
        },

        shouldShowHeadlessDropdownMessage() {
            return this.headlessDropdownMessage() !== ''
        },

        serverSideFilterFn() {
            return () => true
        },
    }
}
