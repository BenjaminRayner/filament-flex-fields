import { layoutTodoStrikes } from './rendering/strike-layout.js'
import {
    childProgressLabel,
    countDoneChildren,
    indexTodoDoneById,
} from './helpers.js'

export function createTodoPresentation() {
    return {
        rebuildDoneIds(list = this.items()) {
            const next = Object.create(null)

            for (const [id, done] of indexTodoDoneById(list)) {
                if (done) {
                    next[id] = true
                }
            }

            this.doneIds = next
        },

        isTodoDone(id) {
            return Boolean(this.doneIds[String(id)])
        },

        isSettledId(id) {
            return Boolean(this.settledIds[String(id)])
        },

        hasChildren(item) {
            return Array.isArray(item?.children) && item.children.length > 0
        },

        hasMeta(item) {
            return this.hasChildren(item) || Boolean(item?.date)
        },

        childProgressLabel(item) {
            return childProgressLabel(item)
        },

        countDoneChildren(item) {
            return countDoneChildren(item)
        },

        findItemById(id) {
            const needle = String(id)

            for (const item of this.items()) {
                if (String(item.id) === needle) {
                    return item
                }

                for (const child of item.children ?? []) {
                    if (String(child.id) === needle) {
                        return child
                    }
                }
            }

            return null
        },

        syncStrikeWidths() {
            layoutTodoStrikes(this.$refs?.viewport || this.$el)
        },

        syncDoneStyles({ snapStrike = true } = {}) {
            for (const item of this.items()) {
                const syncRow = (id, done) => {
                    const row = this.rowEl(id)

                    if (!row) {
                        return
                    }

                    const input = row.querySelector(
                        'input.fff-todo-list-field__checkbox-input',
                    )

                    if (input) {
                        input.checked = Boolean(done)
                    }

                    // Toggle path only syncs the native input — strike/tick motion owns the CSS vars.
                    if (!snapStrike) {
                        return
                    }

                    if (done) {
                        row.style.setProperty('--text-line-scale', '1')
                        row.style.setProperty('--text-x', '0px')

                        return
                    }

                    row.style.setProperty('--text-line-scale', '0')
                    row.style.setProperty('--text-x', '0px')
                    row.style.removeProperty('--checkbox-lines-offset')
                }

                syncRow(item.id, item.done)

                for (const child of item.children ?? []) {
                    syncRow(child.id, child.done)
                }
            }

            this.syncStrikeWidths()
        },

        items() {
            return Array.isArray(this.state) ? this.state : []
        },

        isDone(item) {
            return Boolean(item?.done)
        },

        isSettled(item) {
            return Boolean(item?.done) && Boolean(this.settledIds[item.id])
        },
    }
}
