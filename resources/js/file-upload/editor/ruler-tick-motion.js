/** Damped return fitted to visible clip-path samples from the public exposure demo. */
export function rulerTickPulse(ageMs) {
    if (ageMs >= 840) return 0
    const time = Math.max(0, ageMs) / 1000
    const frequency = Math.sqrt(128 - 9.1 ** 2)
    return Math.exp(-9.1 * time) * (Math.cos(frequency * time) + 9.1 / frequency * Math.sin(frequency * time))
}

/** The yellow notch belongs to the moving strip; passed notches retain their own return spring. */
export function createRulerTickMotion({ ticks, min, step, spacing, reduced }) {
    const moving = new Map()
    let active = null
    const clear = index => {
        const tick = ticks[index]
        tick.style.clipPath = ''; tick.style.opacity = ''; tick.style.backgroundColor = ''; tick.style.width = ''; tick.style.marginLeft = ''
    }
    function pass(from, to, time) {
        if (reduced) return
        const first = Math.max(0, Math.ceil((Math.min(from, to) - min) / step))
        const last = Math.min(ticks.length - 1, Math.floor((Math.max(from, to) - min) / step))
        if (from === to) return
        for (let index = first; index <= last; index++) moving.set(index, time)
    }
    function paint(time, position) {
        const nearest = Math.round((position - min) / step)
        // Match the demo's small activation window; between notches there is no detached yellow overlay.
        const next = nearest >= 0 && nearest < ticks.length && Math.abs((min + nearest * step - position) * spacing / step) <= spacing * 5 / 13 ? nearest : null
        if (active !== next) {
            if (active !== null) {
                ticks[active].style.backgroundColor = ''; ticks[active].style.width = ''; ticks[active].style.marginLeft = ''
                if (reduced) clear(active)
                else moving.set(active, time)
            }
            active = next
            if (active !== null) {
                moving.delete(active)
                const tick = ticks[active]
                tick.style.backgroundColor = '#f4d845'; tick.style.width = '3px'; tick.style.marginLeft = '-1.5px'; tick.style.clipPath = 'inset(0% 0 0)'; tick.style.opacity = '1'
            }
        }
        for (const [index, started] of moving) {
            if (index === active) { moving.delete(index); continue }
            const tick = ticks[index], age = time - started
            if (age >= 840) { clear(index); moving.delete(index); continue }
            const pulse = rulerTickPulse(age)
            const opacity = (min + index * step) % 50 === 0 ? .72 : .44
            tick.style.clipPath = `inset(${50 * (1 - pulse)}% 0 0)`
            tick.style.opacity = String(opacity + (1 - opacity) * pulse)
        }
        return moving.size > 0
    }
    function reset() {
        for (const index of moving.keys()) clear(index)
        if (active !== null) clear(active)
        active = null; moving.clear()
    }
    return { pass, paint, reset }
}
