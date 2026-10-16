export default function flexCodePreview({ sources = [] } = {}) {
    let timer = null
    let alive = true
    let observer = null
    return {
        active: '0', copied: false, failed: false,
        init() {
            if (typeof ResizeObserver !== 'undefined') {
                observer = new ResizeObserver(() => this.revealActive())
                observer.observe(this.$refs.files)
            }
        },
        revealActive() {
            const files = this.$refs.files
            const tab = files?.querySelector('[aria-selected="true"]')
            if (!tab || !files.clientWidth) return
            const box = files.getBoundingClientRect()
            const active = tab.getBoundingClientRect()
            if (active.left < box.left) files.scrollLeft += active.left - box.left
            else if (active.right > box.right) files.scrollLeft += active.right - box.right
        },
        select(key) {
            this.active = String(key); this.copied = false; this.failed = false; clearTimeout(timer)
            this.$nextTick(() => this.revealActive())
        },
        step(direction, button) {
            this.goTo((Number(this.active) + direction + sources.length) % sources.length, button)
        },
        goTo(index, button) {
            this.select(index)
            this.$nextTick(() => button.parentElement.querySelector('[aria-selected="true"]')?.focus())
        },
        async copy() {
            const active = this.active
            try {
                await navigator.clipboard.writeText(sources[Number(active)] ?? '')
                if (!alive || this.active !== active) return
                this.copied = true; this.failed = false
            } catch {
                if (!alive || this.active !== active) return
                this.failed = true; this.copied = false
            }
            clearTimeout(timer)
            timer = setTimeout(() => { this.copied = false; this.failed = false }, 2200)
        },
        destroy() { alive = false; clearTimeout(timer); observer?.disconnect() },
    }
}
