import { createLabelRepository } from '../../async/label-repository.js'
import { flattenHeadlessOptions } from '../../options/option-tree.js'
export function createSelectSelectedLabelsMixin({ initialOptionLabel, initialOptionLabels, multiple, livewireId, statePath }) {
    return {
        bindSelectedOptionLabelRefreshListener() {
            if (this._refreshOptionLabelListener || ! livewireId || ! statePath) {
                return
            }

            this._refreshOptionLabelListener = async (event) => {
                if (event?.detail?.livewireId !== livewireId || event?.detail?.statePath !== statePath) {
                    return
                }

                this.ensureHeadlessSsrHandoff?.()

                // Filament explicitly requests this after create/edit, commonly
                // with the picker closed. Wait for the updated wire selection.
                await this.$nextTick?.()
                if (this._selectDisposed) return
                this._pendingSelectedLabelRefresh = false
                await this.resolveSelectedLabelsFromServer()

                if (! this.hasDynamicOptions) {
                    return
                }

                this.invalidateDynamicOptionsCache({ clearStaleOptions: false })
                if (this.comboboxOpen) await this.fetchDynamicOptions({ soft: true })
            }

            window.addEventListener(
                'filament-forms::select.refreshSelectedOptionLabel',
                this._refreshOptionLabelListener,
            )
        },

        teardownSelectedOptionLabelRefreshListener() {
            if (! this._refreshOptionLabelListener) {
                return
            }

            window.removeEventListener(
                'filament-forms::select.refreshSelectedOptionLabel',
                this._refreshOptionLabelListener,
            )
            this._refreshOptionLabelListener = null
        },

        seedLabelRepository() {
            if (initialOptionLabel != null && initialOptionLabel !== '') {
                const value = this.comboboxSelectedValues[0]

                if (value !== undefined) {
                    this.storeLabelEntry(value, {
                        value,
                        label: String(initialOptionLabel),
                        triggerLabel: String(initialOptionLabel),
                    })
                }
            }

            if (Array.isArray(initialOptionLabels) && initialOptionLabels.length > 0) {
                for (const entry of initialOptionLabels) {
                    if (entry?.value === undefined) {
                        continue
                    }

                    this.storeLabelEntry(entry.value, entry)
                }
            }

            if (Array.isArray(this.initialSelectedUserEntries)) {
                for (const entry of this.initialSelectedUserEntries) {
                    if (entry?.value === undefined) {
                        continue
                    }

                    this.storeLabelEntry(entry.value, {
                        value: entry.value,
                        label: entry.user?.name ?? String(entry.value),
                        triggerLabel: entry.user?.name ?? String(entry.value),
                        user: entry.user,
                        fffClientRender: Boolean(entry.user),
                    })

                    if (entry.user && typeof this.storeUserInRepository === 'function') {
                        this.storeUserInRepository(entry.value, entry.user)
                    }
                }
            }

            this.populateLabelRepositoryFromOptions(this.options)
        },

        populateLabelRepositoryFromOptions(options) {
            // Validate the entire response before writing any repository entry.
            // Label endpoints use the same cycle/depth/node bounds as options.
            const entries = flattenHeadlessOptions(options)
            for (const option of entries) {
                if (option?.value === undefined || option?.value === null) continue
                this.storeLabelEntry(option.value, option)
                if (option.user && typeof this.storeUserInRepository === 'function') {
                    this.storeUserInRepository(option.value, option.user)
                }
            }
        },

        storeLabelEntry(value, entry) {
            if (value === undefined || value === null || ! entry) {
                return
            }

            const key = String(value)

            this._labelRepositoryStore ??= createLabelRepository({ records: this.labelRepository })
            this._labelRepositoryStore.pin(this.comboboxSelectedValues)
            this._labelRepositoryStore.put(key, entry)
        },

        labelEntry(value) {
            const key = String(value)

            this._labelRepositoryStore?.pin(this.comboboxSelectedValues)
            const cached = this._labelRepositoryStore?.get(key)
            if (cached) return cached

            const fromKnown = this._knownLabelEntries?.get(key)

            if (fromKnown) {
                return fromKnown
            }

            return this._engine?.findOption(key) ?? null
        },

        hasMissingSelectedLabels() {
            if (this.comboboxSelectedValues.length === 0) {
                return false
            }

            return this.comboboxSelectedValues.some((value) => {
                const entry = this.labelEntry(value)

                return entry == null || entry.label == null || entry.label === ''
            })
        },

        async resolveSelectedLabelsFromServer() {
            if (! this.componentKey || this.comboboxSelectedValues.length === 0) {
                return
            }

            if (this._selectDisposed) return
            const selected = this.comboboxSelectedValues.slice()
            const lease = this._selectLabelRequests.begin('labels')
            if (!lease) return
            const current = () => lease.current() && this._valuesEqual(selected, this.comboboxSelectedValues)
            try {
                if (multiple) {
                    const results = await this.callSchemaMethod('getOptionLabelsForJs')
                    if (!current()) return

                    if (Array.isArray(results)) {
                        this.populateLabelRepositoryFromOptions(results)
                        this.bumpTriggerLabelEpoch?.()
                        this.markHeadlessDisplayReady?.()
                    }

                    return
                }

                const label = await this.callSchemaMethod('getOptionLabel')
                if (!current()) return

                if (label != null && label !== '') {
                    const value = selected[0]

                    this.storeLabelEntry(value, {
                        value,
                        label: String(label),
                        triggerLabel: String(label),
                    })
                    this.bumpTriggerLabelEpoch?.()
                    this.markHeadlessDisplayReady?.()
                }
            } catch {
                // Labels remain as raw values until the next successful fetch.
            } finally { lease.finish() }
        },

    }
}
