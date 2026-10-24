import { createComboboxEngine } from '../../engine/combobox-engine.js'
import { flattenHeadlessOptions, headlessOptionLabelHtml, headlessOptionValue } from '../../../components/select-field/headless-select-options.js'
import { readRawOptionSnapshot } from '../../options/option-snapshot.js'

/** The engine callback is the sole route from a user command to persisted state. */
export function createSelectEngineBindingMixin({ engineConfig, userOnChange }) {
    return {
        createSelectEngine(initialSelectedValues) {
            // Publish the same snapshot the engine receives. Initialization must
            // not immediately flatten and replace this identical collection again.
            const options = flattenHeadlessOptions(readRawOptionSnapshot(this.options))
            this.flatOptions = options
            this._engineOptionsFingerprint = this.fingerprintHeadlessOptions(options)
            this._engine = createComboboxEngine({
                options,
                multiple: this.multiple,
                searchable: this.searchable,
                getOptionLabel: engineConfig.getOptionLabel ?? ((option) => headlessOptionLabelHtml(option, 'dropdown')),
                getOptionValue: engineConfig.getOptionValue ?? headlessOptionValue,
                filterFn: this.hasDynamicSearchResults ? this.serverSideFilterFn()
                    : (option) => this.resolveHeadlessOptionProjection().positions.has(String(headlessOptionValue(option))),
                hideSelectedOptions: this.multiple && !this.keepSelectedOptionsInDropdown,
                isOptionDisabled: (option) => this.isHeadlessOptionDisabled(option),
                virtualizeThreshold: this.virtualizeThreshold,
                virtualWindowSize: this.virtualWindowSize,
                initialSelectedValues,
                recentValues: this.recentOptionValues,
                suggestedValues: this.suggestedOptionValues,
                allowCreate: this.allowCreateOption,
                createOptionLabel: (query) => `${this.createOptionLabel} "${query}"`,
                onChange: (values) => {
                    if (this._selectDisposed) return
                    if (!this._programmaticSelectionWrite) this._userHasMutatedSelection = true
                    this.comboboxSelectedValues = values
                    this.syncStateFromEngine(values)
                    this.syncClearablePresentation()
                    this.syncUserSelectTags?.()
                    userOnChange?.(values, this._engine, this)
                    this.queueSelectLayoutRefresh()
                },
            })
        },
        reconcileSelectWireState(nextValues) {
            // Hydration must not trigger onChange, mark a user edit, or echo a new commit.
            this._engine?.setSelectedValues(nextValues, { notify: false })
            this._syncFromEngine()
            this.syncClearablePresentation()
            if (!this.comboboxOpen) this.syncInlineSearchInputAfterClose()
        },
    }
}
