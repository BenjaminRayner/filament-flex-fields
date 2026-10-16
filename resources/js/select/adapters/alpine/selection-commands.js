import { setCheckVisibleInstant } from '../../../components/select-field/headless-select-selection-ux.js'

export function createSelectionCommandsMixin() {
    return {
        selectCreateOption(value) {
            if (this.disabled || this.__fffPickerDisposed) return false
            if (this.multiple && this.hasReachedMaxItems()) {
                this.showMaxItemsMessage()

                return false
            }

            if (! this._engine?.createInlineOption?.(value)) {
                return false
            }

            this.hideMaxItemsMessage()
            this._syncFromEngine()
            if (!this.multiple) this.comboboxCloseMenu({immediate: true})
            return true
        },

        hasReachedMaxItems() {
            const limit = this.maxItems

            if (limit == null || limit === '') {
                return false
            }

            return this.comboboxSelectedValues.length >= Number(limit)
        },

        showMaxItemsMessage() {
            if (! this.maxItemsMessage) {
                return
            }

            this.maxItemsMessageVisible = true
        },

        hideMaxItemsMessage() {
            this.maxItemsMessageVisible = false
        },

        shouldShowMaxItemsMessage() {
            return Boolean(this.multiple && this.maxItemsMessageVisible && this.maxItemsMessage)
        },

        reorderSelectedChips(event) {
            if (! this.isReorderable || ! this.multiple || this.disabled) {
                return
            }

            if (
                event
                && Number.isInteger(event.oldIndex)
                && Number.isInteger(event.newIndex)
                && event.oldIndex !== event.newIndex
            ) {
                const next = this.comboboxSelectedValues.slice()
                if (event.oldIndex < 0 || event.oldIndex >= next.length || event.newIndex < 0 || event.newIndex >= next.length) return
                const [moved] = next.splice(event.oldIndex, 1)

                if (moved === undefined) {
                    return
                }

                next.splice(event.newIndex, 0, moved)
                this._engine?.setSelectedValues(next)
                this._syncFromEngine()

                return
            }

            const container = this.$refs.headlessBadgesCtn

            if (! container) {
                return
            }

            const nextValues = Array.from(container.querySelectorAll('[data-value]'))
                .map((element) => element.getAttribute('data-value'))
                .filter((value) => value !== null && value !== '')

            const selected = new Set(this.comboboxSelectedValues.map(String))
            if (nextValues.length !== selected.size || new Set(nextValues).size !== selected.size || nextValues.some(value => !selected.has(value))) {
                return
            }

            this._engine?.setSelectedValues(nextValues)
            this._syncFromEngine()
        },
        rememberSelectedOption(value) {
            const record = this.optionRecord(value) ?? this.labelEntry(value)

            if (! record) {
                return
            }

            const entry = {
                ...record,
                entityMention: this.comboboxEntityMentionActive?.() ? true : (record.entityMention ?? false),
            }

            this.storeLabelEntry(value, entry)

            if (record.user) {
                this.storeUserInRepository(value, record.user)
            }
        },

        comboboxSelectValue(value) {
            if (this.disabled || this.__fffPickerDisposed) return
            const record = this.optionRecord(value)
            if (!record || this.isHeadlessOptionDisabled(record)) return
            const key = String(value)

            if (this.multiple && this.hasReachedMaxItems()) {
                this.showMaxItemsMessage()

                return
            }

            this.hideMaxItemsMessage()
            this.rememberSelectedOption(value)
            this._engine?.selectValue(value)
            this._syncFromEngine()

            if (! this.multiple) {
                this.comboboxCloseMenu({ immediate: true })
                this.syncInlineSearchInputAfterSelection(value)

                return
            }

            this.queueOptionCheckEnter(key)
        },

        comboboxDeselectValue(value) {
            if (this.disabled || this.__fffPickerDisposed) return
            const key = String(value)
            const shouldAnimateExit = this.multiple
                && ! this.isGridLayout
                && this.comboboxOpen

            const check = shouldAnimateExit ? this.findOptionCheckElement(key) : null
            const animateExit = Boolean(check && check.getAttribute('data-visible') === 'true')

            // Mark exiting BEFORE syncing selection so Alpine keeps the selected-row
            // chrome (and the check node) while chips update immediately.
            if (animateExit) {
                this.markOptionCheckExiting(key)
            }

            this.knownSelectedChecks.delete(key)
            this._engine?.deselectValue(key)
            this._syncFromEngine()

            if (this.comboboxOpen) {
                this.scheduleMenuPositionAfterLayout()
            }

            if (! animateExit) {
                setCheckVisibleInstant(check, false)

                return
            }

            this.queueOptionCheckExit(key, check)
        },

        finishDeselectValue(key) {
            this.clearOptionCheckExiting(key)
            this.knownSelectedChecks.delete(key)
            this._engine?.deselectValue(key)
            this._syncFromEngine()

            const check = this.findOptionCheckElement(key)
            setCheckVisibleInstant(check, false)

            if (this.comboboxOpen) {
                this.scheduleMenuPositionAfterLayout()
            }
        },

        toggleOption(value) {
            if (this.disabled) {
                return
            }

            const record = this.optionRecord(value)

            if (record && this.isHeadlessOptionDisabled(record)) {
                return
            }

            if (this.isOptionSelected(value)) {
                if (this.multiple) {
                    this.hideMaxItemsMessage()
                    this.comboboxDeselectValue(value)

                    return
                }

                if (this.clearable) {
                    this.clearSelection()
                }

                // Always close — re-clicking the current value used to leave the
                // glass panel open and cover sibling cascade fields (Region).
                this.comboboxCloseMenu({ immediate: true })

                return
            }

            this.comboboxSelectValue(value)

            // Keep @-mention mode ready for the next pick (multi) or clear the
            // trigger character after a single select so search does not stick.
            if (this.comboboxEntityMentionActive?.()) {
                if (this.multiple) {
                    this.comboboxSetQuery(String(this.mentionTrigger ?? '@'))
                } else {
                    this.comboboxSetQuery('')
                }
            }
        },

        clearSelection() {
            if (this.disabled || ! this.clearable) {
                return
            }

            this._engine?.setSelectedValues([])
            this._syncFromEngine()
            this.syncClearablePresentation()

            if (this.usesInlineSearchTriggerInput() && ! this.comboboxOpen) {
                this.syncInlineSearchInputAfterClose()
            }
        },


    }
}
