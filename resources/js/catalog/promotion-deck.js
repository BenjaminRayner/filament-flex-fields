/** One timeout per deck. CSS owns the circle animation; no frame polling or page-global state. */
export function createPromotionDeck(ids, options = {}) {
    const count = ids.length
    const duration = Math.max(3000, Math.min(60000, Number(options.interval) || 7000))
    let timer = null, startedAt = 0, remaining = duration
    let visibilityListener = null, pointerStart = null, motionQuery = null, motionListener = null
    const now = () => performance.now()
    const stop = (deck) => {
        if (timer !== null) { clearTimeout(timer); remaining = Math.max(0, remaining - (now() - startedAt)) }
        timer = null
        deck.running = false
    }
    return {
        active: 0, count, duration, cycle: 0, imageStates: {}, paused: false, userPaused: false, running: false,
        init() {
            this.$nextTick(() => {
                this.$el.querySelectorAll('[data-promotion-image]').forEach(image => {
                    if (image.complete) this.imageSettled(image.dataset.promotionImage, image.naturalWidth > 0)
                })
                this.resume()
            })
            visibilityListener = () => { if (document.hidden) stop(this); else this.resume() }
            document.addEventListener('visibilitychange', visibilityListener)
            motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
            motionListener = () => { if (motionQuery.matches) stop(this); else this.resume() }
            motionQuery.addEventListener('change', motionListener)
        },
        go(index) {
            stop(this)
            if (count > 0) this.active = ((index % count) + count) % count
            remaining = duration
            ++this.cycle
            this.resume()
        },
        move(direction) { this.go(this.active + direction) },
        choose(index) { this.go(index) },
        dotClass(index) {
            const start = Math.max(0, Math.min(this.active - 2, count - 5))
            if (count <= 5) return this.active === index ? 'is-active' : ''
            if (index < start || index >= start + 5) return 'is-outside'
            if (index === this.active) return 'is-active'
            return index === start || index === start + 4 ? 'is-edge' : ''
        },
        pause() { this.paused = true; stop(this) },
        resume() {
            if (!options.autoplay || count < 2 || typeof document === 'undefined') return
            if (this.userPaused || this.$el?.matches(':hover') || this.$el?.contains(document.activeElement) || document.hidden || motionQuery?.matches) { this.paused = true; stop(this); return }
            const slide = this.$el?.querySelector(`[data-promotion-slide="${this.active}"]`)
            const images = slide ? JSON.parse(slide.dataset.slideImages) : []
            if (!this.slideReady(images)) { stop(this); return }
            this.paused = false
            if (timer !== null) return
            this.running = true
            startedAt = now()
            timer = setTimeout(() => { timer = null; this.running = false; this.move(1) }, remaining)
        },
        toggleAutoplay() { this.userPaused = !this.userPaused; if (this.userPaused) this.pause(); else this.resume() },
        restart() { this.resume() },
        pointerDown(event) { if (event.pointerType === 'touch') { pointerStart = { x: event.clientX, y: event.clientY }; this.pause() } },
        pointerUp(event) {
            if (!pointerStart) return
            const dx = event.clientX - pointerStart.x, dy = event.clientY - pointerStart.y
            pointerStart = null
            if (Math.abs(dx) > 48 && Math.abs(dx) > Math.abs(dy) * 1.5) this.move(dx < 0 ? 1 : -1)
            this.resume()
        },
        pointerCancel() { pointerStart = null; this.resume() },
        imageSettled(key, success = true) { this.imageStates[key] = success ? 'ready' : 'failed'; this.resume() },
        imagePending(key) { return !this.imageStates[key] },
        slideReady(keys) { return !options.pending && keys.every(key => Boolean(this.imageStates[key])) },
        destroy() { stop(this); if (visibilityListener) document.removeEventListener('visibilitychange', visibilityListener); if (motionListener) motionQuery?.removeEventListener('change', motionListener); pointerStart = null },
    }
}
