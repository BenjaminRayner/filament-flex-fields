import { syncSettingsMenuViewportFromPanel, syncSettingsMenuViewTransition, clearSettingsMenuViewportSize } from '../core/settings-menu-transition.js'

const views = ['model', 'language', 'task']
const phaseKey = view => `settings${view[0].toUpperCase()}${view.slice(1)}Phase`
export function createAudioSettings(scope) {
    let generation = 0, stopWatch = null, cancelClose = null, cancelFrame = null
    scope.defer(() => { generation++; cancelClose?.(); cancelFrame?.(); stopWatch?.() })
    const later = (vm, callback) => vm.$nextTick(() => { if (!scope.disposed) callback() })
    return {
        settingsOpen: false, settingsView: 'root', settingsViewDirection: 'forward', settingsActiveSubmenu: null,
        settingsMenuPopoverPhase: null, settingsModelPhase: 'hidden', settingsLanguagePhase: 'hidden', settingsTaskPhase: 'hidden',
        get settingsMenu() { return this.$refs.sttSettingsMenu },
        get selectedModelLabel() { return this.transcriptionModelOptions.find(m => m.id === this.sttModel)?.label ?? this.sttModel },
        get selectedLanguageLabel() { return this.transcriptionLanguages.find(l => (l.code ?? '') === this.sttLanguage)?.label ?? this.labels.transcription?.language_auto ?? 'Auto detect' },
        get selectedTaskLabel() { return this.labels.transcription?.[this.sttTask === 'translate' ? 'task_translate' : 'task_transcribe'] ?? this.sttTask },
        getSettingsPanelRef(view) { return this.$refs[`sttSettingsPanel${view[0].toUpperCase()}${view.slice(1)}`] },
        getSettingsSubmenuPhase(view) { return this[phaseKey(view)] ?? 'hidden' },
        isSettingsRootInactive() { return this.settingsActiveSubmenu !== null },
        syncSettingsMenuViewport() {
            const menu = this.settingsMenu
            if (!menu || !this.settingsOpen) return
            const win = menu.ownerDocument.defaultView, trigger = this.$refs.sttSettingsTrigger?.getBoundingClientRect()
            const available = Math.max(96, win.innerHeight - (trigger?.bottom ?? 0) - 16)
            menu.style.setProperty('--fff-video-field-menu-max-height', `${available}px`)
            menu.style.setProperty('--fff-settings-menu-available-width', `${Math.min(320, win.innerWidth - 32)}px`)
            syncSettingsMenuViewportFromPanel(menu, this.getSettingsPanelRef(this.settingsActiveSubmenu ?? 'root'), 192)
        },
        toggleSettingsMenu() { this.settingsOpen ? this.closeSettingsMenu() : this.openSettingsMenu() },
        openSettingsMenu() {
            if (!this.transcriptionEnabled || !this.settingsMenu || scope.disposed) return
            generation++; cancelClose?.(); this.settingsOpen = true; this.settingsMenuPopoverPhase = 'starting'
            later(this, () => {
                this.syncSettingsMenuViewport(); void this.settingsMenu.offsetHeight
                cancelFrame?.(); cancelFrame = scope.frame(() => { if (!scope.disposed) this.settingsMenuPopoverPhase = null })
                stopWatch?.()
                const releases = [scope.listen(window, 'resize', () => this.syncSettingsMenuViewport()), scope.listen(document, 'keydown', e => {
                    if (e.key === 'Escape') { e.preventDefault(); this.closeSettingsMenu(); this.$refs.sttSettingsTrigger?.focus() }
                })]
                stopWatch = () => releases.forEach(release => release())
            })
        },
        closeSettingsMenu() {
            if (!this.settingsOpen || scope.disposed) return
            const token = ++generation
            this.settingsMenuPopoverPhase = 'ending'; cancelClose?.(); stopWatch?.(); stopWatch = null
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
            cancelClose = scope.timeout(() => {
                if (scope.disposed || token !== generation) return
                this.settingsOpen = false; this.settingsMenuPopoverPhase = null; this.resetSettingsMenuNavigation()
            }, reduced ? 0 : 220)
        },
        onSettingsMenuClickOutside(event) { if (!this.$refs.sttSettingsTrigger?.contains(event.target)) this.closeSettingsMenu() },
        resetSettingsMenuNavigation() {
            this.settingsView = 'root'; this.settingsActiveSubmenu = null
            for (const view of views) this[phaseKey(view)] = 'hidden'
            clearSettingsMenuViewportSize(this.settingsMenu)
        },
        openSettingsView(view) {
            if (view === 'root') { this.navigateSettingsBack(); return }
            if (!views.includes(view) || scope.disposed) return
            const token = ++generation
            for (const name of views) this[phaseKey(name)] = name === view ? 'entering' : 'hidden'
            this.settingsView = view; this.settingsViewDirection = 'forward'; this.settingsActiveSubmenu = view
            later(this, () => {
                const panel = this.getSettingsPanelRef(view)
                syncSettingsMenuViewTransition(this.settingsMenu, panel, { phase: 'entering', direction: 'forward' }, this.getSettingsPanelRef('root'), 192)
                void panel?.offsetHeight
                cancelFrame?.(); cancelFrame = scope.frame(() => {
                    if (token !== generation || scope.disposed) return
                    this[phaseKey(view)] = 'active'
                    syncSettingsMenuViewTransition(this.settingsMenu, panel, { phase: 'active', direction: 'forward' }, this.getSettingsPanelRef('root'), 192)
                })
            })
        },
        navigateSettingsBack() {
            const view = this.settingsActiveSubmenu
            if (!view) return
            const token = ++generation
            this.settingsView = 'root'; this.settingsViewDirection = 'back'; this[phaseKey(view)] = 'exiting'
            later(this, () => syncSettingsMenuViewTransition(this.settingsMenu, this.getSettingsPanelRef(view), { phase: 'exiting', direction: 'back' }, this.getSettingsPanelRef('root'), 192))
            cancelClose?.(); cancelClose = scope.timeout(() => {
                if (scope.disposed || token !== generation) return
                this[phaseKey(view)] = 'hidden'; this.settingsActiveSubmenu = null; this.syncSettingsMenuViewport()
            }, 250)
        },
        selectSttModel(value) { this.sttModel = value; this.ensureValidSttModel(); this.navigateSettingsBack() },
        selectSttLanguage(value) { this.sttLanguage = value ?? ''; this.navigateSettingsBack() },
        selectSttTask(value) { this.sttTask = value; this.navigateSettingsBack() },
        toggleSttMultilingual() { this.sttMultilingual = !this.sttMultilingual; if (!this.sttMultilingual) this.sttLanguage = ''; this.ensureValidSttModel() },
        toggleSttQuantized() { this.sttQuantized = !this.sttQuantized; this.ensureValidSttModel() },
    }
}
