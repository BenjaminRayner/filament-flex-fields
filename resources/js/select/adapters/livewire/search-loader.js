import { mergeSelectOptionPages, normalizeSelectPage } from '../../async/page-publication.js'
import { flattenHeadlessOptions } from '../../options/option-tree.js'
import { emitObservabilityEvent } from '../../../core/observability.js'

/** Search/page publication belongs to one request epoch and query snapshot. */
export function createSelectSearchLoaderMixin() {
    return {
        async fetchSearchResults(search, observabilityQuery = search, { append = false } = {}) {
            if (this._selectDisposed || this.disabled || this.isSelectRetryBlocked?.() || !this.hasDynamicSearchResults) return
            const requests = this._selectSearchRequests
            if (append && (!this.hasPaginatedSearchResults || !this.searchResultsHasMore || this.loadingMore || this.searchPending)) return
            if (!append) {
                this._selectOptionsRequests?.invalidate()
                this.optionsError = null
                this.optionsLoading = false
                requests.invalidate()
                this._relationshipAdapter?.cancel?.()
                this.resetPaginatedSearchState()
                this._activeSearchQuery = String(search ?? '')
                this.searchPending = true
            }
            const cursor = append ? this.searchResultsCursor : null
            const lease = requests.begin(append ? 'page' : 'search', {
                exclusive: append, context: { search, cursor },
            })
            if (!lease) return
            const queryAtStart = String(this.comboboxQuery ?? '')
            const searchEpoch = requests.epoch
            const current = () => lease.current() && String(this.comboboxQuery ?? '') === queryAtStart
            if (append) this.loadingMore = true
            this.searchError = null
            emitObservabilityEvent('select.search', {
                field: this.componentKey,
                queryLength: String(observabilityQuery ?? '').length,
                source: append ? 'livewire-load-more' : 'livewire',
                mention: this.comboboxEntityMentionActive?.() ?? false,
            })
            try {
                let normalized
                let nextCursor = null
                let hasMore = false
                if (this.hasPaginatedSearchResults) {
                    const page = this._selectDataSource
                        ? await this._selectDataSource.search({ query: search, cursor, signal: lease.signal })
                        : await this.callSchemaMethod('getSearchResultsPageForJs', { search, cursor })
                    if (!current()) return
                    const validated = normalizeSelectPage(page, { previousCursor: cursor, visitedCursors: this._searchPageCursors, maxBytes: this.maxPageBytes })
                    nextCursor = validated.cursor
                    hasMore = validated.hasMore
                    normalized = validated.items
                } else {
                    const results = this._selectDataSource
                        ? await this._selectDataSource.search({ query: search, cursor: null, signal: lease.signal })
                        : this._relationshipAdapter
                        ? await this._relationshipAdapter.search(search, {
                            onWarning: (warning) => emitObservabilityEvent('select.search.warning', { field: this.componentKey, ...warning }),
                        })
                        : await this.callSchemaMethod('getSearchResultsForJs', { search })
                    if (!current()) return
                    if (!Array.isArray(results)) throw new Error('Invalid Select search response.')
                    normalized = normalizeSelectPage({items: results, hasMore: false}, {maxBytes: this.maxPageBytes}).items
                }
                if (!current()) return
                const published = append ? mergeSelectOptionPages(this.options, normalized) : normalized
                flattenHeadlessOptions(published)
                this.applyRemoteOptions(published)
                this.populateLabelRepositoryFromOptions(normalized)
                this.searchResultsCursor = hasMore ? nextCursor : null
                this.searchResultsHasMore = hasMore
                if (hasMore) (this._searchPageCursors ??= new Set()).add(nextCursor)
            } catch (error) {
                if (!current() || error?.name === 'AbortError') return
                // Retain painted rows and cursor so retry cannot destroy a valid selection.
                this.searchError = error
                this.captureSelectRequestFailure(error)
                emitObservabilityEvent('select.search.error', { field: this.componentKey, source: append ? 'page' : 'search' })
            } finally {
                if (lease.current()) {
                    if (append) this.loadingMore = false
                    else this.searchPending = false
                    this.$nextTick?.(() => {
                        if (this._selectDisposed || !this.comboboxOpen || requests.epoch !== searchEpoch
                            || String(this.comboboxQuery ?? '') !== queryAtStart) return
                        this.observeHeadlessLoadMore?.()
                        this.syncDropdownOverflowChrome?.()
                    })
                }
                lease.finish()
            }
        },

        async loadMoreSearchResults() {
            if (this._selectDisposed || !this.comboboxOpen || this.disabled || this.searchError) return
            const query = this._activeSearchQuery || String(this.comboboxQuery ?? '').trim()
            if (query.length < this.minSearchLength) return
            await this.fetchSearchResults(query, query, { append: true })
        },

        async retryHeadlessSearch() {
            if (this._selectDisposed || this.disabled || this.isSelectRetryBlocked?.()) return
            const append = this.searchResultsCursor !== null && this.searchResultsHasMore
            this.searchError = null
            await this.fetchSearchResults(this._activeSearchQuery || String(this.comboboxQuery ?? '').trim(), undefined, { append })
        },

        appendRemoteOptions(nextOptions) {
            this.applyRemoteOptions(mergeSelectOptionPages(this.options, nextOptions))
        },

        resetPaginatedSearchState() {
            this.searchResultsCursor = null
            this.searchResultsHasMore = false
            this.loadingMore = false
            this._activeSearchQuery = ''
            this._searchPageCursors = new Set()
        },

    }
}
