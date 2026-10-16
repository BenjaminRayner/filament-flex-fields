export function configureConfirmation(input = {}) {
    const config = { variant: 'hold', mode: 'press', duration: 2000, releaseDuration: 200, sweep: 'right',
        confirmTimeout: 6000, resultTimeout: 5000, countdown: 3, cancelOnOutsidePress: true,
        disabled: false, hasUndo: false, labels: {}, ...input }
    for (const [key, min, max] of [['duration', 200, 60000], ['releaseDuration', 0, 5000], ['confirmTimeout', 0, 60000], ['resultTimeout', 0, 60000], ['countdown', 1, 30]]) {
        if (!Number.isSafeInteger(config[key]) || config[key] < min || config[key] > max) throw Error(`Invalid confirmation ${key}`)
    }
    if (!['hold', 'morph'].includes(config.variant) || !['press', 'hold', 'countdown'].includes(config.mode)
        || !['left', 'right', 'up', 'down'].includes(config.sweep)) throw Error('Invalid confirmation mode')
    return config
}
