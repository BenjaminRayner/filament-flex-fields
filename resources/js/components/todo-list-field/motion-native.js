/** Native element animations avoid inherited row-variable writes on every frame. */
const owners = new WeakMap()
const clip = (value) => `inset(-3px ${(1 - value) * 100}% -3px 0)`

export function strikeProgress(row) {
    const owner = owners.get(row)
    if (!owner) return null
    if (owner.complete) return owner.on ? 1 : 0
    const elapsed = Number(owner.clock.currentTime) || 0
    const ratio = Math.min(1, Math.max(0, elapsed / owner.duration))
    return owner.on ? Math.min(1, ratio * 2) : owner.from * (1 - ratio)
}

export function cancelNativeTodoMotion(row) {
    const owner = owners.get(row)
    if (!owner) return
    owners.delete(row)
    for (const animation of owner.animations) animation.cancel()
}

export function animateNativeTodoStrike(
    row,
    { on, from = 1, onComplete } = {},
) {
    const title = row.querySelector?.('.fff-todo-list-field__title-wrap')
    const strikes = [
        ...(row.querySelectorAll?.('.fff-todo-list-field__strike') ?? []),
    ]
    if (!title?.animate || strikes.some((node) => !node.animate)) return false
    const duration = on ? 300 : 250
    const animations = []
    const owner = { animations, on, from, duration, clock: null }
    try {
        const titleFrames = on
            ? [
                  { transform: 'translateX(0px)', offset: 0 },
                  { transform: 'translateX(2px)', offset: 0.5 },
                  { transform: 'translateX(0px)', offset: 1 },
              ]
            : [
                  { transform: 'translateX(0px)' },
                  { transform: 'translateX(0px)' },
              ]
        owner.clock = title.animate(titleFrames, {
            duration,
            fill: 'both',
            easing: 'linear',
        })
        animations.push(owner.clock)
        for (const strike of strikes)
            animations.push(
                strike.animate(
                    on
                        ? [
                              { clipPath: clip(0), offset: 0 },
                              { clipPath: clip(1), offset: 0.5 },
                              { clipPath: clip(1), offset: 1 },
                          ]
                        : [{ clipPath: clip(from) }, { clipPath: clip(0) }],
                    { duration, fill: 'both', easing: 'linear' },
                ),
            )
        if (on) {
            const lines = row.querySelector('.fff-todo-list-field__lines')
            if (lines?.animate)
                animations.push(
                    lines.animate(
                        [
                            { strokeDashoffset: '13.5px' },
                            { strokeDashoffset: '4.5px' },
                        ],
                        { delay: 200, duration: 200, easing: 'linear' },
                    ),
                )
        }
    } catch {
        for (const animation of animations) animation.cancel()
        return false
    }
    owners.set(row, owner)
    owner.clock.finished.then(
        () => {
            if (owners.get(row) !== owner) return
            owner.complete = true
        row.style.setProperty('--text-line-scale', on ? '1' : '0')
            row.style.setProperty('--text-x', '0px')
            // The spark runs 100 ms longer than the title; retain its owner until it finishes.
            const spark = animations.at(-1)
            if (on && animations.length > strikes.length + 1) {
                for (const animation of animations.slice(0, -1))
                    animation.cancel()
                spark.finished.then(
                    () => {
                        if (owners.get(row) === owner)
                            cancelNativeTodoMotion(row)
                    },
                    () => {},
                )
            } else cancelNativeTodoMotion(row)
            if (row.isConnected !== false) onComplete?.()
        },
        () => {},
    )
    return true
}
