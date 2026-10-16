import { headlessOptionLabelHtml, headlessOptionValue } from '../../../components/select-field/headless-select-options.js'

export function createSelectionPresentationMixin() {
    return {
        labelEntry(value) {
            return this._knownLabelEntries.get(String(value)) ?? null
        },

        storeLabelEntry(value, entry) {
            if (! entry) {
                return
            }

            this._knownLabelEntries.set(String(value), entry)
        },

        smartCreateRowLabel() {
            const query = String(this.comboboxQuery ?? '').trim()

            if (query === '') {
                return this.createOptionLabel
            }

            return `${this.createOptionLabel} "${query}"`
        },

        smartCreateRowHtml() {
            const query = String(this.comboboxQuery ?? '').trim()

            if (query === '') {
                return this.escapeHtml(String(this.createOptionLabel ?? ''))
            }

            const label = this.escapeHtml(String(this.createOptionLabel ?? ''))
            const term = this.escapeHtml(query)

            return `${label} <em class="fff-select-smart-create__term">"${term}"</em>`
        },

        escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
        },

        optionRecord(value) {
            const normalized = String(value)
            return this._engine?.findOption?.(normalized)
                ?? this._knownLabelEntries?.get(normalized)
                ?? (this.labelRepository && Object.hasOwn(this.labelRepository, normalized) ? this.labelRepository[normalized] : null)
        },

        optionLabel(value) {
            if (this.isUserSelectField) {
                const userHtml = this.userOptionLabelHtml(value, 'trigger')

                if (userHtml !== String(value)) {
                    return userHtml
                }
            }

            const stored = this.labelEntry(value)

            if (stored) {
                return headlessOptionLabelHtml(stored, 'trigger')
            }

            const match = this.optionRecord(value)

            return match ? headlessOptionLabelHtml(match, 'trigger') : this.escapeHtml(value)
        },

        optionDropdownLabel(option) {
            if (option?.fffClientRender && option?.user) {
                if (typeof this.renderUserOptionHtml === 'function') {
                    return this.renderUserOptionHtml(option.user, 'list')
                }

                return String(option.user?.name ?? option.label ?? option.user?.email ?? '')
            }

            return headlessOptionLabelHtml(option, 'dropdown')
        },

        headlessOptionValue(option) {
            return headlessOptionValue(option)
        },

        selectedChips() {
            return this.comboboxSelectedValues.map((value) => ({
                value,
                label: this.entityMentionChipLabel?.(value) ?? this.optionLabel(value),
                isEntityMention: this.isEntityMentionValue?.(value) ?? false,
            }))
        },

        triggerLabelHtml() {
            // Depend on epoch so Alpine re-runs `x-html` after user/label seeding.
            void this.triggerLabelEpoch

            if (this.isUserSelectField) {
                return this.userSelectTriggerHtml()
            }

            if (this.multiple) {
                return this.placeholder
            }

            if (this.comboboxSelectedValues.length === 0) {
                return this.placeholder
            }

            return this.optionLabel(this.comboboxSelectedValues[0])
        },

        isTriggerLabelSelected() {
            return this.comboboxSelectedValues.length > 0
        },

        isOptionSelected(value) {
            // Keep the reactive dependency while using the engine's canonical Map.
            void this.comboboxSelectedValues
            return this._engine?.isSelected(value) ?? this.comboboxSelectedValues.includes(String(value))
        },

        isEventInsideHeadlessMenu(event) {
            const target = event?.target

            if (! target || typeof target.closest !== 'function') {
                return false
            }

            const menu = typeof this.resolveMenuElement === 'function'
                ? this.resolveMenuElement()
                : this.$refs?.headlessMenu

            if (menu?.contains?.(target)) {
                return true
            }

            const owner = this.componentKey ?? this.statePath ?? null

            if (owner == null || String(owner) === '') {
                return Boolean(target.closest('.fff-select-headless-menu'))
            }

            const escaped = typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                ? CSS.escape(String(owner))
                : String(owner).replace(/\\/g, '\\\\').replace(/"/g, '\\"')

            return Boolean(target.closest(`.fff-select-headless-menu[data-fff-select-menu-owner="${escaped}"]`))
        },


    }
}
