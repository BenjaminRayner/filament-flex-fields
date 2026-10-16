/** Rolling presentation overlays the real readonly FLEX input; state stays native. */
export function createDateValueMotion(root, input) {
    const display = root.querySelector('[data-fff-date-value]')
    if (!display) return { set() {}, dispose() {} }
    const tokens = text => String(text).split(/(\d+|[^\d\s,./–—-]+)/u).filter(Boolean)
    const effects = new Set()
    let previous = '', previousOrder = null, disposed = false
    function stop() { for (const effect of effects) effect.cancel(); effects.clear(); display.querySelectorAll('[data-retiring]').forEach(node => node.remove()) }
    function play(node, frames, options, complete) {
        const effect = node.animate(frames, options); effects.add(effect)
        effect.finished.then(() => { effects.delete(effect); complete?.() }, () => effects.delete(effect))
    }
    return {
        set(text, order = null, immediate = false) {
            if (disposed || text === previous) return
            // Hydrate the exact server-rendered text without replacing it by
            // individual glyph containers (which can change kerning/spacing).
            if (immediate) {
                stop(); display.textContent = text
                root.classList.toggle('has-value-motion', Boolean(text))
                previous = text; previousOrder = order; return
            }
            const old = tokens(previous), next = tokens(text), direction = previousOrder && order && order < previousOrder ? -1 : 1
            stop()
            const reduced = immediate || matchMedia('(prefers-reduced-motion: reduce)').matches || !display.animate
            const parts = next.map((part, index) => {
                const shell = document.createElement('span'); shell.className = 'fff-date-value__part'
                const current = document.createElement('span'); current.textContent = part; shell.append(current)
                if (!reduced && old[index] && old[index] !== part && part.trim()) {
                    const retiring = document.createElement('span'); retiring.dataset.retiring = ''; retiring.textContent = old[index]; shell.append(retiring)
                    play(retiring, [{ opacity: 1, transform: 'translateY(0)', filter: 'blur(0)' }, { opacity: 0, transform: `translateY(${-direction * .3}em)`, filter: 'blur(2px)' }], { duration: 140, easing: 'cubic-bezier(.22,1,.36,1)' }, () => retiring.remove())
                    play(current, [{ opacity: 0, transform: `translateY(${direction * .35}em)`, filter: 'blur(3px)' }, { opacity: 1, transform: 'translateY(0)', filter: 'blur(0)' }], { duration: 240, easing: 'cubic-bezier(.22,1,.36,1)' })
                }
                return shell
            })
            display.replaceChildren(...parts)
            root.classList.toggle('has-value-motion', Boolean(text))
            previous = text; previousOrder = order
        },
        dispose() { disposed = true; stop(); root.classList.remove('has-value-motion') },
    }
}
