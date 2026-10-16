const ACTIVATION_CLASSES = new Set(['hidden', 'invisible'])
const classState = (value, classes) => String(value ?? '').split(/\s+/).filter(value => classes.has(value)).sort().join(' ')
// Repeated declarations retain their order: the last declaration can change visibility.
const styleState = value => String(value ?? '').match(/(?:^|;)\s*(?:display|visibility)\s*:[^;]*/gi)?.map(part => part.replace(/^;/, '').replace(/\s+/g, '').toLowerCase()).join(';') ?? ''

/** Geometry, transition transforms and theme classes do not change asset activation. */
export function changesAssetActivation(mutation, classes = ACTIVATION_CLASSES) {
    if (mutation.attributeName === 'class') return classState(mutation.oldValue, classes) !== classState(mutation.target.getAttribute('class'), classes)
    if (mutation.attributeName === 'style') return styleState(mutation.oldValue) !== styleState(mutation.target.getAttribute('style'))
    return ['hidden', 'aria-hidden', 'data-fff-asset-active', 'open'].includes(mutation.attributeName)
}
