/** Publish lazy behavior on the field's own reactive object, never a merged parent scope. */
export function publishOwnerData(instance, additions) {
    const host = instance._pickerHostElement ?? instance.$el
    const owner = host?._x_dataStack?.[0] ?? instance
    Object.assign(owner, additions)
    return owner
}
