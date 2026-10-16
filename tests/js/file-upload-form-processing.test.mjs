import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { EventEmitter } from 'node:events'
import { test } from 'node:test'
import { createMediaOwner } from '../../resources/js/media/lifecycle/owner.js'
import { bindFormProcessing } from '../../resources/js/media/upload/form-processing.js'

const nativeSource = readFileSync(new URL('../../vendor/filament/support/resources/js/components/form-button.js', import.meta.url), 'utf8')
const { default: nativeFormButton } = await import(`data:text/javascript;base64,${Buffer.from(nativeSource).toString('base64')}`)
test('real Filament button counts simultaneous fields, manual processing, failure, cancellation and disposal', () => {
    const form = new EventTarget(), button = nativeFormButton()
    button.$el = { closest: () => form }; button.init()
    const instances = [0, 1].map(() => {
        const owner = createMediaOwner(), events = new EventEmitter(), files = []
        const component = { dispatchFormEvent(name, detail = {}) { const event = new Event(name); event.detail = detail; form.dispatchEvent(event) } }
        const original = component.dispatchFormEvent
        const pond = { getFiles: () => files, on: events.on.bind(events), off: events.off.bind(events) }
        const stop = bindFormProcessing({ owner, component, pond, message: 'Uploading photos…' })
        return { owner, events, files, component, stop, original }
    })
    const [first, second] = instances
    first.files.push({ status: 3 }); first.events.emit('processfilestart')
    first.component.dispatchFormEvent('form-processing-started') // native duplicate is suppressed
    assert.equal(button.processingCount, 1); assert.equal(button.processingMessage, 'Uploading photos…')
    second.files.push({ status: 3 }); second.events.emit('processfilestart')
    assert.equal(button.processingCount, 2)
    first.files[0].status = 6; first.events.emit('processfile')
    assert.equal(button.isProcessing, true); assert.equal(button.processingCount, 1)
    second.files[0].status = 2; second.events.emit('processfileabort')
    assert.equal(button.isProcessing, false)
    first.files[0].status = 9; first.events.emit('updatefiles')
    first.owner.dispose()
    assert.equal(button.isProcessing, false); assert.equal(first.component.dispatchFormEvent, first.original)
    assert.equal(first.events.eventNames().length, 0)
    second.owner.dispose()
})
