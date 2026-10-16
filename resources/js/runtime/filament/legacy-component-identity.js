/** Explicit compatibility helper for historical markup, never used by x-fff-load. */
export function resolveLegacyComponentIdentity(el) {
    const fieldSelectors = [
        '.fi-fo-select-wrp',
        '.fff-select-field-wrapper',
        '.fi-fo-field-wrp',
        '[data-fff-lazy-alpine-mount]',
    ]

    for (const selector of fieldSelectors) {
        const fieldScope = el?.closest?.(selector)

        if (!fieldScope) {
            continue
        }

        const fromFieldBatch = fieldScope.querySelector?.('[data-fff-asset-consumer]')?.dataset?.fffAssetConsumer

        if (fromFieldBatch) {
            return fromFieldBatch
        }

        const fromParentBatch = fieldScope.parentElement?.querySelector?.('[data-fff-asset-consumer]')?.dataset?.fffAssetConsumer

        if (fromParentBatch) {
            return fromParentBatch
        }
    }

    const entryUrl = el?.getAttribute?.('x-fff-load-src')
        ?? el?.getAttribute?.('x-load-src')
        ?? ''
    const match = String(entryUrl).match(/\/components\/(?:flex-fields-)?([^./?]+)\.js/i)

    return match?.[1] ?? null
}
