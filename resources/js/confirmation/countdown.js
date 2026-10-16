/** Ring and seconds share one monotonic deadline. Digits animate only when the integer changes. */
export function createCountdownPresentation(host, scope, reduced) {
    const ring = host.querySelector('.fff-confirm-morph__ring-fill'), holder = host.querySelector('[data-countdown-number]')
    const effects = new Set()
    let ringControl = null, digit = holder?.firstElementChild ?? null, value = null
    const animateDigit = (node, frames, timing, done) => {
        if (!node?.animate) { done?.(); return }
        const animation = node.animate(frames, timing); effects.add(animation)
        animation.finished.then(() => { effects.delete(animation); done?.(); animation.cancel() }, () => effects.delete(animation))
    }
    const stop = () => { ringControl?.cancel(); ringControl = null; for (const effect of effects) effect.cancel(); effects.clear() }
    scope.defer(stop)
    return {
        start(seconds) {
            stop(); value = seconds
            if (holder) { holder.replaceChildren(); digit = holder.ownerDocument.createElement('span'); digit.textContent = String(seconds); holder.append(digit) }
            ringControl = ring?.animate?.([{ strokeDashoffset: 0 }, { strokeDashoffset: 1 }], { duration: seconds * 1000, easing: 'linear', fill: 'forwards' })
        },
        seconds(next) {
            if (!holder || next === value) return
            value = next
            for (const effect of effects) effect.cancel(); effects.clear()
            for (const child of [...holder.children]) if (child !== digit) child.remove()
            const old = digit, fresh = holder.ownerDocument.createElement('span')
            fresh.textContent = String(next); digit = fresh; holder.append(fresh)
            const easing = 'cubic-bezier(.16,1,.3,1)', simple = reduced?.matches
            animateDigit(old, [{ opacity: 1, transform: 'translateY(0)', filter: 'blur(0px)' }, { opacity: 0, transform: simple ? 'none' : 'translateY(-6px)', filter: simple ? 'none' : 'blur(2px)' }],
                { duration: 220, easing, fill: 'forwards' }, () => old?.remove())
            animateDigit(fresh, [{ opacity: 0, transform: simple ? 'none' : 'translateY(6px)', filter: simple ? 'none' : 'blur(2px)' }, { opacity: 1, transform: 'translateY(0)', filter: 'blur(0px)' }],
                { duration: 220, easing })
        },
        pause(paused) { if (paused) ringControl?.pause(); else ringControl?.play() },
        stop,
    }
}
