/** Adapt real Pond status transitions to Filament's counted form-button protocol. */
export function bindFormProcessing({ owner, component, pond, message }) {
    const dispatch = component.dispatchFormEvent
    if (typeof dispatch !== 'function') return () => {}
    let processing = false
    const filtered = function (name, detail) {
        if (name === 'form-processing-started' || name === 'form-processing-finished') return
        return dispatch.call(this, name, detail)
    }
    component.dispatchFormEvent = filtered
    const refresh = () => {
        const active = pond.getFiles().some(file => [3, 9].includes(file.status))
        if (active === processing) return
        processing = active
        dispatch.call(component, active ? 'form-processing-started' : 'form-processing-finished', { message })
    }
    const events = ['processfilestart', 'processfile', 'processfileabort', 'removefile', 'updatefiles', 'error']
    events.forEach(event => pond.on(event, refresh))
    refresh()
    return owner.defer(() => {
        events.forEach(event => pond.off(event, refresh))
        if (processing) dispatch.call(component, 'form-processing-finished')
        processing = false
        if (component.dispatchFormEvent === filtered) component.dispatchFormEvent = dispatch
    })
}
