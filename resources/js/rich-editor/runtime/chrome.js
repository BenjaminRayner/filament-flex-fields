import { buildRichEditorFooterMetrics, countImagesMissingAlt, createDebouncedScheduler, createRafScheduler, getEditorPlainText } from '../../core/rich-editor-chrome.js'


export function createRichEditorChrome(session, configuration) {
    const { footerConfig, distractionFreeHiddenTools, trackFooterStats, enableChromeSync, enforceHardLimits, trackAltText } = configuration
    return {
        scheduleRichEditorChromeSync() {
            if (! enableChromeSync) {
                return
            }

            if (document.hidden && ! enforceHardLimits) {
                return
            }

            if (! session.chromeSyncScheduler) {
                session.chromeSyncScheduler = createRafScheduler(() => {
                    this.syncRichEditorChrome()
                })
            }

            session.chromeSyncScheduler.schedule()
        },

        shouldDeferChromeSync() {
            return document.hidden && ! enforceHardLimits
        },

        setupEditorChromeSync(editor) {
            if (! enableChromeSync || session.editorUpdateChromeHandler) {
                return
            }

            session.boundEditor = editor
            session.editorUpdateChromeHandler = () => {
                this.scheduleRichEditorChromeSync()
            }

            editor.on('update', session.editorUpdateChromeHandler)

            if (trackFooterStats && ! enforceHardLimits) {
                session.visibilityChromeHandler = () => {
                    if (! document.hidden) {
                        this.scheduleRichEditorChromeSync()
                    }
                }

                document.addEventListener('visibilitychange', session.visibilityChromeHandler)
            }
        },

        teardownEditorChromeSync(editor) {
            if (editor && session.editorUpdateChromeHandler) {
                editor.off('update', session.editorUpdateChromeHandler)
            }

            session.editorUpdateChromeHandler = null
            session.boundEditor = null

            if (session.visibilityChromeHandler) {
                document.removeEventListener('visibilitychange', session.visibilityChromeHandler)
                session.visibilityChromeHandler = null
            }
        },

        syncRichEditorChrome() {
            if (trackFooterStats) {
                this.updateRichEditorFooterStats()
            }

            this.scheduleAutosaveFromEdit()
        },

        updateRichEditorFooterStats() {
            const editor = session.boundEditor ?? (typeof this.$getEditor === 'function' ? this.$getEditor() : null)

            if (! editor) {
                this.footerStats = footerConfig.labels?.empty ?? ''
                this.footerLimitStatus = 'ok'
                this.footerAltMissingCount = 0

                return
            }

            const metrics = buildRichEditorFooterMetrics(getEditorPlainText(editor), footerConfig)

            if (this.footerStats !== metrics.footerStats) {
                this.footerStats = metrics.footerStats
            }

            if (this.footerLimitStatus !== metrics.footerLimitStatus) {
                this.footerLimitStatus = metrics.footerLimitStatus
            }

            if (trackAltText) {
                if (! session.altTextSyncScheduler) {
                    session.altTextSyncScheduler = createDebouncedScheduler(() => {
                        const activeEditor = typeof this.$getEditor === 'function' ? this.$getEditor() : null
                        const missing = activeEditor ? countImagesMissingAlt(activeEditor) : 0

                        if (this.footerAltMissingCount !== missing) {
                            this.footerAltMissingCount = missing
                        }
                    })
                }

                session.altTextSyncScheduler.schedule()
            } else if (this.footerAltMissingCount !== 0) {
                this.footerAltMissingCount = 0
            }

            if (footerConfig.limitBehavior === 'hard') {
                this.enforceHardContentLimits(editor, {
                    characters: metrics.characters,
                    words: metrics.words,
                })
            }
        },

        enforceHardContentLimits(editor, { characters, words }) {
            const maxCharacters = footerConfig.maxCharacters
            const maxWords = footerConfig.maxWords

            if (maxCharacters == null && maxWords == null) {
                return
            }

            const exceedsCharacters = maxCharacters != null && characters > maxCharacters
            const exceedsWords = maxWords != null && words > maxWords

            if (! exceedsCharacters && ! exceedsWords) {
                return
            }

            editor.commands.undo()
        },

        isDistractionFreeToolbarButton(buttonName) {
            if (! this.isRichEditorFullscreen || ! this.footerConfig.distractionFree) {
                return false
            }

            return this.distractionFreeHiddenTools.includes(buttonName)
        },

        toggleRichEditorFullscreen() {
            this.isRichEditorFullscreen = ! this.isRichEditorFullscreen
        },


    }
}
