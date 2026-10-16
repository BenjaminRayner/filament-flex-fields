import { cancelAllOptionCheckAnimations, scheduleCheckEnter, setCheckVisibleInstant, runAfterSelectedCheckExit } from '../../../components/select-field/headless-select-selection-ux.js'
import { createResourceScope } from '../../../runtime/lifecycle/resource-scope.js'

export function createSelectionChecksMixin() {
    const effects = new Map()
    let lifetimeBound = false
    const cancel = id => effects.get(id)?.()
    const cancelAll = () => { for (const release of [...effects.values()]) release() }
    return {
        bindSelectionCheckLifetime() {
            this.checkExitCancel = cancelAll
            if (lifetimeBound) return
            lifetimeBound = true
            this.ensurePickerResourceScope().defer(() => {
                cancelAll()
                cancelAllOptionCheckAnimations(this.$refs.headlessOptionsList)
            })
            // Compatibility cancellation remains an aggregate, never one flight.
        },
        queueOptionCheckExit(key, check) {
            this.bindSelectionCheckLifetime()
            cancel('enter:' + key)
            const id = 'exit:' + key
            cancel(id)
            const scope = createResourceScope()
            let releaseOwner = () => {}
            const release = () => {
                scope.dispose()
                effects.delete(id)
                this.clearOptionCheckExiting(key)
                releaseOwner()
            }
            effects.set(id, release)
            releaseOwner = this.ensurePickerResourceScope().defer(release)
            scope.defer(runAfterSelectedCheckExit(check, release))
        },
        isOptionCheckExiting(value) {
            void this.checkExitTick

            return Object.hasOwn(this.checkExitKeys, String(value))
        },

        /**
         * Dropdown may keep the selected-row chrome while the check strokes out.
         * Trigger chips always follow isOptionSelected() only.
         */
        isOptionSelectedInDropdown(value) {
            return this.isOptionSelected(value) || this.isOptionCheckExiting(value)
        },

        markOptionCheckExiting(value) {
            const key = String(value)

            if (Object.hasOwn(this.checkExitKeys, key)) {
                return
            }

            this.checkExitKeys = { ...this.checkExitKeys, [key]: true }
            this.checkExitTick = (this.checkExitTick ?? 0) + 1
        },

        clearOptionCheckExiting(value) {
            const key = String(value)

            if (! Object.hasOwn(this.checkExitKeys, key)) {
                return
            }

            const next = { ...this.checkExitKeys }
            delete next[key]
            this.checkExitKeys = next
            this.checkExitTick = (this.checkExitTick ?? 0) + 1
        },

        clearAllOptionCheckExiting() {
            if (Object.keys(this.checkExitKeys).length === 0) {
                return
            }

            this.checkExitKeys = {}
            this.checkExitTick = (this.checkExitTick ?? 0) + 1
        },

        /**
         * Teleported menus live outside the Alpine root; click.outside must not
         * treat option/search clicks as dismiss.
         */
        markKnownOptionChecksVisible() {
            if (this.isGridLayout) {
                return
            }

            this.$refs.headlessOptionsList?.querySelectorAll('.fi-select-input-option.fi-selected').forEach((option) => {
                const key = String(option.getAttribute('data-value') ?? '')
                const check = option.querySelector('.fff-select-option-selected-check')
                if (!check || effects.has('enter:' + key) || check.hasAttribute('data-check-animate')) return
                if (this.knownSelectedChecks.has(key)) setCheckVisibleInstant(check, true)
            })
        },

        freezeOptionChecksForMenuClose() {
            cancelAll()
            this.clearAllOptionCheckExiting()

            if (this.isGridLayout) {
                return
            }

            cancelAllOptionCheckAnimations(this.$refs.headlessOptionsList)

            this.$refs.headlessOptionsList?.querySelectorAll('.fi-select-input-option').forEach((option) => {
                const value = String(option.getAttribute('data-value') ?? '')
                const check = option.querySelector('.fff-select-option-selected-check')

                if (! check) {
                    return
                }

                setCheckVisibleInstant(check, this.isOptionSelected(value))
            })
        },

        queueOptionCheckEnter(value) {
            if (!this.comboboxOpen || this.__fffPickerDisposed) return
            this.bindSelectionCheckLifetime()
            const key = String(value)
            cancel('exit:' + key)
            if (this.isGridLayout || this.knownSelectedChecks.has(key)) {
                setCheckVisibleInstant(this.findOptionCheckElement(key), true)
                this.knownSelectedChecks.add(key)
                return
            }
            const id = 'enter:' + key
            cancel(id)
            const scope = createResourceScope()
            let releaseOwner = () => {}
            const release = () => { scope.dispose(); effects.delete(id); releaseOwner() }
            effects.set(id, release)
            releaseOwner = this.ensurePickerResourceScope().defer(release)
            const paint = callback => {
                let settled = false
                let frame = 0, timer = 0
                const stop = scope.defer(() => { cancelAnimationFrame(frame); clearTimeout(timer) })
                const finish = () => {
                    if (settled || scope.disposed) return
                    settled = true; stop(); callback()
                }
                frame = requestAnimationFrame(finish)
                timer = setTimeout(finish, 100)
            }
            paint(() => paint(() => {
                if (!this.__fffPickerDisposed && this.comboboxOpen && this.isOptionSelected(key)) {
                    const check = this.findOptionCheckElement(key)
                    if (check) { scheduleCheckEnter(check); this.knownSelectedChecks.add(key) }
                }
                release()
            }))
        },

        findOptionCheckElement(value) {
            const escaped = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                ? CSS.escape(value)
                : String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"')

            const button = this.$refs.headlessOptionsList?.querySelector(
                `.fi-select-input-option[data-value="${escaped}"]`,
            )

            return button?.querySelector('.fff-select-option-selected-check') ?? null
        }
    }
}
