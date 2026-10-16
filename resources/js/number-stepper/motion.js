/** Native compositor animations: no RAF loop or per-frame Alpine state writes. */
export function limitFrames(edge, amplitude) {
    return Array.from({ length: 49 }, (_, index) => {
        const progress = index / 48
        const displacement = index === 48 ? 0 : -edge * amplitude * Math.exp(-6 * progress) * Math.sin(12 * progress) / 0.514
        return { transform: `translateY(${displacement}px)`, offset: progress }
    })
}

export function createStepperMotionMixin() {
    return {
        ownAnimation(element, frames, options, complete = () => {}) {
            if (this._stepperDisposed || !element?.animate) { complete(); return null }
            const animation = element.animate(frames, options)
            this._stepperAnimations.add(animation)
            const release = this._stepperScope.defer(() => animation.cancel())
            const finish = () => {
                if (!this._stepperDisposed) complete()
                this._stepperAnimations.delete(animation)
                release()
            }
            animation.finished.then(finish, finish)
            return animation
        },
        pushLimit(edge, button) {
            if (this._stepperDisposed || this.disabled) return
            this._limitAnimation?.cancel()
            this._iconAnimation?.cancel()
            const now = performance.now()
            const last = this._limitPush
            const count = last?.edge === edge && now - last.at < 500 ? Math.min(5, last.count + 1) : 1
            this._limitPush = { edge, count, at: now }
            if (this.reducedMotion || !this.wheelAnimated) return
            this._limitAnimation = this.ownAnimation(this.$refs.valueDisplay, limitFrames(edge, 4 + count), { duration: 620, easing: 'linear' })
            this._iconAnimation = this.ownAnimation(button?.querySelector('.fff-number-stepper__icon'),
                [0, -2.5, 2.5, -1.5, 1, 0].map(x => ({ transform: `translateX(${x}px)` })),
                { duration: 320, easing: 'ease-out' })
        },
        updateSuffix(text, direction, animate) {
            const node = this.$refs.suffixText
            if (!node || this._lastSuffix === text || this._stepperDisposed) return
            this._lastSuffix = text
            const chars = Array.from(text ? '\u00a0' + text : '')
            const current = Array.from(node.children)
            // Retire previous exits so rapid 1→2→1 changes cannot duplicate letters.
            for (const child of current.filter(child => child.dataset.exiting === 'true')) child.remove()
            const active = Array.from(node.children)
            for (let index = 0; index < Math.max(chars.length, active.length); index++) {
                const old = active[index]
                if (old?.textContent === chars[index]) continue
                const animated = animate && this.wheelAnimated && !this.reducedMotion
                if (old) {
                    old.dataset.exiting = 'true'
                    if (animated) this.ownAnimation(old,
                        [{ width: old.getBoundingClientRect().width + 'px', opacity: 1, transform: 'translateY(0)' },
                            { width: '0px', opacity: 0, transform: `translateY(${-direction * .3}em)` }],
                        { duration: 180, easing: 'cubic-bezier(.2,0,.2,1)', fill: 'both' }, () => old.remove())
                    else old.remove()
                }
                if (chars[index] === undefined) continue
                const span = node.ownerDocument.createElement('span')
                span.className = 'fff-number-stepper__suffix-char'
                span.textContent = chars[index]
                node.insertBefore(span, old?.isConnected ? old : (active[index + 1] ?? null))
                if (animated) this.ownAnimation(span,
                    [{ width: '0px', opacity: 0, transform: `translateY(${direction * .3}em) scale(.6)` },
                        { width: span.getBoundingClientRect().width + 'px', opacity: 1, transform: 'translateY(0) scale(1)' }],
                    { duration: 380, easing: 'cubic-bezier(.22,1,.36,1)' })
            }
        },
    }
}
