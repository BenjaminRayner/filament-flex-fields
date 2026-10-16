import { createTodoState } from './state.js'
import { watchOwnedAlpineValue } from '../../../runtime/alpine/owned-watch.js'
import { createResourceScope } from '../../../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../../../runtime/lifecycle/asset-activation.js'
import {
    bindOverlayScrollbar,
    syncOverlayScrollbar,
    revealOverlayScrollbar,
} from '../../../core/overlay-scrollbar.js'
import { createTodoWindow } from '../collection/window.js'
import { projectTodoItems } from '../collection/projection.js'
import { createTodoPager } from '../collection/remote.js'
import { createTodoRows } from '../rendering/rows.js'
import { createTodoPresentation } from '../presentation.js'
import { createTodoActions } from '../actions.js'
import { createTodoFeedback } from '../feedback.js'
import { createAudioPool } from '../audio-pool.js'
import { cancelRowMotion } from '../motion.js'
import { queueTodoLayout, todoFontsReady } from '../rendering/layout-queue.js'
import {
    measureTodoStrikes,
    measureCompletedTodoStrikes,
} from '../rendering/strike-layout.js'
import { createTodoChrome } from '../rendering/chrome.js'
import { createTodoScrollbarPosition } from '../rendering/scrollbar-position.js'

export function createTodoRuntime(input = {}) {
    let host,
        scope,
        ready,
        view,
        chrome,
        windowSession,
        pager,
        projection,
        cancelPaint,
        remoteHasMore = false,
        sourceDirty = true,
        cancelLayout,
        windowMounted = false,
        virtualListInset = 0,
        measuredTrackHeight,
        scrollbarScrolled = false
    let scrollbarPosition
    let lastSearch,
        queryGeneration = 0,
        requestPage = 1,
        remoteIds = null,
        remoteTotal = null,
        layoutDirty = true,
        lastWindow = ''
    const audio = createAudioPool(),
        frames = new Map(),
        timers = new Map()
    const runtime = {
        ...createTodoState(input.state),
        frame(callback) {
            if (!scope || scope.disposed) return 0
            const key = Symbol()
            frames.set(
                key,
                scope.frame((now) => {
                    frames.delete(key)
                    callback(now)
                }),
            )
            return key
        },
        cancelFrame(key) {
            frames.get(key)?.()
            frames.delete(key)
        },
        timeout(callback, delay) {
            if (!scope || scope.disposed) return 0
            const key = Symbol()
            timers.set(
                key,
                scope.timeout(
                    () => {
                        timers.delete(key)
                        callback()
                    },
                    Math.max(0, Math.round(delay)),
                ),
            )
            return key
        },
        clearTimer(key) {
            timers.get(key)?.()
            timers.delete(key)
        },
        nextTick(callback) {
            this.$nextTick(() => {
                if (!this._disposed) callback()
            })
        },
        rowEl(id) {
            return view?.row(id) ?? null
        },
        prepareStrikeGeometry(ids) {
            // Read the existing row before completion changes its DOM/classes.
            // Unchecked virtual rows otherwise require no text measurement.
            const plans = ids.map((id) => {
                const row = view?.row(id)
                row?.classList.remove('is-window-presented')
                return measureTodoStrikes(row)
            })
            for (const apply of plans) apply()
        },
        refresh({ reset = false } = {}) {
            if (this._disposed || !view) return
            sourceDirty = true
            if (reset) {
                this.page = 1
                this.visibleCount = this.pageSize ?? Infinity
                this.$refs.viewport.scrollTop = 0
            }
            this.paint()
        },
        schedulePaint() {
            if (cancelPaint || this._disposed) return
            cancelPaint = scope.frame(() => {
                cancelPaint = null
                this.paint()
            })
        },
        paint() {
            if (this._disposed || !view) return
            const contentChanged = sourceDirty
            if (sourceDirty) {
                projection = projectTodoItems(
                    remoteIds
                        ? this.items().filter((item) =>
                              remoteIds.has(String(item.id)),
                          )
                        : this.items(),
                    {
                        query: this.remoteLoader ? '' : this.search,
                        completedLast:
                            this.persistCompletedOrder && !this.reorderable,
                        pageSize: this.remoteLoader
                            ? null
                            : this.paginated
                              ? this.pageSize
                              : null,
                        page: this.page,
                        infinite: this.infiniteScroll && !this.remoteLoader,
                        visibleCount: this.visibleCount,
                    },
                )
                this.page = this.remoteLoader ? this.page : projection.page
                this.pages =
                    this.remoteLoader && remoteTotal != null
                        ? Math.max(
                              1,
                              Math.ceil(remoteTotal / (this.pageSize ?? 20)),
                          )
                        : projection.pages
                this.total = remoteTotal ?? projection.total
                this.hasMore = this.remoteLoader
                    ? remoteHasMore
                    : projection.hasMore
                windowSession?.setItems(projection.items)
                sourceDirty = false
            }
            const result = windowSession
                ? windowSession.read()
                : {
                      rows: projection.items.map((item, index) => ({
                          item,
                          index,
                      })),
                      before: 0,
                      after: 0,
                  }
            const stamp = JSON.stringify([
                result.before,
                result.after,
                result.rows.map((row) => [String(row.item.id), row.size]),
            ])
            const windowChanged = stamp !== lastWindow
            if (windowChanged) {
                layoutDirty = true
                lastWindow = stamp
            }
            if (contentChanged || windowChanged || layoutDirty) {
                const rendered = view.render(result, this)
                layoutDirty ||= rendered.geometryChanged
            }
            windowSession?.commit()
            chrome.update(this)
            this.scheduleLayout()
        },
        scheduleLayout() {
            cancelLayout?.()
            cancelLayout = queueTodoLayout(
                host,
                () => {
                    cancelLayout = null
                    if (this._disposed) return
                    if (windowSession && !windowMounted) {
                        windowMounted = true
                        windowSession.mount()
                    }
                    if (windowSession && layoutDirty)
                        for (const group of view.list.querySelectorAll(
                            '[data-todo-group-id]',
                        ))
                            windowSession.measure(group)
                    const strikes = layoutDirty
                        ? measureCompletedTodoStrikes(this.$refs.viewport)
                        : null
                    layoutDirty = false
                    const viewport = this.$refs.viewport,
                        thumb = this.$refs.scrollbarThumb
                    const virtualMetrics = windowSession?.metrics()
                    const metrics = virtualMetrics
                        ? {
                              scrollTop: virtualMetrics.scrollTop,
                              scrollHeight: Math.max(
                                  virtualMetrics.clientHeight,
                                  virtualMetrics.totalSize + virtualListInset,
                              ),
                              clientHeight: virtualMetrics.clientHeight,
                          }
                        : {
                              scrollTop: viewport.scrollTop,
                              scrollHeight: viewport.scrollHeight,
                              clientHeight: viewport.clientHeight,
                          }
                    const trackHeight = measuredTrackHeight ?? metrics.clientHeight - 8
                    const geometry = [
                        metrics.scrollTop,
                        metrics.scrollHeight,
                        metrics.clientHeight,
                        trackHeight,
                    ].join(':')
                    return () => {
                        if (this._disposed) return
                        strikes?.()
                        if (geometry !== this._scrollbarGeometry) {
                            this._scrollbarGeometry = geometry
                            // The shared scrollbar receives a measured snapshot, so its write phase never reads layout.
                            syncOverlayScrollbar(viewport, thumb, 24, {
                                ...metrics,
                                trackHeight,
                            }, { setPosition: (top) => scrollbarPosition.set(top) })
                        }
                        if (scrollbarScrolled) {
                            scrollbarScrolled = false
                            revealOverlayScrollbar(thumb?.parentElement)
                        }
                    }
                },
                () =>
                    this.$dispatch('fff-todo-list-error', {
                        reason: 'layout_failed',
                    }),
            )
        },
        syncOverlayScrollbar() {
            if (view && !this._disposed) this.scheduleLayout()
        },
        includeCreatedItem(id) {
            remoteIds?.add(String(id))
        },
        scheduleSyncStrikeWidths() {
            this.schedulePaint()
        },
        scheduleMeasureRowHeights() {
            this.schedulePaint()
        },
        scrollToTodo(id) {
            const filtered = projectTodoItems(this.items(), {
                query: this.search,
                completedLast: this.persistCompletedOrder && !this.reorderable,
            }).items
            const index = filtered.findIndex(
                (item) => String(item.id) === String(id),
            )
            if (index < 0) return
            if (this.paginated && !this.remoteLoader)
                this.page = Math.floor(index / this.pageSize) + 1
            if (this.infiniteScroll && !this.remoteLoader)
                this.visibleCount = Math.max(this.visibleCount, index + 1)
            this.refresh()
            this.nextTick(() => {
                if (windowSession)
                    windowSession.reveal(
                        projection.items.findIndex(
                            (item) => String(item.id) === String(id),
                        ),
                    )
                else view.row(id)?.scrollIntoView({ block: 'nearest' })
                this.schedulePaint()
            })
        },
        async goToPage(page) {
            if (
                this._disposed ||
                this.loadingMore ||
                !Number.isInteger(page) ||
                page < 1 ||
                (!this.remoteLoader && page > this.pages)
            )
                return
            if (this.remoteLoader) {
                requestPage = page
                await this.requestRemote(page, false)
            } else {
                this.page = page
                this.$refs.viewport.scrollTop = 0
                this.refresh()
            }
        },
        async requestRemote(page, append) {
            const generation = queryGeneration
            await pager.request(page, this.search, () => this.items(), append)
            if (!this._disposed && generation === queryGeneration) this.paint()
        },
        async loadMore() {
            if (
                this._disposed ||
                !this.infiniteScroll ||
                this.loadingMore ||
                !this.hasMore ||
                this.loadError
            )
                return
            if (this.remoteLoader) {
                requestPage = this.remotePage + 1
                await this.requestRemote(requestPage, true)
            } else {
                this.visibleCount += this.pageSize ?? 20
                this.refresh()
            }
        },
        async retryPage() {
            if (this._disposed || this.loadingMore) return
            this.loadError = null
            await this.requestRemote(
                requestPage,
                this.infiniteScroll && requestPage > 1,
            )
        },
        init() {
            if (ready || this._disposed) return ready
            host = this.$el
            const fonts = todoFontsReady(host)
            scope = createResourceScope({
                onEffectError: (error) =>
                    this.$dispatch('fff-todo-list-error', {
                        reason: 'effect_failed',
                    }),
            })
            ready = (async () => {
                if (
                    !(await waitForAssetActivation(host, {
                        signal: scope.signal,
                    })) ||
                    this._disposed
                )
                    return
                const config = {
                    ...JSON.parse(host.dataset.todoConfig || '{}'),
                    ...input,
                }
                const { items: initialItems, ...settings } = config
                Object.assign(this, settings, {
                    state: this.state ?? initialItems ?? [],
                    visibleCount: config.pageSize ?? Infinity,
                })
                const media = host.ownerDocument.defaultView.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                )
                this.reducedMotion = media.matches
                scope.listen(media, 'change', () => {
                    this.reducedMotion = media.matches
                    if (media.matches) {
                        this.stopCelebration()
                        for (const row of host.querySelectorAll(
                            '[data-todo-id]',
                        ))
                            cancelRowMotion(row)
                    }
                })
                view = createTodoRows(host, {
                    ...config,
                    releaseMeasurement: (group) => windowSession?.unmeasure(group),
                })
                chrome = createTodoChrome(host)
                scrollbarPosition = createTodoScrollbarPosition(this.$refs.scrollbarThumb)
                scope.defer(() => scrollbarPosition.dispose())
                scope.defer(() => view.dispose())
                scope.defer(() => cancelLayout?.())
                scope.defer(() => audio.dispose())
                scope.defer(() => this.stopCelebration())
                this.rebuildDoneIds()
                this.settledIds = { ...this.doneIds }
                if (this.virtualizing) {
                    const listStyle = host.ownerDocument.defaultView.getComputedStyle(view.list)
                    virtualListInset =
                        (parseFloat(listStyle.paddingTop) || 0) +
                        (parseFloat(listStyle.paddingBottom) || 0)
                    windowSession = createTodoWindow({
                        viewport: this.$refs.viewport,
                        // Many native scroll events can arrive within one frame. Commit the
                        // latest window once, before paint, rather than invalidating layout
                        // between successive scroll-offset reads.
                        onChange: (_synchronous, measured) => {
                            if (measured && !sourceDirty) this.paint()
                            else this.schedulePaint()
                        },
                        estimateSize: config.virtualItemHeight,
                    })
                    scope.defer(() => windowSession.dispose())
                }
                if (this.remoteLoader) {
                    remoteIds = new Set(
                        this.items().map((item) => String(item.id)),
                    )
                    remoteHasMore = config.hasMore ?? true
                    pager = createTodoPager({
                        maxItems: this.maxItems,
                        pageSize: this.pageSize ?? 20,
                        load: async (page, query, signal) => {
                            const method =
                                this.$wire?.[this.remoteLoader] ??
                                window[this.remoteLoader]
                            if (typeof method !== 'function')
                                throw Error('Todo loader unavailable.')
                            return this.$wire?.[this.remoteLoader]
                                ? method.call(this.$wire, page, query)
                                : method.call(window, page, query, { signal })
                        },
                        commit: (result) => {
                            this.state = result.items
                            remoteIds = new Set(
                                this.infiniteScroll && result.append
                                    ? [...remoteIds, ...result.visibleIds]
                                    : result.visibleIds,
                            )
                            remoteTotal = result.total
                            this.remotePage = result.page
                            this.page = result.page
                            remoteHasMore = result.hasMore
                            sourceDirty = true
                            layoutDirty = true
                            this.$refs.viewport.scrollTop = this.infiniteScroll
                                ? this.$refs.viewport.scrollTop
                                : 0
                        },
                        onStatus: (status) => {
                            if (this._disposed) return
                            if ('loading' in status)
                                this.loadingMore = status.loading
                            if ('error' in status) this.loadError = status.error
                            this.schedulePaint()
                        },
                    })
                    scope.defer(() => pager.dispose())
                }
                this.bindUndoListener()
                this.bindEditSyncedListener()
                this.bindDeleteSyncedListener(scope)
                scope.defer(() => this._undoCleanup?.())
                scope.defer(() => this._editSyncedCleanup?.())
                watchOwnedAlpineValue(
                    scope,
                    () => this.state,
                    () => {
                        if (this._disposed) return
                        this.rebuildDoneIds()
                        this.refresh()
                    },
                )
                watchOwnedAlpineValue(
                    scope,
                    () => this.search,
                    () => {
                        if (this._disposed || lastSearch === this.search) return
                        lastSearch = this.search
                        queryGeneration++
                        if (pager) {
                            pager.cancel()
                            remoteHasMore = true
                            requestPage = 1
                            void this.requestRemote(1, false)
                        }
                        this.refresh({ reset: true })
                    },
                )
                watchOwnedAlpineValue(
                    scope,
                    () => this.disabled,
                    () => this.refresh(),
                )
                scope.listen(
                    this.$refs.viewport,
                    'scroll',
                    () => {
                        scrollbarScrolled = true
                        this.syncOverlayScrollbar()
                        const el = this.$refs.viewport
                        if (
                            this.infiniteScroll &&
                            this.hasMore &&
                            !this.loadingMore &&
                            el.scrollTop + el.clientHeight >=
                            el.scrollHeight - 120
                        )
                            void this.loadMore()
                    },
                    { passive: true },
                )
                scope.defer(
                    bindOverlayScrollbar(
                        this.$refs.viewport,
                        this.$refs.scrollbar,
                        this.$refs.scrollbarThumb,
                        { manageScroll: false },
                    ),
                )
                let measuredWidth = null
                const resize = new ResizeObserver((entries) => {
                    for (const entry of entries) {
                        if (
                            entry.target === this.$refs.scrollbarThumb.parentElement
                        ) {
                            measuredTrackHeight = entry.contentRect.height
                            continue
                        }
                        const width = entry.contentRect.width
                        if (width !== measuredWidth) {
                            measuredWidth = width
                            windowSession?.invalidateWidth()
                            layoutDirty = true
                        }
                    }
                    this.schedulePaint()
                })
                resize.observe(this.$refs.viewport)
                resize.observe(this.$refs.scrollbarThumb.parentElement)
                scope.trackObserver(resize)
                scope.listen(host, 'click', (event) => {
                    const row = event.target.closest('[data-todo-id]')
                    if (!row || !host.contains(row)) return
                    const action =
                        event.target.closest('[data-todo-action]')?.dataset
                            .todoAction
                    if (action === 'edit')
                        this.editItem(row.dataset.todoId, event)
                    else if (action === 'delete')
                        void this.removeItem(row.dataset.todoId, event)
                    else if (!event.target.closest('[data-todo-drag]'))
                        this.toggle(row.dataset.todoId, event)
                })
                scope.listen(host, 'keydown', (event) => {
                    const row = event.target.closest('[data-todo-id]')
                    if (!row || event.target.closest('button')) return
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        this.toggle(row.dataset.todoId, event)
                    }
                    if (
                        ['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(
                            event.key,
                        )
                    ) {
                        event.preventDefault()
                        const list = projection.items.filter(
                                (item) => !item.disabled,
                            ),
                            current = list.findIndex(
                                (item) =>
                                    String(item.id) === row.dataset.todoId,
                            )
                        const index =
                            event.key === 'Home'
                                ? 0
                                : event.key === 'End'
                                  ? list.length - 1
                                  : Math.max(
                                        0,
                                        Math.min(
                                            list.length - 1,
                                            current +
                                                (event.key === 'ArrowDown'
                                                    ? 1
                                                    : -1),
                                        ),
                                    )
                        const target = list[index]
                        if (target) {
                            this.scrollToTodo(target.id)
                            this.nextTick(() =>
                                view
                                    .row(target.id)
                                    ?.focus({ preventScroll: true }),
                            )
                        }
                    }
                })
                this.bindReorder?.(scope, host)
                this.refresh()
                host.classList.add('is-hydrated', 'is-motion-ready')
                host.setAttribute('data-todo-ready', '')
                lastSearch = this.search
                fonts?.then(() => {
                    if (!this._disposed) {
                        windowSession?.invalidateWidth()
                        layoutDirty = true
                        this.schedulePaint()
                    }
                })
            })().catch((error) => {
                scope.dispose()
                throw error
            })
            return ready
        },
        destroy() {
            if (this._disposed) return
            this._disposed = true
            scope?.dispose()
            for (const cancel of [...frames.values(), ...timers.values()])
                cancel()
            frames.clear()
            timers.clear()
        },
        ...createTodoPresentation(),
        ...createTodoFeedback(audio),
        ...createTodoActions(),
    }
    // Row lookup and batching are renderer-owned, not repeated full-tree selectors.
    runtime.rowEl = (id) => view?.row(id) ?? null
    runtime.scheduleSyncStrikeWidths = function () {
        this.schedulePaint()
    }
    runtime.syncStrikeWidths = function () {
        layoutDirty = true
        this.scheduleLayout()
    }
    runtime.scheduleMeasureRowHeights = function () {
        this.schedulePaint()
    }
    return runtime
}
