import { createRequestCoordinator } from '../async/request-coordinator.js'
import { trimSearchResultsCache } from '../../core/icon-picker-cache.js'
import { callIconPickerSchemaMethod, canCallIconPickerSchemaMethod, isIconPickerSearchPayload } from '../../core/icon-picker-livewire.js'
import { buildSearchResultsCacheKey } from '../../core/icon-picker-virtual-window.js'

/** Icon provider adapter: presentation/skeleton/grid geometry stay in the component. */
export function createIconSearchMixin() {
    return {
        _iconRequests: null,
        _iconPreviewRequests: null,
        _iconPrefetch: null,
        iconSearchError: null,
        _iconFailedReset: null,
        _iconLoadedContext: null,

        ensureIconRequests() {
            return this._iconRequests ??= createRequestCoordinator()
        },

        cancelIconSearch() {
            this.ensureIconRequests().invalidate()
            this._iconPrefetch = null
            this.nextPageCache = null
            this.searchPending = false
            this.iconResultsLoading = false
            this.clearIconSkeletonTimers?.()
            this.releaseLoadMoreRequest?.({ immediate: true })
        },

        onSearchInput() {
            if (this.__fffPickerDisposed || this.readOnly) return
            this.cancelIconSearch()
            if (this.isSelectRetryBlocked()) { this._iconFailedReset = true; this.iconSearchError = { status: 429 }; this.finishIconResultsLoad(); return }
            this.searchResultsCache.clear()
            void this.fetchResults({ reset: true })
        },

        selectSet(setKey) {
            if (this.__fffPickerDisposed || this.readOnly || this.activeSet === setKey) return
            this.activeSet = setKey
            this.cancelIconSearch()
            if (this.isSelectRetryBlocked()) { this._iconFailedReset = true; this.iconSearchError = { status: 429 }; this.finishIconResultsLoad(); return }
            this.searchResultsCache.clear()
            void this.fetchResults({ reset: true })
        },

        normalizeIcons(icons) {
            if (!Array.isArray(icons)) return []
            const seen = new Set()
            return icons.flatMap((item) => {
                const name = typeof item === 'string' ? item : item?.name
                if (typeof name !== 'string' || !name || seen.has(name)) return []
                seen.add(name)
                return [{ name, label: String(typeof item === 'string' ? item : item.label ?? name) }]
            })
        },

        readSearchCache(page = this.page) {
            return this.searchResultsCache.get(buildSearchResultsCacheKey(this.searchQuery, this.activeSet, page))
        },

        writeSearchCache(payload, page = this.page, query = this.searchQuery, set = this.activeSet) {
            this.searchResultsCache.set(buildSearchResultsCacheKey(query, set, page), payload)
            trimSearchResultsCache(this.searchResultsCache)
        },

        async fetchResultsWhenLivewireReady({ reset = true, maxAttempts = 40 } = {}) {
            const allowed = () => !this.__fffPickerDisposed && (this.panelOpen || this.preload)
            if (!this.componentKey || !allowed()) return false
            if (reset && this._iconInitialFetchStarted) return this.loadedIconItems.length > 0
            if (reset) this._iconInitialFetchStarted = true
            const epoch = this.ensureIconRequests().epoch
            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                if (!allowed() || this.ensureIconRequests().epoch !== epoch) return false
                if (canCallIconPickerSchemaMethod(this)) {
                    await this.fetchResults({ reset })
                    return !this.__fffPickerDisposed && this.loadedIconItems.length > 0
                }
                await new Promise((resolve) => setTimeout(resolve, 16))
            }
            if (allowed() && this.ensureIconRequests().epoch === epoch) {
                this._iconInitialFetchStarted = false
                this._iconFailedReset = true
                this.iconSearchError = new Error('Icon Livewire transport did not become ready.')
                this.finishIconResultsLoad()
            }
            return false
        },

        async retryIconSearch() {
            if (this.__fffPickerDisposed || this.readOnly || this.searchPending || this.isSelectRetryBlocked()) return
            return this.fetchResults({ reset: this._iconFailedReset ?? this.loadedIconItems.length === 0 })
        },

        async loadMore() {
            if (!this.hasMore || this.searchPending || this.iconSearchError || this.__fffPickerDisposed) {
                this.releaseLoadMoreRequest?.({ immediate: true })
                return
            }
            return this.fetchResults({ reset: false })
        },

        async fetchResults({ reset = true } = {}) {
            if (this.__fffPickerDisposed || this.readOnly || this.isSelectRetryBlocked() || !this.componentKey || !canCallIconPickerSchemaMethod(this)) return
            const context = JSON.stringify([this.searchQuery, this.activeSet])
            if (!reset && this._iconLoadedContext !== context) reset = true
            const requests = this.ensureIconRequests()
            if (reset) this.cancelIconSearch()
            const query = this.searchQuery
            const set = this.activeSet
            const page = reset ? 1 : this.page + 1
            const key = buildSearchResultsCacheKey(query, set, page)
            const lease = requests.begin('results', { exclusive: true, context: { query, set, page } })
            if (!lease) return
            const current = () => lease.current() && !this.__fffPickerDisposed && !this.readOnly
                && this.searchQuery === query && this.activeSet === set
            this.iconSearchError = null
            if (reset) {
                this.resetVirtualScrollState()
                this.resetIconResultsScroll()
                this.syncVirtualGridGeometry?.({ force: !this.gridGeometryLocked })
                this.beginIconResultsLoad()
            } else this.searchPending = true
            try {
                let payload = this.readSearchCache(page)
                if (!isIconPickerSearchPayload(payload)) {
                    const prefetched = this._iconPrefetch
                    payload = prefetched?.key === key
                        ? await prefetched.promise
                        : await callIconPickerSchemaMethod(this, 'getIconPickerSearchResults', { query, set, page })
                    if (!current()) return
                    if (!isIconPickerSearchPayload(payload) || Number(payload.page) !== page) throw new Error('Invalid icon picker search payload')
                    this.writeSearchCache(payload, page, query, set)
                }
                if (!current()) return
                this._iconFailedReset = null
                this._iconLoadedContext = context
                this.page = page
                this.nextPageCache = null
                this.applyPagePayload(payload, { reset })
                if (this.hasMore && this.panelOpen) void this.prefetchNextPage()
            } catch (error) {
                if (current() && error?.name !== 'AbortError') {
                    this._iconFailedReset = reset
                    this.iconSearchError = error
                    this.captureSelectRequestFailure(error)
                }
            } finally {
                if (lease.current()) {
                    this.releaseLoadMoreRequest?.()
                    if (reset) this.finishIconResultsLoad()
                    else this.searchPending = false
                }
                lease.finish()
            }
        },

        async prefetchNextPage() {
            if (this.__fffPickerDisposed || this.readOnly || this.isSelectRetryBlocked() || !this.hasMore || !this.componentKey || !canCallIconPickerSchemaMethod(this)) return null
            const query = this.searchQuery
            const set = this.activeSet
            const page = this.page + 1
            const key = buildSearchResultsCacheKey(query, set, page)
            const cached = this.readSearchCache(page)
            if (isIconPickerSearchPayload(cached)) return cached
            if (this._iconPrefetch?.key === key) return this._iconPrefetch.promise
            const lease = this.ensureIconRequests().begin('prefetch', { context: { query, set, page } })
            if (!lease) return null
            const flight = { key, promise: null }
            this._iconPrefetch = flight
            flight.promise = (async () => {
                try {
                    const payload = await callIconPickerSchemaMethod(this, 'getIconPickerSearchResults', { query, set, page })
                    if (!lease.current() || !isIconPickerSearchPayload(payload)) return null
                    this.writeSearchCache(payload, page, query, set)
                    this.nextPageCache = { ...payload, page }
                    return payload
                } catch (error) {
                    if (lease.current()) this.captureSelectRequestFailure(error)
                    return null
                } finally {
                    lease.finish()
                    if (this._iconPrefetch === flight) this._iconPrefetch = null
                }
            })()
            return flight.promise
        },

        async requestSvgPreviews(icons) {
            if (this.__fffPickerDisposed || !this.componentKey || !canCallIconPickerSchemaMethod(this)) return []
            if (this.isSelectRetryBlocked()) throw Object.assign(new Error('Icon preview request is cooling down.'), { status: 429 })
            const rendered = await callIconPickerSchemaMethod(this, 'getIconPickerSvgPreviews', { icons })
            return !this.__fffPickerDisposed && Array.isArray(rendered) ? rendered : []
        },

        async syncSelectedPreview(icon) {
            if (this.__fffPickerDisposed) return
            this._iconPreviewRequests ??= createRequestCoordinator()
            const lease = this._iconPreviewRequests.begin('selected', { context: icon })
            if (!lease) return
            try {
                if (!icon) { this.selectedHtml = ''; return }
                if (!Object.hasOwn(this.svgCache, icon)) {
                    const rendered = await this.requestSvgPreviews([icon])
                    if (!lease.current() || this.state !== icon) return
                    this.writeIconSvgPreviews(rendered.filter(item => item?.name === icon))
                }
                if (lease.current() && this.state === icon) this.selectedHtml = this.svgCache[icon] ?? ''
            } catch {
                // A missing preview must not change the selected transport value.
            } finally {
                lease.finish()
            }
        },
    }
}
