/** Loaded only by the local diagnostic pages; its poller dies with the Alpine root. */
export default () => ({
    assets: [], stack: [], inflight: 0, duplicates: 0, events: [], slowReveal: false,
    layoutScore: null, layoutShifts: [], _layoutObserver: null, _layoutSession: null, _layoutSequence: 0,
    _timer: null, _restore: null, _previous: new Set(), _sequence: 0,
    init() {
        const runtime = window.FffAssetInjector
        if (!this.$el.closest('.fi-modal')) this._restore = runtime?.registerInjectorHooks({ getPendingReleaseDelayMs: () => this.slowReveal ? 1200 : 0 })
        this.observeLayout()
        this.refresh()
        this._timer = window.setInterval(() => { if (!document.hidden && (!this.$el.closest('.fi-modal') || this.$el.closest('.fi-modal').classList.contains('fi-modal-open'))) this.refresh() }, 250)
    },
    observeLayout() {
        if (typeof PerformanceObserver === 'undefined' || !PerformanceObserver.supportedEntryTypes?.includes('layout-shift')) return
        this.layoutScore = 0
        this._layoutObserver = new PerformanceObserver(list => {
            for (const entry of list.getEntries()) {
                if (entry.hadRecentInput) continue
                // Rendering the measurement log must not recursively measure itself.
                const sources = entry.sources || []
                if (sources.length && sources.every(source => source.node?.closest?.('.fff-asset-lab__inspector'))) continue
                const session = this._layoutSession
                if (!session || entry.startTime - session.last > 1000 || entry.startTime - session.start > 5000) {
                    this._layoutSession = { start: entry.startTime, last: entry.startTime, score: entry.value }
                } else {
                    session.last = entry.startTime
                    session.score += entry.value
                }
                this.layoutScore = Math.max(this.layoutScore, this._layoutSession.score)
                this.layoutShifts.push({
                    id: ++this._layoutSequence, time: Math.round(entry.startTime), score: entry.value,
                    sources: sources.map(source => source.node?.className || source.node?.nodeName || 'detached').join(', '),
                })
                this.layoutShifts = this.layoutShifts.slice(-12)
            }
        })
        // Buffered records include the first paint, before this lazy inspector booted.
        this._layoutObserver.observe({ type: 'layout-shift', buffered: true })
    },
    refresh() {
        const runtime = window.FffAssetInjector
        if (!runtime) return
        const graph = runtime.getConsumerGraph()
        const links = [...document.querySelectorAll('link[data-fff-managed-asset]')]
        const urls = links.map(link => link.href)
        this.duplicates = urls.length - new Set(urls).size
        this.stack = runtime.getModalOpenStack()
        this.inflight = runtime.getInflightUrls().length
        this.assets = links.filter(link => link.rel === 'stylesheet').map(link => ({
            url: link.href, name: new URL(link.href).pathname.split('/').pop(), refs: graph.getRefCount(link.href),
            owners: [...new Set(graph.getConsumersForUrl(link.href).map(consumer => runtime.resolveAssetOwnerKey(consumer.element)))].join(', '),
        })).sort((a, b) => a.name.localeCompare(b.name))
        const current = new Set(this.assets.map(asset => asset.name))
        const log = text => this.events.unshift({ id: ++this._sequence, time: new Date().toLocaleTimeString(), text })
        for (const name of current) if (!this._previous.has(name)) log('+ ' + name)
        for (const name of this._previous) if (!current.has(name)) log('− ' + name)
        this.events = this.events.slice(0, 24)
        this._previous = current
    },
    destroy() { this._layoutObserver?.disconnect(); window.clearInterval(this._timer); this._restore?.() },
})
