import { shouldApplyRemoteOptionsFromFetchResult } from '../../../components/select-field/headless-select-state.js'

/** One actual options flight; repeated callers join it instead of scheduling duplicates. */
export function createSelectDynamicLoaderMixin() {
    return {
        _dynamicOptionsPromise: null,
        fetchDynamicOptions({ soft = false } = {}) {
            if (this._selectDisposed || this.disabled || this.isSelectRetryBlocked?.() || !this.hasDynamicOptions) return Promise.resolve(false)
            if (this.hasDynamicSearchResults && String(this.comboboxQuery ?? '').trim() !== '') {
                this.scheduleAsyncSearch(this.comboboxQuery)
                return Promise.resolve(false)
            }
            if (this._dynamicOptionsPromise) return this._dynamicOptionsPromise
            const lease = this._selectOptionsRequests.begin('options')
            if (!lease) return Promise.resolve(false)
            this._dynamicOptionsFetchInFlight = true
            this.optionsError = null
            this.optionsLoading = !(soft || this.flatOptions?.length > 0)
            // Assign the promise before invoking even a synchronously failing
            // transport or a reentrant caller; both must observe this same flight.
            this._dynamicOptionsPromise = Promise.resolve().then(() => this.performDynamicOptionsRequest(lease))
            return this._dynamicOptionsPromise
        },
        async performDynamicOptionsRequest(lease) {
            let loaded = false
            try {
                if (lease.current()) {
                    const results = await this.callSchemaMethod('getOptionsForJs')
                    if (lease.current()) {
                        if (!shouldApplyRemoteOptionsFromFetchResult(results)) throw new Error('Invalid dynamic Select options response.')
                        this.applyRemoteOptions(results)
                        this.dynamicOptionsLoaded = true
                        loaded = true
                    }
                }
            } catch (error) {
                if (lease.current() && error?.name !== 'AbortError') {
                    this.optionsError = error
                    this.captureSelectRequestFailure(error)
                }
            } finally {
                lease.finish()
                this._dynamicOptionsPromise = null
                this._dynamicOptionsFetchInFlight = false
                this.optionsLoading = false
            }
            const queued = this._fetchDynamicOptionsQueued
            const soft = this._fetchDynamicOptionsQueuedSoft !== false
            this._fetchDynamicOptionsQueued = false
            this._fetchDynamicOptionsQueuedSoft = undefined
            if (this._selectDisposed) return false
            // A parent changed during the flight. Only an open owner refreshes;
            // closing leaves the cache stale for the next demand, not a background call.
            if (queued && this.comboboxOpen && !this.disabled) return this.fetchDynamicOptions({ soft })
            return loaded
        },
        async retryHeadlessRequest() {
            if (this._selectDisposed || this.disabled || this.isSelectRetryBlocked?.()) return false
            if (this.optionsError) return this.fetchDynamicOptions({ soft: true })
            return this.retryHeadlessSearch()
        },
    }
}
