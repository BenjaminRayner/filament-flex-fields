/** A retained disposed handle contains no root, callbacks or document adapter. */
export function createSurfaceHandle(record) {
    const live = () => {
        if (!record.actions) throw new DOMException('FLEX surface lifetime ended.', 'AbortError')
        return record.actions
    }
    return Object.freeze({
        activate: () => live().activate(),
        suspend: () => live().suspend(),
        close: () => record.actions?.close(),
        dispose: () => record.actions?.dispose(),
        get state() { return record.disposed ? 'disposed' : record.state },
        get ready() { return record.actions?.ready() === true },
    })
}
