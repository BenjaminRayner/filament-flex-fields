import { createMediaOwner } from '../media/lifecycle/owner.js'
import { createRichEditorChrome } from './runtime/chrome.js'
import { createRichEditorAutosave } from './runtime/autosave.js'
import { createRichEditorSelection } from './runtime/selection.js'
import {
    buildRichEditorFooterMetrics,
    cancelIdleWork,
    countImagesMissingAlt,
    createDebouncedScheduler,
    createRafScheduler,
    getEditorPlainText,
    isEmptyRichEditorState,
    runWhenIdle,
    shouldEnableRichEditorChromeSync,
    shouldTrackRichEditorFooterStats,
} from '../core/rich-editor-chrome.js'
import { loadFilamentRichEditorFormComponent } from '../support/load-filament-rich-editor.js'
import { mergeAlpineComponentData } from '../support/merge-alpine-component-data.js'

export default function flexRichEditorFormComponent(options) {
    const richEditorSrc = options.richEditorSrc

    if (! richEditorSrc) {
        throw new Error('FlexRichEditor requires the Filament rich editor runtime source URL.')
    }

    const footerConfig = options.footerConfig ?? {}
    const distractionFreeHiddenTools = options.distractionFreeHiddenTools ?? []
    const floatingToolbars = options.floatingToolbars ?? {}
    const componentKey = options.key
    const canAttachFiles = options.canAttachFiles ?? false

    delete options.footerConfig
    delete options.distractionFreeHiddenTools
    delete options.pasteCleanupMode
    delete options.richEditorSrc
    delete options.youtubeConfig

    const owner = createMediaOwner()
    let booting = false
    const session = { owner, chromeSyncScheduler: null, overlaySyncScheduler: null, altTextSyncScheduler: null, teardownToolbarA11y: null, boundEditor: null, editorUpdateChromeHandler: null, visibilityChromeHandler: null, autosaveIdleHandle: null, imageOverlayResizeObserver: null, lastParagraphToolbarHidden: null, lastOverlayGeometryKey: null }
    const trackFooterStats = shouldTrackRichEditorFooterStats(footerConfig)
    const enableChromeSync = shouldEnableRichEditorChromeSync(footerConfig)
    const enforceHardLimits = footerConfig.limitBehavior === 'hard'
    const trackAltText = footerConfig.altTextRequired === true
    const hasParagraphFloatingToolbar = 'paragraph' in floatingToolbars

    const flexExtensions = {
        footerConfig,
        distractionFreeHiddenTools,
        isRichEditorFullscreen: false,
        autosaveSavedAt: null,
        autosaveTimer: null,
        autosaveDebounceTimer: null,
        autosaveSavedClearTimer: null,
        autosaveSavingMinTimer: null,
        autosaveSavingStartedAt: null,
        autosaveLastSerializedState: null,
        autosaveStatus: 'idle',
        autosaveReady: false,
        autosaveLastEditorUpdatedAt: null,
        footerStats: footerConfig.labels?.empty ?? '',
        footerLimitStatus: 'ok',
        footerAltMissingCount: 0,
        imageOverlayVisible: false,
        imageOverlayScrollTarget: null,
        imageOverlayOnScroll: null,
        imageOverlayOnResize: null,


    }

    const configuration = { footerConfig, distractionFreeHiddenTools, componentKey, canAttachFiles, trackFooterStats, enableChromeSync, enforceHardLimits, trackAltText, hasParagraphFloatingToolbar }
    for (const mixin of [createRichEditorChrome(session, configuration), createRichEditorAutosave(session, configuration), createRichEditorSelection(session, configuration)]) {
        Object.defineProperties(flexExtensions, Object.getOwnPropertyDescriptors(mixin))
    }

    return mergeAlpineComponentData(
        {
            state: options.state,
            activePanel: null,
            editorSelection: { type: 'text', anchor: 1, head: 1 },
            isUploadingFile: false,
            fileValidationMessage: null,
            shouldUpdateState: true,
            editorUpdatedAt: null,
            _filamentRichEditorBootstrapped: false,
            _baseDestroy: null,

            getEditor() {
                return null
            },

            $getEditor() {
                return this.getEditor()
            },

            async init() {
                if (this._filamentRichEditorBootstrapped || booting || owner.disposed) {
                    return
                }

                booting = true
                try {
                const richEditorFormComponent = await loadFilamentRichEditorFormComponent(richEditorSrc)
                if (owner.disposed) return
                const base = richEditorFormComponent(options)
                const baseInit = base.init

                this._baseDestroy = base.destroy

                for (const key of Reflect.ownKeys(base)) {
                    if (key === 'init' || key === 'destroy') {
                        continue
                    }

                    const descriptor = Object.getOwnPropertyDescriptor(base, key)
                    if (typeof descriptor?.value === 'function') this[key] = descriptor.value
                    else if (descriptor) Object.defineProperty(this, key, descriptor)

                }

                // Override runEditorCommands to avoid Y[F.name] minification bug in native Filament
                this.runEditorCommands = function(detail) {
                    const commands = detail.commands || [];
                    const editorSelection = detail.editorSelection;
                    try {
                        this.setEditorSelection(editorSelection);
                    } catch (e) {
                        console.warn('Failed to restore editor selection before running commands:', e);
                        // Do not halt execution if selection restoration fails (e.g., due to state desync)
                    }

                    const editor = typeof this.getEditor === 'function' ? this.getEditor() : this.$getEditor();
                    if (!editor) return;

                    commands.forEach((command) => {
                        let commandChain = editor.chain().focus();
                        if (command.arguments && command.arguments.length) {
                            command.arguments.forEach((argument) => {
                                for (let [key, value] of Object.entries(argument ?? {})) {
                                    if (typeof value === 'string' && value.startsWith('view::')) {
                                        argument[key] = window.Livewire.find(detail.livewireId).components.componentsByName[value.substring(6)][0];
                                    }
                                }
                            });
                        }
                        
                        if (typeof commandChain[command.name] === 'function') {
                            commandChain[command.name](...(command.arguments || []));
                        } else {
                            console.error(`Command ${command.name} not found on editor chain!`);
                        }
                        commandChain.run();
                    });
                };

                if (typeof baseInit === 'function') {
                    await baseInit.call(this)
                }

                if (owner.disposed) { base.destroy?.call(this); this._baseDestroy = null; return }
                this._filamentRichEditorBootstrapped = true
                booting = false

                this.restoreAutosaveDraftIfNeeded()
                this.seedAutosaveBaseline()
                this.autosaveLastEditorUpdatedAt = this.editorUpdatedAt
                this.autosaveReady = true
                this.patchFloatingToolbarPlugins()

                const frame = requestAnimationFrame(() => {
                    releaseFrame()
                    if (!owner.disposed) void this.setupRichEditorToolbarA11y()
                })
                const releaseFrame = owner.defer(() => cancelAnimationFrame(frame))

                this.startAutosaveTimer()

                if (trackFooterStats) {
                    this.scheduleRichEditorChromeSync()
                }
                } finally { booting = false }
            },

            destroy() {
                if (owner.disposed) return
                owner.dispose()
                session.chromeSyncScheduler?.cancel()
                session.overlaySyncScheduler?.cancel()
                session.altTextSyncScheduler?.cancel()
                cancelIdleWork(session.autosaveIdleHandle)
                session.autosaveIdleHandle = null
                this.teardownEditorChromeSync(session.boundEditor)
                this.disconnectImageOverlayResizeObserver()
                session.teardownToolbarA11y?.()
                session.teardownToolbarA11y = null

                if (this.autosaveTimer) {
                    clearInterval(this.autosaveTimer)
                }

                clearTimeout(this.autosaveDebounceTimer)
                clearTimeout(this.autosaveSavedClearTimer)
                clearTimeout(this.autosaveSavingMinTimer)

                if (this.imageOverlayScrollTarget && this.imageOverlayOnScroll) {
                    this.imageOverlayScrollTarget.removeEventListener('scroll', this.imageOverlayOnScroll)
                }

                if (typeof this._baseDestroy === 'function') {
                    this._baseDestroy.call(this)
                }

                this._baseDestroy = null
                this._filamentRichEditorBootstrapped = false
            },
        },
        flexExtensions,
    )
}
