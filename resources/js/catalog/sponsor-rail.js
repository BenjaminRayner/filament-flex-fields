/** Page-local overflow controller; all profiles remain reachable without growing the strip. */
export function createSponsorRail() {
    let observer = null
    let frame = null
    let disposed = false
    return {
        before: false, after: false,
        init() {
            this.$nextTick(() => {
                if (disposed) return
                this.measure()
                if (typeof ResizeObserver !== 'undefined') {
                    observer = new ResizeObserver(() => this.scheduleMeasure())
                    observer.observe(this.$refs.profiles)
                }
            })
        },
        measure() {
            const viewport = this.$refs.profiles
            if (!viewport || disposed) return
            const max = Math.max(0, viewport.scrollWidth - viewport.clientWidth)
            this.before = viewport.scrollLeft > 1
            this.after = viewport.scrollLeft < max - 1
        },
        scheduleMeasure() {
            if (disposed || frame !== null) return
            frame = requestAnimationFrame(() => { frame = null; this.measure() })
        },
        behavior() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' },
        move(direction) {
            const viewport = this.$refs.profiles
            viewport.scrollBy({ left: direction * Math.max(38, viewport.clientWidth * .7), behavior: this.behavior() })
            this.scheduleMeasure()
        },
        goToEdge(end) {
            const viewport = this.$refs.profiles
            viewport.scrollTo({ left: end ? viewport.scrollWidth : 0, behavior: this.behavior() })
            this.scheduleMeasure()
        },
        destroy() {
            disposed = true
            observer?.disconnect()
            if (frame !== null) cancelAnimationFrame(frame)
            frame = null
        },
    }
}
