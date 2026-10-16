import { beginTapGesture, createTapGestureTracker, resolveTapGesture, resolveVideoHotkeyAction } from './values.js'
import { positionTopPopup } from './ui/position.js'

export function videoUi(runtime) {
    const tap = createTapGestureTracker()
    return {
        revealUi() {
            if (runtime.disposed) return
            this.showUi = true
            runtime.cancel('hide-ui')
            if (this.autoHideControls && this.showControls && this.playing && !this.readOnly && !this.volumeOpen && !this.settingsOpen) runtime.schedule('hide-ui', () => {
                if (this.playing && !this.volumeOpen && !this.settingsOpen) { this.showUi = false; this.dismissAllControlTooltips() }
            }, 3200)
        },
        scheduleHideUi() { this.revealUi() },
        clearHideUiTimeout() { runtime.cancel('hide-ui') },
        onFrameMove() { if (this.playing && this.autoHideControls) this.revealUi() },
        onFrameLeave() { if (this.playing && this.autoHideControls) this.revealUi() },
        onFramePointerDown(event) {
            if (!this.canInteract || !this.showControls || this.nativeControls || event.target.closest('button,input,[role=slider],[role=menu],.fff-video-field__volume-popover')) return
            beginTapGesture(tap, event)
        },
        onFramePointerUp(event) {
            if (!this.canInteract || !this.showControls || this.nativeControls || this.progressDragging || event.target.closest('button,input,[role=slider],[role=menu],.fff-video-field__volume-popover')) return
            if (!event.target.closest('.fff-video-field__video,.fff-video-field__empty,.fff-video-field__placeholder,.fff-video-field__youtube-facade')) return
            const gesture = resolveTapGesture(tap, event, this.$refs.frame.getBoundingClientRect())
            if (!gesture) return
            if (gesture.isDoubleTap) { gesture.region === 'center' ? this.toggleFullscreen() : this.seekStep((gesture.region === 'left' ? -1 : 1) * this.skipSeconds); return }
            if (event.pointerType === 'touch' || event.pointerType === 'pen') {
                if (!this.playing) { this.revealUi(); return }
                this.showUi = !this.showUi; if (this.showUi) this.revealUi(); else this.dismissAllControlTooltips()
            } else if (gesture.region === 'center') this.togglePlay()
        },
        async onFrameKeydown(event) {
            if (!this.canInteract || this.nativeControls || event.target.closest('[role=menu]')) return
            const action = resolveVideoHotkeyAction(event)
            if (!action) return
            // A focused button already owns Space/Enter; do not trigger two actions.
            if (event.key === ' ' && event.target.tagName === 'BUTTON') return
            event.preventDefault()
            const actions = {
                togglePaused: () => this.togglePlay(), toggleMuted: () => this.toggleMute(), toggleFullscreen: () => this.toggleFullscreen(), togglePictureInPicture: () => this.togglePictureInPicture(),
                seekBackShort: () => this.seekStep(-5), seekForwardShort: () => this.seekStep(5), volumeUp: () => this.setVolume(this.volume + .05), volumeDown: () => this.setVolume(this.volume - .05), escape: () => { this.closeVolumePanel(); this.closeSettingsMenu() },
            }
            await actions[action]?.()
        },
        showControlTooltip(wrapper) {
            if (!wrapper || this.coarsePointer || !this.tooltips || wrapper.classList.contains('is-tooltip-suppressed')) return
            runtime.schedule('tooltip', () => { if (!wrapper.classList.contains('is-tooltip-suppressed')) { wrapper.classList.add('is-tooltip-visible'); this.positionControlTooltip(wrapper) } }, 600)
        },
        hideControlTooltip(wrapper) { runtime.cancel('tooltip'); wrapper?.classList.remove('is-tooltip-visible', 'is-tooltip-suppressed') },
        suppressControlTooltip(wrapper) { runtime.cancel('tooltip'); wrapper?.classList.add('is-tooltip-suppressed'); wrapper?.classList.remove('is-tooltip-visible') },
        dismissAllControlTooltips() { runtime.cancel('tooltip'); this.$el.querySelectorAll('.is-tooltip-visible').forEach(e => e.classList.remove('is-tooltip-visible')) },
        positionControlTooltip(wrapper) {
            const tooltip = wrapper?.querySelector('[role=tooltip]'), trigger = wrapper?.querySelector('button'), frame = this.$refs.frame
            if (!tooltip || !trigger || !frame) return
            positionTopPopup(tooltip, trigger, frame)
        },
        toggleCompactVolumeControl() { this.toggleVolumeControl() },
        toggleVolumeControl() { if (this.canInteract && this.volumeControl) this.coarsePointer ? this.toggleVolumePanel() : this.toggleMute() },
        toggleVolumePanel() { if (!this.canInteract || !this.volumeControl) return; this.volumeOpen = !this.volumeOpen; if (this.volumeOpen) this.closeSettingsMenu(); this.revealUi() },
        closeVolumePanel() { runtime.cancel('volume-open'); runtime.cancel('volume-close'); this.volumeOpen = false; this.revealUi() },
        openVolumePopoverHover() {
            if (this.coarsePointer || !this.volumeControl) return
            runtime.cancel('volume-close'); if (!this.volumeOpen) runtime.schedule('volume-open', () => { this.volumeOpen = true; this.revealUi() }, 200)
        },
        closeVolumePopoverHover() {
            runtime.cancel('volume-open'); if (!this.coarsePointer) runtime.schedule('volume-close', () => this.closeVolumePanel(), 100)
        },
        onVolumeWheel(event) { this.setVolume(this.volume + (event.deltaY < 0 ? .05 : -.05)) },
        toggleSettingsMenu() { if (this.settingsMenu && this.canInteract) { this.closeVolumePanel(); runtime.menu?.toggle() } },
        openSettingsMenu() { runtime.menu?.open() },
        closeSettingsMenu() { runtime.menu?.close() },
        onSettingsMenuClickOutside(event) { if (!this.$refs.settingsTrigger?.contains(event.target)) runtime.menu?.close() },
        openSettingsView(view) { runtime.menu?.navigate(view) },
        navigateSettingsBack() { runtime.menu?.navigate('root') },
        completeSettingsItemSelection() { runtime.menu?.navigate('root') },
    }
}
