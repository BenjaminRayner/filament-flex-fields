import { cancelIdleWork, isEmptyRichEditorState, runWhenIdle } from '../../core/rich-editor-chrome.js'


export function createRichEditorAutosave(session, configuration) {
    const { footerConfig } = configuration
    return {
        scheduleAutosaveFromEdit() {
            if (! footerConfig.autosave || ! this.autosaveReady) {
                return
            }

            if (this.editorUpdatedAt === this.autosaveLastEditorUpdatedAt) {
                return
            }

            this.autosaveLastEditorUpdatedAt = this.editorUpdatedAt

            clearTimeout(this.autosaveDebounceTimer)

            if (isEmptyRichEditorState(this.state)) {
                this.autosaveStatus = 'idle'

                return
            }

            const serialized = JSON.stringify(this.state)

            if (serialized === this.autosaveLastSerializedState) {
                return
            }

            const intervalMs = Math.max(5, Number(footerConfig.autosaveInterval || 30)) * 1000
            const debounceMs = Math.min(2000, Math.max(750, Math.round(intervalMs / 4)))

            this.autosaveDebounceTimer = setTimeout(() => {
                this.persistAutosaveDraft()
            }, debounceMs)
        },

        seedAutosaveBaseline() {
            if (! footerConfig.autosave) {
                return
            }

            if (isEmptyRichEditorState(this.state)) {
                this.autosaveLastSerializedState = null

                return
            }

            const currentSerialized = JSON.stringify(this.state)
            const storageKey = `fff-rich-editor:${footerConfig.autosaveKey}`
            const rawDraft = localStorage.getItem(storageKey)

            if (rawDraft) {
                try {
                    const draft = JSON.parse(rawDraft)

                    if (draft?.state != null && JSON.stringify(draft.state) === currentSerialized) {
                        this.autosaveLastSerializedState = currentSerialized
                        this.autosaveSavedAt = draft.savedAt ?? null

                        return
                    }
                } catch {
                    // Ignore invalid drafts.
                }
            }

            this.autosaveLastSerializedState = currentSerialized
        },

        markAutosaveSaving() {
            clearTimeout(this.autosaveSavingMinTimer)

            if (this.autosaveStatus !== 'saving') {
                this.autosaveSavingStartedAt = Date.now()
            }

            this.autosaveStatus = 'saving'
        },

        finishAutosaveSaving(savedAt, serialized) {
            const storageKey = `fff-rich-editor:${footerConfig.autosaveKey}`
            const savingStartedAt = this.autosaveSavingStartedAt ?? Date.now()
            const minSavingMs = 500

            const showSaved = () => {
                this.autosaveLastSerializedState = serialized
                this.autosaveSavedAt = savedAt
                this.autosaveStatus = 'saved'
                this.autosaveSavingStartedAt = null
                this.clearAutosaveSavedIndicatorSoon()
            }

            try {
                const payload = JSON.stringify({
                    state: this.state,
                    savedAt,
                })

                cancelIdleWork(session.autosaveIdleHandle)

                session.autosaveIdleHandle = runWhenIdle(() => {
                    session.autosaveIdleHandle = null

                    try {
                        localStorage.setItem(storageKey, payload)
                    } catch {
                        this.autosaveStatus = 'idle'
                        this.autosaveSavingStartedAt = null

                        return
                    }

                    const remaining = minSavingMs - (Date.now() - savingStartedAt)

                    if (remaining > 0) {
                        this.autosaveSavingMinTimer = setTimeout(showSaved, remaining)

                        return
                    }

                    showSaved()
                })
            } catch {
                this.autosaveStatus = 'idle'
                this.autosaveSavingStartedAt = null
            }
        },

        clearAutosaveSavedIndicatorSoon() {
            clearTimeout(this.autosaveSavedClearTimer)

            this.autosaveSavedClearTimer = setTimeout(() => {
                if (this.autosaveStatus === 'saved') {
                    this.autosaveStatus = 'idle'
                }
            }, 3000)
        },

        persistAutosaveDraft() {
            if (! footerConfig.autosave) {
                return
            }

            if (isEmptyRichEditorState(this.state)) {
                this.autosaveStatus = 'idle'

                return
            }

            const serialized = JSON.stringify(this.state)

            if (serialized === this.autosaveLastSerializedState) {
                if (this.autosaveStatus === 'saving') {
                    this.autosaveStatus = 'idle'
                    this.autosaveSavingStartedAt = null
                }

                return
            }

            this.markAutosaveSaving()
            this.finishAutosaveSaving(new Date().toISOString(), serialized)
        },

        restoreAutosaveDraftIfNeeded() {
            if (! footerConfig.autosave) {
                return
            }

            const storageKey = `fff-rich-editor:${footerConfig.autosaveKey}`
            const rawDraft = localStorage.getItem(storageKey)

            if (! rawDraft) {
                return
            }

            let draft

            try {
                draft = JSON.parse(rawDraft)
            } catch {
                return
            }

            if (! draft?.savedAt || draft.state == null) {
                return
            }

            if (! isEmptyRichEditorState(this.state)) {
                return
            }

            const shouldRestore = window.confirm(footerConfig.labels?.autosaveRestorePrompt ?? '')

            if (! shouldRestore) {
                return
            }

            this.state = draft.state
            this.autosaveSavedAt = draft.savedAt
            this.autosaveLastSerializedState = JSON.stringify(draft.state)
        },

        startAutosaveTimer() {
            if (! footerConfig.autosave) {
                return
            }

            const intervalMs = Math.max(5, Number(footerConfig.autosaveInterval || 30)) * 1000

            this.autosaveTimer = setInterval(() => {
                this.persistAutosaveDraft()
            }, intervalMs)
        },
    }
}
