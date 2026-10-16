import headlessComboboxAlpine from '../components/select-field/headless-combobox-alpine.js'
import { acceptTag, acceptTagBatch, splitTagPaste, tagIdentity } from './value-policy.js'

/** Tags composes Select. It owns editing commands, never transport, positioning or option markup. */
export function createTagsController({ select, splitKeys = ['Tab'], duplicateInsensitive = false, suggestionsOnly = false,
    tagPrefix = '', tagSuffix = '', tagAddedMessage = '', tagRemovedMessage = '' }) {
    const base = headlessComboboxAlpine({ ...select, multiple: true, inlineSearch: true,
        keepSelectedOptionsInDropdown: false, allowCreateOption: !suggestionsOnly,
        onChange: (_values, _engine, owner) => owner.announceTags(),
    })
    const initialize = base.init
    const selectValue = base.comboboxSelectValue
    const createValue = base.selectCreateOption
    const disabledOption = base.isHeadlessOptionDisabled
    return {
        ...base, splitKeys, duplicateInsensitive, suggestionsOnly, tagPrefix, tagSuffix,
        tagAnnouncement: '', _lastTags: null,
        hasTagSuggestions: Boolean(select.hasDynamicSearchResults || select.options?.length),
        async init() {
            await initialize.call(this)
            if (this._selectDisposed) return
            this._lastTags = this.comboboxSelectedValues.slice()
            this.$el?.classList.add('is-hydrated')
        },
        announceTags() {
            const next = this.comboboxSelectedValues
            if (this._lastTags !== null) {
                const added = next.find(value => !this._lastTags.includes(value))
                const removed = this._lastTags.find(value => !next.includes(value))
                if (added !== undefined) this.tagAnnouncement = tagAddedMessage.replace(':tag', added)
                else if (removed !== undefined) this.tagAnnouncement = tagRemovedMessage.replace(':tag', removed)
            }
            this._lastTags = next.slice()
        },
        displayLabel(tag) { return `${this.tagPrefix}${tag}${this.tagSuffix}` },
        tagCountLabel() { return this.maxItems === null ? `${this.comboboxSelectedValues.length}` : `${this.comboboxSelectedValues.length}/${this.maxItems}` },
        shouldShowMenuSearch() { return this.isSheetPresentation() },
        isHeadlessOptionDisabled(option) {
            const identity = tagIdentity(option.value, this.duplicateInsensitive)
            return disabledOption.call(this, option) || this.comboboxSelectedValues.some(value => tagIdentity(value, this.duplicateInsensitive) === identity)
        },
        finishTagChoice() {
            this.comboboxSetQuery('')
            this.comboboxCloseMenu({ immediate: true })
        },
        comboboxSelectValue(value) {
            const count = this.comboboxSelectedValues.length
            selectValue.call(this, value)
            if (this.comboboxSelectedValues.length !== count) this.finishTagChoice()
        },
        selectCreateOption(value) { return this.addTag(value) },
        addTag(raw = this.comboboxQuery) {
            if (this.disabled || this._selectDisposed || !this._engine) return false
            const value = acceptTag(raw, {selected: this.comboboxSelectedValues, options: this.flatOptions,
                maximum: this.maxItems, suggestionsOnly: this.suggestionsOnly, insensitive: this.duplicateInsensitive})
            if (value === null) { if (this.hasReachedMaxItems()) this.showMaxItemsMessage(); return false }
            if (this.optionRecord(value)) selectValue.call(this, value)
            else if (!createValue.call(this, value)) return false
            this.finishTagChoice()
            return true
        },
        deleteTag(value) { this.comboboxDeselectValue(value) },
        reorderTags(event) { this.reorderSelectedChips(event) },
        onTagFocus() { if (!this.disabled && this.hasTagSuggestions) this.comboboxOpenMenu() },
        onTagInput(event) {
            if (event.isComposing || this.disabled) return
            this.comboboxSetQuery(event.target.value)
            if (!this.comboboxOpen && this.hasTagSuggestions) this.comboboxOpenMenu()
        },
        onTagBlur(event) {
            if (this.resolveMenuElement()?.contains(event.relatedTarget)) return
            this.addTag()
            this.comboboxCloseMenu()
        },
        onTagKeydown(event) {
            if (event.isComposing || this.disabled || this._selectDisposed) return
            if (event.key === 'Enter' || this.splitKeys.includes(event.key)) {
                event.preventDefault(); event.stopPropagation()
                if (event.key === 'Enter' && this.comboboxOpen && this._highlightedDisplayRowKey && this.comboboxSelectHighlighted()) return
                this.addTag(); return
            }
            this.onHeadlessControlKeydown(event)
        },
        onTagPaste(event) {
            if (this.disabled || this._selectDisposed || !this._engine) return
            const text = event.clipboardData?.getData('text/plain')
            if (!text || text.length > 1048576) return
            const parts = splitTagPaste(text, this.splitKeys)
            if (parts.length < 2) return
            event.preventDefault()
            const input = event.target
            const current = String(input.value ?? '')
            const start = input.selectionStart ?? current.length, end = input.selectionEnd ?? start
            const combined = current.slice(0, start) + text + current.slice(end)
            const values = acceptTagBatch(splitTagPaste(combined, this.splitKeys), {
                selected: this.comboboxSelectedValues, options: this.flatOptions,
                maximum: this.maxItems, suggestionsOnly: this.suggestionsOnly, insensitive: this.duplicateInsensitive,
            })
            this._engine.setSelectedValues(values)
            this._syncFromEngine()
            this.finishTagChoice()
        },
    }
}
