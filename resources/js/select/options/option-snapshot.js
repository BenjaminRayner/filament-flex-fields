/** Read a collection for pure projection without subscribing to every hidden record.
 * Public options remain mutable; the adapter's revision owns projection replacement.
 */
export function readRawOptionSnapshot(options) {
    return globalThis.Alpine?.raw?.(options) ?? options
}
