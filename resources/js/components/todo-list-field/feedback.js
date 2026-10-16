import { allTodosComplete, applyTodoUndoPatch, cloneTodoItems, diffTodoDoneIds } from './helpers.js'
import { animateCheckOff } from './motion.js'
import { runTodoListCelebration } from './celebrations.js'

export function createTodoFeedback(audio) {
    return {
        bindUndoListener() {
            this._undoCleanup?.()

            if (! this.undoCompletionNotifications || ! this.undoEvent) {
                return
            }

            const handler = (...args) => {
                if (this._disposed) return
                const payload = args[0]
                const data = payload?.snapshot !== undefined || payload?.changedIds !== undefined
                    ? payload
                    : (payload?.[0] ?? payload)

                const snapshot = data?.snapshot ?? data
                const changedIds = data?.changedIds ?? null

                if (Array.isArray(snapshot)) {
                    this.restoreUndoSnapshot(snapshot, changedIds)
                }
            }

            if (typeof window.Livewire?.on === 'function') {
                const stop = window.Livewire.on(this.undoEvent, handler)
                this._undoCleanup = typeof stop === 'function' ? stop : () => {}

                return
            }

            const domHandler = (event) => handler(event.detail)
            window.addEventListener(this.undoEvent, domHandler)
            this._undoCleanup = () => window.removeEventListener(this.undoEvent, domHandler)
        },

        bindEditSyncedListener() {
            this._editSyncedCleanup?.()

            if (! this.editSyncedEvent) {
                return
            }

            const handler = (...args) => {
                if (this._disposed) return
                const payload = args[0]
                const data = payload?.item !== undefined
                    ? payload
                    : (payload?.[0] ?? payload)
                const item = data?.item ?? data

                if (item && typeof item === 'object' && item.id != null) {
                    this.applyServerEditedItem(item)
                }
            }

            if (typeof window.Livewire?.on === 'function') {
                const stop = window.Livewire.on(this.editSyncedEvent, handler)
                this._editSyncedCleanup = typeof stop === 'function' ? stop : () => {}

                return
            }

            const domHandler = (event) => handler(event.detail)
            window.addEventListener(this.editSyncedEvent, domHandler)
            this._editSyncedCleanup = () => window.removeEventListener(this.editSyncedEvent, domHandler)
        },

        applyServerEditedItem(item) {
            const needle = String(item.id)

            this.state = this.items().map((row) => {
                if (String(row.id) === needle) {
                    return {
                        ...row,
                        ...item,
                        id: row.id,
                        children: Array.isArray(item.children) ? item.children : (row.children ?? []),
                    }
                }

                return {
                    ...row,
                    children: (row.children ?? []).map((child) => {
                        if (String(child.id) !== needle) {
                            return child
                        }

                        return {
                            ...child,
                            ...item,
                            id: child.id,
                        }
                    }),
                }
            })

            this.nextTick(() => {
                this.scheduleSyncStrikeWidths()
                this.syncOverlayScrollbar()
            })
        },

        restoreUndoSnapshot(snapshot, changedIds = null) {
            const before = cloneTodoItems(this.items())
            const next = applyTodoUndoPatch(before, snapshot, changedIds)
            const idSet = Array.isArray(changedIds) && changedIds.length > 0
                ? new Set(changedIds.map((id) => String(id)))
                : null

            this.state = next
            this.lastUndoSnapshot = null
            this.rebuildDoneIds(next)
            this.settledIds = Object.create(null)

            for (const item of this.items()) {
                if (item.done) {
                    this.settledIds[String(item.id)] = true
                }

                for (const child of item.children ?? []) {
                    if (child.done) {
                        this.settledIds[String(child.id)] = true
                    }
                }
            }

            // Same afterToggled / DB hook as checkbox toggle.
            const toggledIds = idSet
                ? [...idSet]
                : diffTodoDoneIds(before, next)

            if (toggledIds.length > 0) {
                this.notifyToggled(toggledIds, next)
            }

            this.nextTick(() => {
                this.syncDoneStyles()
                this.syncStrikeWidths()

                if (! idSet) {
                    return
                }

                for (const id of idSet) {
                    const row = this.rowEl(id)

                    if (! row) {
                        continue
                    }

                    const wasDone = (() => {
                        for (const item of before) {
                            if (String(item.id) === id) {
                                return Boolean(item.done)
                            }

                            for (const child of item.children ?? []) {
                                if (String(child.id) === id) {
                                    return Boolean(child.done)
                                }
                            }
                        }

                        return false
                    })()

                    const isDoneNow = (() => {
                        for (const item of next) {
                            if (String(item.id) === id) {
                                return Boolean(item.done)
                            }

                            for (const child of item.children ?? []) {
                                if (String(child.id) === id) {
                                    return Boolean(child.done)
                                }
                            }
                        }

                        return false
                    })()

                    if (wasDone && ! isDoneNow) {
                        animateCheckOff(row, { reducedMotion: this.reducedMotion })
                    }
                }
            })
        },

        notifyCompletion(count, snapshot, changedIds = []) {
            if (! this.undoCompletionNotifications || count < 1) {
                return
            }

            if (typeof window.FilamentNotification !== 'function' || typeof window.FilamentNotificationAction !== 'function') {
                return
            }

            const frozenSnapshot = cloneTodoItems(snapshot)
            const frozenChangedIds = [...changedIds]

            this.lastUndoSnapshot = frozenSnapshot

            const title = count === 1
                ? (this.labels.taskCompleted || '1 task completed')
                : String(this.labels.tasksCompleted || ':count tasks completed').replace(':count', String(count))

            new window.FilamentNotification()
                .title(title)
                .duration(8000)
                .actions([
                    new window.FilamentNotificationAction('undo')
                        .label(this.labels.undo || 'Undo')
                        .color('danger')
                        .dispatch(this.undoEvent, {
                            snapshot: frozenSnapshot,
                            changedIds: frozenChangedIds,
                        })
                        .close(),
                ])
                .send()
        },

        play(url, options = {}) {
            if (this._disposed || ! this.sounds || ! url) {
                return null
            }

            return audio.play(url, options)
        },

        allCompletableDone(list = this.items()) {
            return allTodosComplete(list)
        },

        stopCelebration() {
            this.celebrationHandle?.stop?.()
            this.celebrationHandle = null
            this.resetCelebrationCanvas()
        },

        resetCelebrationCanvas() {
            const canvas = this.$refs?.celebration

            if (! canvas) {
                return
            }

            canvas.classList.remove(
                'fff-todo-list-field__celebration--fullscreen',
                'is-anchored',
            )
            canvas.style.inset = ''
            canvas.style.top = ''
            canvas.style.left = ''
            canvas.style.right = ''
            canvas.style.bottom = ''
            canvas.style.width = ''
            canvas.style.height = ''
            canvas.style.borderRadius = ''
        },

        layoutCelebrationCanvas({ fullscreen = false, anchorId = null } = {}) {
            const canvas = this.$refs?.celebration

            if (! canvas) {
                return
            }

            this.resetCelebrationCanvas()

            if (fullscreen) {
                return
            }

            if (! anchorId) {
                return
            }

            const row = this.rowEl(anchorId)
            const shell = canvas.parentElement

            if (! row || ! shell) {
                return
            }

            const rowRect = row.getBoundingClientRect()
            const shellRect = shell.getBoundingClientRect()

            // Strict row bounds — never spill outside the option chrome.
            canvas.classList.add('is-anchored')
            canvas.style.inset = 'auto'
            canvas.style.top = `${Math.max(0, rowRect.top - shellRect.top)}px`
            canvas.style.left = `${Math.max(0, rowRect.left - shellRect.left)}px`
            canvas.style.width = `${Math.max(1, rowRect.width)}px`
            canvas.style.height = `${Math.max(1, rowRect.height)}px`
        },

        triggerCelebration(key, options = {}) {
            if (this._disposed || ! key || this.reducedMotion) {
                return
            }

            const canvas = this.$refs?.celebration

            if (! canvas) {
                return
            }

            this.stopCelebration()

            const fullscreen = Boolean(options.fullscreen ?? this.celebrationFullscreen)
            const anchorId = fullscreen ? null : (options.anchorId ?? null)

            this.layoutCelebrationCanvas({ fullscreen, anchorId })

            const pack = this.celebrationAudio?.[key] && typeof this.celebrationAudio[key] === 'object'
                ? this.celebrationAudio[key]
                : null

            this.celebrationHandle = runTodoListCelebration(key, {
                canvas,
                durationMs: this.celebrationDurationMs,
                playSound: (url, playOptions) => this.play(url, playOptions),
                startSound: pack
                    ? (pack.start ?? null)
                    : (key === 'fireworks' ? this.celebrationStartSound : null),
                burstSound: pack
                    ? (pack.burst ?? null)
                    : (key === 'fireworks' ? this.celebrationSound : null),
                reducedMotion: this.reducedMotion,
                fullscreen,
                onStop: () => this.resetCelebrationCanvas(),
            })
        },
    }
}
