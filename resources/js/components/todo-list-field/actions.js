import { cloneTodoItems, toggleTodoTree } from './helpers.js'
import { animateCheckOn, animateCheckOff } from './motion.js'
import { uuid } from './identity.js'
import { planTodoOrder } from './reordering/order.js'
import { bindTodoReorder } from './reordering/pointer.js'

/** Operations preserve stable row identities; server failures never commit a rejected mutation. */
export function createTodoActions() {
    const pending = new Set()
    const policy = (runtime, item, operation, mode) =>
        !!item &&
        !runtime._disposed &&
        !runtime.disabled &&
        !item.disabled &&
        runtime[operation] &&
        (mode === 'all' || (mode === 'created' && item?.created))
    return {
        async callSchemaMethod(method, params = {}) {
            if (
                this._disposed ||
                !this.componentKey ||
                typeof this.$wire?.callSchemaComponentMethod !== 'function'
            )
                throw Error('Todo server operation unavailable.')
            const result = await this.$wire.callSchemaComponentMethod(
                this.componentKey,
                method,
                params,
            )
            if (this._disposed) throw Error('Todo operation owner disposed.')
            return result
        },
        collectItemsByIds(items, ids) {
            const keys = new Set(ids.map(String))
            return items
                .flatMap((item) => [item, ...(item.children ?? [])])
                .filter((item) => keys.has(String(item.id)))
        },
        notifyToggled(changedIds, items) {
            if (!this.afterToggledEnabled || !changedIds.length) return
            void this.callSchemaMethod('todoItemsToggled', {
                changed: this.collectItemsByIds(items, changedIds),
                items,
            }).catch(() => {
                if (!this._disposed)
                    this.$dispatch?.('fff-todo-list-error', {
                        operation: 'toggle',
                    })
            })
        },
        toggle(id, event) {
            event?.preventDefault?.()
            if (this._disposed || this.disabled) return
            const before = cloneTodoItems(this.items()),
                { items, changedIds, completedCount } = toggleTodoTree(
                    before,
                    id,
                )
            if (!changedIds.length) return
            this.prepareStrikeGeometry?.(changedIds)
            this.state = items
            this.rebuildDoneIds(items)
            for (const key of changedIds) {
                this.clearTimer(this.settleTimers[key])
                delete this.settleTimers[key]
                delete this.settledIds[key]
            }
            this.refresh()
            this.nextTick(() => {
                if (this._disposed) return
                for (const key of changedIds) {
                    const done = items
                        .flatMap((item) => [item, ...(item.children ?? [])])
                        .find((item) => String(item.id) === String(key))?.done
                    if (this.isTodoDone(key) !== Boolean(done)) continue
                    this.clearTimer(this.settleTimers[key])
                    delete this.settleTimers[key]
                    if (!done) {
                        animateCheckOff(this.rowEl(key), {
                            reducedMotion: this.reducedMotion,
                        })
                        continue
                    }
                    const settle = () => {
                        if (this.isTodoDone(key)) {
                            this.settledIds = {
                                ...this.settledIds,
                                [key]: true,
                            }
                            this.refresh()
                        }
                        delete this.settleTimers[key]
                    }
                    animateCheckOn(this.rowEl(key), {
                        reducedMotion: this.reducedMotion,
                    })
                    this.settleTimers[key] = this.timeout(
                        settle,
                        (this.reducedMotion ? 0 : 300) + this.doneSettleMs,
                    )
                }
                if (completedCount) {
                    this.play(this.findItemById(id)?.sound || this.checkSound)
                    this.notifyCompletion(completedCount, before, changedIds)
                    const item = this.findItemById(id)
                    if (item?.celebration && item.celebration !== false)
                        this.triggerCelebration(item.celebration, {
                            fullscreen: !!item.celebrationFullscreen,
                            anchorId: String(id),
                        })
                    else if (
                        item?.celebration !== false &&
                        this.celebration &&
                        this.allCompletableDone(items)
                    )
                        this.triggerCelebration(this.celebration)
                } else this.stopCelebration()
                this.notifyToggled(changedIds, items)
            })
        },

        canAddMore() {
            return (
                !this._disposed &&
                this.allowCreate &&
                !this.disabled &&
                (this.maxItems == null || this.items().length < this.maxItems)
            )
        },
        startCreate() {
            if (!this.canAddMore() || this.createBusy) return
            this.clearTimer(this._createBlurTimer)
            this.creating = true
            this.createDraft = ''
            this.createDescriptionDraft = ''
            this.nextTick(() => this.$refs.createInput?.focus())
        },
        cancelCreate() {
            this.clearTimer(this._createBlurTimer)
            this.creating = false
            this.createDraft = ''
            this.createDescriptionDraft = ''
        },
        onCreateDockFocusOut(event) {
            if (
                event.relatedTarget &&
                event.currentTarget.contains(event.relatedTarget)
            )
                return
            this.clearTimer(this._createBlurTimer)
            this._createBlurTimer = this.timeout(() => {
                if (
                    this.createBusy ||
                    !this.creating ||
                    this.$refs.createDock?.contains(
                        this.$el.ownerDocument.activeElement,
                    )
                )
                    return
                if (this.createDraft.trim()) void this.confirmCreate()
                else this.cancelCreate()
            }, 0)
        },
        onCreateLabelEnter() {
            if (this.createBusy) return
            if (this.createWithDescription)
                this.$refs.createDescriptionInput?.focus()
            else void this.confirmCreate()
        },
        onCreateRowClick(event) {
            if (!this.creating && !event?.target?.closest('input,button'))
                this.startCreate()
        },
        onCreateIconMouseDown(event) {
            if (this.creating) event.preventDefault()
        },
        onCreateIconClick(event) {
            event?.stopPropagation()
            if (this.createBusy) return
            if (this.creating) this.cancelCreate()
            else this.startCreate()
        },
        async confirmCreate() {
            if (this.createBusy || !this.canAddMore()) return
            const label = String(this.createDraft ?? '').trim(),
                description = this.createWithDescription
                    ? String(this.createDescriptionDraft ?? '').trim() || null
                    : null
            if (
                !label ||
                label.length > 500 ||
                (description?.length ?? 0) > 2000
            )
                return
            this.clearTimer(this._createBlurTimer)
            this.createBusy = true
            let item = {
                id: uuid(),
                label,
                description,
                done: false,
                disabled: false,
                created: true,
                children: [],
                date: null,
                sound: null,
                celebration: null,
            }
            try {
                if (this.createUsingEnabled) {
                    const result = await this.callSchemaMethod(
                        'createTodoItem',
                        { item },
                    )
                    if (
                        !result ||
                        !['string', 'number'].includes(typeof result.id) ||
                        typeof result.label !== 'string'
                    )
                        return
                    item = {
                        ...item,
                        ...result,
                        id: String(result.id),
                        created: true,
                    }
                }
                if (
                    this._disposed ||
                    !this.canAddMore() ||
                    this.findItemById(item.id)
                )
                    return
                this.includeCreatedItem?.(item.id)
                this.state = [...this.items(), item]
                this.enteringIds = { ...this.enteringIds, [item.id]: true }
                this.cancelCreate()
                this.refresh()
                this.scrollToTodo(item.id)
                this.play(this.createSound || this.accentSound)
                this._enterExitTimers[item.id] = this.timeout(
                    () => {
                        delete this.enteringIds[item.id]
                        delete this._enterExitTimers[item.id]
                        this.refresh()
                    },
                    this.reducedMotion ? 0 : 380,
                )
            } catch {
                if (!this._disposed)
                    this.$dispatch?.('fff-todo-list-error', {
                        operation: 'create',
                    })
            } finally {
                if (!this._disposed) this.createBusy = false
            }
        },
        canDeleteItem(item) {
            return policy(this, item, 'allowDelete', this.deletableMode)
        },
        canEditItem(item) {
            return policy(this, item, 'allowEdit', this.editableMode)
        },
        canReorderItem(item) {
            return (
                !!item &&
                !this._disposed &&
                this.reorderable &&
                !this.disabled &&
                !item?.disabled &&
                !String(this.search).trim()
            )
        },
        actionSlotCount() {
            return Number(!!this.allowEdit) + Number(!!this.allowDelete)
        },
        actionGutterStyle() {
            return null
        },
        async mountItemAction(id, operation, action) {
            const key = String(id)
            if (this.actionMountPending || this.actionBusy[key] || this.exitingIds[key] || !this.componentKey || this._disposed) return
            this.actionMountPending = true
            this.actionBusy = { ...this.actionBusy, [key]: operation }
            this.refresh()
            try {
                if (typeof this.$wire?.mountAction !== 'function')
                    throw Error('Todo action unavailable.')
                await this.$wire.mountAction(
                    action,
                    { id: key },
                    { schemaComponent: this.componentKey },
                )
            } catch {
                if (!this._disposed)
                    this.$dispatch?.('fff-todo-list-error', { operation })
            } finally {
                if (!this._disposed) {
                    delete this.actionBusy[key]
                    this.actionMountPending = false
                    this.refresh()
                }
            }
        },
        async editItem(id, event) {
            event?.stopPropagation()
            if (!this.canEditItem(this.findItemById(id))) return
            await this.mountItemAction(id, 'edit', 'editTodoItem')
        },
        applyServerDeletedItem(id) {
            if (this._disposed || typeof id !== 'string') return
            this.animateItemRemoval(id)
        },
        animateItemRemoval(key) {
            if (this._disposed || this.exitingIds[key] || !this.findItemById(key)) return
            this.exitingIds = { ...this.exitingIds, [key]: true }
            this.refresh()
            const finish = () => {
                if (this._disposed) return
                this.state = this.items()
                    .filter((row) => String(row.id) !== key)
                    .map((row) => ({
                        ...row,
                        children: (row.children ?? []).filter(
                            (child) => String(child.id) !== key,
                        ),
                    }))
                delete this.exitingIds[key]
                pending.delete(key)
                this.refresh()
                this.play(this.accentSound)
            }
            if (this.reducedMotion) finish()
            else this._enterExitTimers[key] = this.timeout(() => {
                delete this._enterExitTimers[key]
                finish()
            }, 320)
        },
        async removeItem(id, event) {
            event?.stopPropagation()
            const key = String(id), item = this.findItemById(key)
            if (!this.canDeleteItem(item) || pending.has(key) || this.actionBusy[key] || this.exitingIds[key]) return
            if (this.deleteConfirmation) {
                await this.mountItemAction(key, 'delete', 'confirmDeleteTodoItem')
                return
            }
            pending.add(key)
            try {
                if (this.deleteUsingEnabled && (await this.callSchemaMethod('deleteTodoItem', { id: key })) === false) return
                if (this._disposed) return
                this.animateItemRemoval(key)
            } catch {
                if (!this._disposed) this.$dispatch?.('fff-todo-list-error', { operation: 'delete' })
            } finally {
                if (!this.exitingIds[key]) pending.delete(key)
            }
        },
        bindDeleteSyncedListener(scope) {
            if (!this.deleteSyncedEvent) return
            const handler = (payload) => {
                const data = payload?.id !== undefined ? payload : (payload?.[0] ?? payload)
                this.applyServerDeletedItem(data?.id)
            }
            if (typeof window.Livewire?.on === 'function') {
                const stop = window.Livewire.on(this.deleteSyncedEvent, handler)
                if (typeof stop === 'function') scope.defer(stop)
            } else {
                scope.listen(window, this.deleteSyncedEvent, (event) => handler(event.detail))
            }
        },
        async reorderItems(event) {
            const previous = this.items()
            const from = event?.id != null
                ? previous.findIndex(item => String(item.id) === String(event.id))
                : event?.oldDraggableIndex ?? event?.oldIndex
            const to = event?.targetId != null
                ? previous.findIndex(item => String(item.id) === String(event.targetId))
                : event?.newDraggableIndex ?? event?.newIndex
            if (this._disposed || this.reorderBusy || !this.reorderable || this.disabled || this.search.trim()) return
            const next = planTodoOrder(previous, from, to)
            if (!next) return
            this.reorderBusy = true
            try {
                if (
                    this.reorderUsingEnabled &&
                    (await this.callSchemaMethod('reorderTodoItems', {
                        items: next,
                    })) === false
                )
                    return
                if (this._disposed) return
                const latest = new Map(
                    this.items().map((item) => [String(item.id), item]),
                )
                if (
                    latest.size !== next.length ||
                    next.some((item) => !latest.has(String(item.id)))
                )
                    return
                const latestOrder = this.items()
                if (latestOrder.some((item, index) =>
                    String(item.id) !== String(previous[index].id) ||
                    (item.disabled && String(next[index].id) !== String(item.id)))) return
                this.state = next.map((item) => latest.get(String(item.id)))
                this.refresh()
            } catch {
                if (!this._disposed)
                    this.$dispatch?.('fff-todo-list-error', {
                        operation: 'reorder',
                    })
            } finally {
                if (!this._disposed) this.reorderBusy = false
            }
        },
        bindReorder(scope, root) {
            if (this.reorderable) bindTodoReorder(this, scope, root)
        },
    }
}
