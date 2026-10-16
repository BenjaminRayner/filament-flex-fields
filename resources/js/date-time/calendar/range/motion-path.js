import { spring } from 'motion'

const properties = ['left', 'top', 'width', 'height', 'radius']

/** Arc's physical GROW/SHRINK springs, in pixels rather than normalized progress. */
export function createRangeMaterialPath(from, to, entering, velocity = {}) {
    const root = 2 * Math.PI / ((entering ? .48 : .38) * 1.2)
    const generators = Object.fromEntries(properties.map(property => {
        const small = Math.abs(to[property] - from[property]) < 5
        return [property, spring({ keyframes: [from[property], to[property]],
            stiffness: root * root, damping: 2 * (1 - (entering ? .12 : 0)) * root, mass: 1,
            velocity: velocity[property] || 0, restDelta: small ? .01 : .5, restSpeed: small ? .1 : 2 })]
    }))
    function at(time) {
        const values = {}, speeds = {}
        let done = true
        for (const property of properties) {
            const sample = generators[property].next(Math.max(0, time))
            // Motion reuses its sample object. Capture it before asking for
            // the derivative, otherwise both samples become the same value.
            const value = sample.value, settled = sample.done
            values[property] = value
            speeds[property] = settled ? 0 : (generators[property].next(Math.max(0, time) + 1).value - value) * 1000
            done &&= settled
        }
        return { values, velocity: speeds, done }
    }
    const frames = []
    let duration = 0
    for (; duration <= 2000; duration += 1000 / 120) {
        const sample = at(duration), pose = sample.values
        frames.push({ width: `${Math.max(1, pose.width)}px`, height: `${Math.max(1, pose.height)}px`,
            borderRadius: `${Math.max(0, pose.radius)}px`, transform: `translate(${pose.left - to.left}px,${pose.top - to.top}px)` })
        if (sample.done) break
    }
    const last = frames[frames.length - 1]
    Object.assign(last, { width: `${to.width}px`, height: `${to.height}px`, borderRadius: `${to.radius}px`, transform: 'translate(0,0)' })
    return { frames, duration: (frames.length - 1) * 1000 / 120, at }
}
