import { test } from 'node:test'
import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import { trackNativeRequests } from '../../scripts/core/native-request-drain.mjs'

const request = name => ({ url: () => 'https://fixture.test/' + name })
function fixture() {
    const page = new EventEmitter()
    page.waitForLoadState = async state => assert.equal(state, 'load')
    return page
}

test('a previously reached document load state cannot bypass outstanding SPA requests', async () => {
    const page = fixture(), tracker = trackNativeRequests(page)
    const first = request('first.js'), second = request('second.js')
    page.emit('request', first); page.emit('request', second)
    let drained = false
    const waiting = tracker.drain().then(() => { drained = true })
    await Promise.resolve()
    page.emit('requestfinished', first)
    await Promise.resolve()
    assert.equal(drained, false)
    const late = request('dependency.js')
    page.emit('request', late)
    page.emit('requestfinished', second)
    await Promise.resolve()
    assert.equal(drained, false)
    page.emit('requestfinished', late)
    await waiting
    assert.equal(drained, true)
    tracker.dispose()
    for (const event of ['request', 'requestfinished', 'requestfailed']) assert.equal(page.listenerCount(event), 0)
})

test('draining does not suppress genuine request failures observed by the audit', async () => {
    const page = fixture(), failures = []
    page.on('requestfailed', request => failures.push(request.url()))
    const tracker = trackNativeRequests(page), failed = request('broken.js')
    page.emit('request', failed)
    const waiting = tracker.drain()
    await Promise.resolve()
    page.emit('requestfailed', failed)
    await waiting
    assert.deepEqual(failures, [failed.url()])
    tracker.dispose()
    assert.equal(page.listenerCount('requestfailed'), 1)
})

test('a stuck request fails with its URL instead of bypassing the audit', async () => {
    const page = fixture(), tracker = trackNativeRequests(page, { timeout: 10 })
    page.emit('request', request('stuck.js'))
    await assert.rejects(tracker.drain(), /requests did not settle: https:\/\/fixture.test\/stuck.js/)
    tracker.dispose()
})
