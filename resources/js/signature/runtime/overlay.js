/** Native top-layer dialogs keep focus, Escape handling and scroll containment owned. */
export function createSignatureOverlay(owner) {
    return {
        openFullscreen() {
            if (!this.fullscreenEnabled || this.readOnly || owner.disposed) return
            this.stopReplay(); this.stopWipe(); this.isFullscreen = true
            this.$nextTick(() => {
                if (owner.disposed) return
                this.$refs.fullscreenDialog?.showModal()
                this.resizeCanvas(); this.scheduleRedraw()
            })
        },
        closeFullscreen() {
            this.isFullscreen = false
            this.$refs.fullscreenDialog?.close()
            if (!owner.disposed) this.$nextTick(() => { if (!owner.disposed) { this.resizeCanvas(); this.scheduleRedraw() } })
        },
        confirmFullscreen() { this.commitState(); this.closeFullscreen() },
    }
}
