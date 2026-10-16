import { spring } from 'motion'

/** Arc's Motion generator, sampled once for native Web Animations. */
const transitions = new Map()
export function springTransition(visualDuration, bounce = 0) {
    const key = `${visualDuration}/${bounce}`
    if (transitions.has(key)) return transitions.get(key)
    const generator = spring({ keyframes: [0, 1], visualDuration, bounce, restDelta: .0001, restSpeed: .001 })
    const samples = []
    let elapsed = 0
    for (; elapsed <= 10000; elapsed += 1000 / 120) {
        const { value, done } = generator.next(elapsed)
        samples.push(value)
        if (done) break
    }
    samples[samples.length - 1] = 1
    const result = { duration: (samples.length - 1) / 120 * 1000, easing: `linear(${samples.map(value => value.toFixed(5)).join(',')})` }
    if (transitions.size >= 16) transitions.delete(transitions.keys().next().value)
    transitions.set(key, result)
    return result
}
