import { enqueueTextareaMeasurement } from './measurement-batch.js'

/** Independent mirrors and metrics; layout reads/writes share document phases. */
export function createTextareaAutosize(root, input, options, scope) {
    if (!options.shouldAutosize) return { schedule() {}, measure() {}, initialize: () => Promise.resolve(true) }
    const document = input.ownerDocument
    let mirror = null, presentation = null, nativeHeight = null
    scope.defer(() => mirror?.remove())
    const freezeNativeHeight = () => {
        if (nativeHeight === null) return
        input.style.height = `${nativeHeight}px`
        input.style.fieldSizing = 'fixed'
        nativeHeight = null
    }
    let pending = null, refreshPending = false, lastWidth = -1, observedWidth = -1, metrics = null, first = true, fontReadyObserved = false
    let initialReady = null, resolveInitial = null, rejectInitial = null
    const readMetrics = () => {
        const css = document.defaultView.getComputedStyle(input)
        // Computed styles are live: snapshot every read before changing the mirror.
        const presentation = ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'textTransform', 'tabSize', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight', 'wordBreak', 'overflowWrap'].map(name => [name, css[name]])
        return { presentation, native: css.fieldSizing === 'content' && css.writingMode === 'horizontal-tb' && !input.style.height
            && [css.paddingTop, css.paddingBottom, css.borderTopWidth, css.borderBottomWidth].every(value => parseFloat(value) === 0)
            && document.fonts?.status !== 'loading', metrics: {
            minimum: options.initialHeightRem * parseFloat(document.defaultView.getComputedStyle(document.documentElement).fontSize),
            maximum: css.maxHeight?.endsWith('px') ? parseFloat(css.maxHeight) : Infinity,
            inlinePadding: (parseFloat(css.paddingLeft) || 0) + (parseFloat(css.paddingRight) || 0),
        } }
    }
    const task = {
        read() {
            if (scope.disposed || !input.isConnected) return null
            const width = input.clientWidth
            if (width <= 0) return null
            // FontFaceSet.ready can flush styles synchronously. Read it alongside
            // the other layout reads, never between owners appending mirrors.
            if (!fontReadyObserved && document.fonts) {
                fontReadyObserved = true
                if (document.fonts.status !== 'loaded') {
                    document.fonts.ready.then(() => { if (!scope.disposed) schedule(true) })
                }
            }
            const snapshot = !metrics || refreshPending || width !== lastWidth ? readMetrics() : null
            refreshPending = false
            return { width, snapshot, value: input.value || input.placeholder || '',
                nativeHeight: first && snapshot?.native ? input.clientHeight : null }
        },
        prepare({ width, snapshot, value, nativeHeight }) {
            if (snapshot) {
                metrics = snapshot.metrics
                presentation = snapshot.presentation
            }
            lastWidth = width
            if (nativeHeight !== null) return
            const creating = !mirror
            if (creating) {
                mirror = document.createElement('textarea')
                mirror.className = 'fff-flex-textarea__measure'
                mirror.tabIndex = -1
                mirror.setAttribute('aria-hidden', 'true')
                root.querySelector('[data-textarea-measure-host]').appendChild(mirror)
            }
            if (creating || snapshot) for (const [name, value] of presentation) mirror.style[name] = value
            mirror.style.width = `${width}px`
            mirror.value = value
        },
        height(measurement) {
            measurement.height = Math.max(metrics.minimum, Math.min(measurement.nativeHeight ?? mirror.scrollHeight, metrics.maximum))
        },
        commit(measurement) {
            const { height } = measurement
            if (measurement.nativeHeight !== null) {
                // Keep the already painted native geometry. The first edit or
                // condition change freezes it before the next animated measure.
                nativeHeight = height
                first = false
                resolveInitial?.(true)
                resolveInitial = rejectInitial = null
                return
            }
            if (first) input.style.transition = 'none'
            input.style.fieldSizing = 'fixed'
            if (input.style.height !== `${height}px`) input.style.height = `${height}px`
            if (first) {
                first = false
                scope.frame(() => { input.style.transition = '' })
            }
            resolveInitial?.(true)
            resolveInitial = rejectInitial = null
        },
    }
    // Synchronous measurement remains available to explicit callers. Runtime
    // readiness uses initialize(), which resolves after the batched commit.
    const measure = (refresh = false) => {
        freezeNativeHeight()
        refreshPending ||= refresh
        const measurement = task.read()
        if (!measurement) return
        task.prepare(measurement)
        task.height(measurement)
        task.commit(measurement)
    }
    const enqueue = (refresh = false) => {
        refreshPending ||= refresh
        if (scope.disposed) return Promise.resolve(false)
        if (!pending) {
            const job = enqueueTextareaMeasurement(document, task)
            pending = job
            const clear = () => { if (pending === job) pending = null }
            job.promise.then(clear, clear)
        }
        return pending.promise
    }
    scope.defer(() => {
        pending?.cancel(); pending = null
        resolveInitial?.(false)
        resolveInitial = rejectInitial = null
    })
    const schedule = (refresh = false) => {
        freezeNativeHeight()
        if (pending) { refreshPending ||= refresh; return }
        // Report deferred errors through this owner's normal effect reporter.
        enqueue(refresh).catch(error => {
            if (rejectInitial) {
                rejectInitial(error)
                resolveInitial = rejectInitial = null
            } else if (!scope.disposed) scope.frame(() => { throw error })
        })
    }
    const initialize = () => {
        if (scope.disposed) return Promise.resolve(false)
        if (!initialReady) {
            initialReady = new Promise((resolve, reject) => { resolveInitial = resolve; rejectInitial = reject })
            enqueue().catch(error => {
                rejectInitial?.(error)
                resolveInitial = rejectInitial = null
            })
        }
        // A temporarily zero-width owner waits for its first real commit, not
        // just an empty queue pass. Resize/activation can then complete readiness.
        return initialReady
    }
    const Observer = document.defaultView.ResizeObserver
    if (Observer) {
        const observer = new Observer(entries => {
            const width = entries[0]?.contentRect.width
            if (width !== observedWidth) {
                observedWidth = width
                if (width === 0) lastWidth = 0
                // The first notification describes the width already measured.
                // Height animations must not trigger another measurement cycle.
                if (!metrics || Math.abs(width + metrics.inlinePadding - lastWidth) > 0.5) schedule(true)
            }
        })
        observer.observe(input)
        scope.trackObserver(observer)
    } else scope.listen(document.defaultView, 'resize', () => schedule(true))
    if (document.fonts) {
        scope.listen(document.fonts, 'loadingdone', () => schedule(true))
    }
    return { schedule, measure, initialize }
}
