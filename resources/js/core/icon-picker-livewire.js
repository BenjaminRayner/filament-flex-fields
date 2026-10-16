function isUsableLivewireWire(wire) {
    return wire != null && (typeof wire.callSchemaComponentMethod === 'function'
        || typeof wire.call === 'function' && typeof wire.get === 'function')
}

/** The original field host wins over a teleported element's current surroundings. */
export function resolveIconPickerLivewire(component) {
    const host = (component._pickerHostElement ?? component.$el)?.closest('[wire\\:id], [wire-id]')
    if (host && typeof Livewire !== 'undefined' && typeof Livewire.find === 'function') {
        const id = host.getAttribute('wire:id') ?? host.getAttribute('wire-id')
        const wire = id ? Livewire.find(id) : null
        if (isUsableLivewireWire(wire)) return wire
    }
    return isUsableLivewireWire(component.$wire) ? component.$wire : null
}

/** A rejected request is a failure, never permission to send it a second time. */
export async function callIconPickerSchemaMethod(component, method, params = {}) {
    if (component.__fffPickerDisposed) throw new DOMException('Aborted', 'AbortError')
    if (!component.componentKey) throw new Error('Icon schema component identity is missing.')
    const wire = resolveIconPickerLivewire(component)
    if (!wire) throw new Error('Icon Livewire transport is unavailable.')
    if (typeof wire.callSchemaComponentMethod === 'function') {
        return await wire.callSchemaComponentMethod(component.componentKey, method, params)
    }
    if (typeof wire.call === 'function') {
        return await wire.call('callSchemaComponentMethod', component.componentKey, method, params)
    }
    if (typeof Livewire !== 'undefined' && typeof Livewire.fireAction === 'function' && wire.__instance) {
        return await Livewire.fireAction(wire.__instance, 'callSchemaComponentMethod',
            [component.componentKey, method, params], { async: true })
    }
    throw new Error('Icon schema transport is unavailable.')
}

export function canCallIconPickerSchemaMethod(component) { return resolveIconPickerLivewire(component) !== null }

export function isIconPickerSearchPayload(payload) {
    return payload != null && typeof payload === 'object' && Array.isArray(payload.icons)
        && payload.icons.length <= 1000 && Number.isInteger(Number(payload.page)) && Number(payload.page) >= 1
        && [true, false, 0, 1, 'true', 'false', '0', '1'].includes(payload.hasMore)
}
