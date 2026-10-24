/** Remote loading runs only for the opening intent that requested it. */
export function createSelectMenuRequestsMixin() {
    return {
        async onHeadlessMenuOpenedForLivewire() {
            const generation = this._selectOpenEffectsGeneration
            const current = () => !this._selectDisposed && !this.disabled && this.comboboxOpen
                && generation === this._selectOpenEffectsGeneration
            if (!current()) return
            this.ensureHeadlessSsrHandoff?.()
            if (this._selectDataSource) {
                // A client provider uses this same request owner, but never invokes
                // Filament schema methods or binds application-wide commit hooks.
                this.scheduleAsyncSearch(this.comboboxQuery)
                return
            }

            // Always re-fetch dynamic / dependsOn option closures — parent live()
            // updates must not leave a stale empty list behind wire:ignore.
            if (this.hasDynamicOptions) {
                this.dynamicOptionsLoaded = false
                const soft = Array.isArray(this.flatOptions) && this.flatOptions.length > 0
                const loaded = await this.fetchDynamicOptions({ soft })

                if (!current()) return
                if (! loaded) {
                    this.dynamicOptionsLoaded = false
                }
            }

            if (
                this.hasDynamicSearchResults
                && this.isPreloaded
                && this.comboboxQuery.trim() === ''
                && this.flatOptions.length === 0
                && ! this.hasDynamicOptions
                // Fields with a min search length show the search prompt on open —
                // do not hard-load options (and flash a skeleton) before the user types.
                && Number(this.minSearchLength ?? 0) <= 0
            ) {
                await this.fetchDynamicOptions()
                if (!current()) return
            }

            // Input can precede the optional remote module's query watcher.
            // Recover that intent on opening, without restarting a pending search.
            if (this.hasDynamicSearchResults && !this.hasPaginatedSearchResults
                && this.comboboxQuery.trim() !== '' && !this.searchPending) {
                this.scheduleAsyncSearch(this.comboboxQuery)
            }

            if (
                this.hasPaginatedSearchResults
                && this.comboboxQuery.trim().length >= this.minSearchLength
                && ! this.searchPending
            ) {
                // Closing cancels page flights and resets the cursor. Painted
                // options can belong to the previous query, so a nonempty list
                // cannot prove that the current query has an initial page.
                await this.fetchSearchResults(this.comboboxQuery.trim(), this.comboboxQuery.trim(), { append: false })
                if (!current()) return
            }

            if (this._pendingSelectedLabelRefresh || this.hasMissingSelectedLabels()) {
                this._pendingSelectedLabelRefresh = false
                await this.resolveSelectedLabelsFromServer()
            }
        },

    }
}
