import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { isDomAssetElementActive } from '../runtime/lifecycle/dom-activation.js'
import { fetchUrlMeta, readUrlMetaCache } from '../support/url-meta-scrape.js'
import { extractDomain, isScrapeCandidate, isValidHttpUrl, normalizeLinkMetadata, resolveUrlWithPrefix, stripUrlPrefix } from './values.js'
import { preloadLinkImage, waitForLinkPreview } from './loading.js'

export function createLinkPreview(options = {}) {
    const scope = createResourceScope()
    let ready = null, generation = 0, controller = null, cancelDebounce = null, lastUrl = null, active = true
    const cancel = () => { generation++; cancelDebounce?.(); cancelDebounce = null; controller?.abort(); controller = null }
    scope.defer(cancel)
    const empty = () => ({ title: null, description: null, image: null, domain: null })
    return {
        state: null, initialUrl: '', initialPreview: null, disabled: false, readOnly: false, previewEnabled: true,
        scrapeUrl: null, previewDebounce: 500, previewMinUrlLength: 10, previewMinSkeletonMs: 500,
        previewLayout: 'horizontal', showVisitLink: true, prefix: null, labels: {}, ...options,
        inputValue: stripUrlPrefix(typeof options.state === 'string' ? options.state : options.initialUrl ?? '', options.prefix),
        preview: options.initialPreview ? { ...normalizeLinkMetadata(options.initialPreview, globalThis.document?.baseURI), domain: extractDomain(options.initialUrl) } : empty(), isFetching: false, isImagePending: false, isMinRevealPending: false, error: null,
        get resolvedUrl() { return resolveUrlWithPrefix(this.inputValue, this.prefix) },
        get canVisit() { return isValidHttpUrl(this.resolvedUrl) },
        get visitUrl() { return this.canVisit ? this.resolvedUrl : null },
        get hasPreview() { return Boolean(this.preview.title || this.preview.description || this.preview.image) },
        get showSkeleton() { return this.isFetching || this.isImagePending || this.isMinRevealPending },
        get isRevealed() { return !this.showSkeleton && this.hasPreview },
        get shouldShowThumb() { return this.showSkeleton || Boolean(this.preview.image) },
        get shouldShowCard() { return this.previewEnabled && (this.showSkeleton || this.hasPreview) },
        init() {
            if (ready || scope.disposed) return ready
            ready = (async () => {
                if (!await waitForAssetActivation(this.$el, { signal: scope.signal }) || scope.disposed) return
                await this.$nextTick()
                const initial = stripUrlPrefix(typeof this.state === 'string' ? this.state : this.initialUrl, this.prefix)
                const input = this.$refs.input
                const edited = !this.disabled && !this.readOnly && this.$el.ownerDocument.activeElement === input
                    && input?.getAttribute?.('value') === initial && input.value !== initial
                this.inputValue = edited ? input.value : initial
                if (edited) this.state = this.resolvedUrl || null
                this.paintInput()
                if (this.initialPreview && !edited) {
                    this.preview = { ...normalizeLinkMetadata(this.initialPreview, this.$el.ownerDocument.baseURI), domain: extractDomain(this.resolvedUrl) }
                    lastUrl = this.resolvedUrl
                } else this.refresh()
                const stop = this.$watch?.('state', () => {
                    if (scope.disposed) return
                    const value = stripUrlPrefix(typeof this.state === 'string' ? this.state : '', this.prefix)
                    if (value === this.inputValue) return
                    this.inputValue = value; this.paintInput(); this.refresh()
                })
                if (typeof stop === 'function') scope.defer(stop)
                const Observer = this.$el.ownerDocument.defaultView.MutationObserver
                if (Observer) {
                    const observer = new Observer(() => {
                        const next = isDomAssetElementActive(this.$el)
                        if (next === active) return
                        active = next
                        if (!active) { cancel(); this.isFetching = this.isImagePending = this.isMinRevealPending = false }
                        else this.refresh()
                    })
                    for (let parent = this.$el.parentElement; parent && parent !== this.$el.ownerDocument.body; parent = parent.parentElement) {
                        observer.observe(parent, { attributes: true, attributeFilter: ['class', 'style', 'hidden', 'aria-hidden', 'open', 'data-fff-asset-active'] })
                    }
                    scope.trackObserver(observer)
                }
            })().catch(error => { scope.dispose(); throw error })
            return ready
        },
        paintInput() { if (this.$refs.input && this.$refs.input.value !== this.inputValue) this.$refs.input.value = this.inputValue },
        onInput(value) {
            if (this.disabled || this.readOnly || scope.disposed) return
            this.inputValue = String(value).slice(0, 2048); this.state = this.resolvedUrl || null
            this.error = null; cancel(); lastUrl = null
            if (!this.previewEnabled || !active) { this.resetPreview(); return }
            if (!isScrapeCandidate(this.resolvedUrl, this.previewMinUrlLength)) { this.resetPreview(); return }
            this.preview = empty(); this.isFetching = true; this.isImagePending = this.isMinRevealPending = false
            cancelDebounce = scope.timeout(() => { cancelDebounce = null; this.refresh() }, this.previewDebounce)
        },
        onBlur() { this.inputValue = stripUrlPrefix(this.resolvedUrl, this.prefix); this.paintInput() },
        resetPreview() { cancel(); lastUrl = null; this.preview = empty(); this.isFetching = this.isImagePending = this.isMinRevealPending = false; this.error = null },
        async refresh() {
            const url = this.resolvedUrl
            if (scope.disposed || !active || this.disabled || !this.previewEnabled) return
            if (!isScrapeCandidate(url, this.previewMinUrlLength)) { this.resetPreview(); return }
            if (url === lastUrl && this.hasPreview) return
            cancel(); const token = generation, request = new AbortController(); controller = request
            const release = scope.defer(() => request.abort())
            const cancelDeadline = scope.timeout(() => {
                if (token !== generation || scope.disposed) return
                request.abort(); this.preview = empty(); this.isFetching = this.isImagePending = this.isMinRevealPending = false
                this.error = this.labels.error ?? 'Preview unavailable'
            }, 15000)
            const started = this.$el.ownerDocument.defaultView.performance.now()
            this.error = null; this.isFetching = true; this.preview = empty()
            const current = () => !scope.disposed && !request.signal.aborted && token === generation && this.resolvedUrl === url
            try {
                const cached = readUrlMetaCache(url, this.scrapeUrl)
                const result = normalizeLinkMetadata(cached ?? await fetchUrlMeta(this.scrapeUrl, url, request.signal), this.$el.ownerDocument.baseURI)
                if (!current()) return
                this.preview = { ...result, domain: extractDomain(url) }
                this.isImagePending = Boolean(result.image)
                if (result.image && !await preloadLinkImage(result.image, this.$el.ownerDocument, request.signal, scope)) {
                    if (!current()) return
                    this.preview.image = null
                }
                if (!current()) return
                this.isImagePending = false
                if (!cached && this.hasPreview) {
                    const remaining = Math.max(0, this.previewMinSkeletonMs - (this.$el.ownerDocument.defaultView.performance.now() - started))
                    this.isMinRevealPending = remaining > 0
                    if (!await waitForLinkPreview(remaining, request.signal, scope) || !current()) return
                }
                this.isMinRevealPending = false; this.isFetching = false; lastUrl = url
            } catch (error) {
                if (!current() || error?.name === 'AbortError') return
                this.preview = empty(); this.isFetching = this.isImagePending = this.isMinRevealPending = false
                this.error = this.labels.error ?? 'Preview unavailable'
            } finally {
                cancelDeadline(); release(); if (controller === request) controller = null
            }
        },
        onImageError() { this.preview.image = null; this.isImagePending = false },
        destroy() { if (!scope.disposed) scope.dispose() },
        getResourceSnapshot() { return scope.snapshot() },
    }
}
