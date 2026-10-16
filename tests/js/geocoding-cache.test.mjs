import assert from 'node:assert/strict'
import test from 'node:test'

import { createGeocodingResultCache } from '../../resources/js/select/geocoding/result-cache.js'

test('writeGeocodingCache stores and reads values', () => {
    const cache = createGeocodingResultCache()

    const key = 'reverse:52.1,21.0'

    cache.set(key, [{ id: 'place-1' }])

    assert.deepEqual(cache.get(key), [{ id: 'place-1' }])
})

test('writeGeocodingCache evicts oldest entry when max entries exceeded', () => {
    const cache = createGeocodingResultCache()

    cache.set('first', 'a', 2)
    cache.set('second', 'b', 2)
    cache.set('third', 'c', 2)

    assert.equal(cache.get('first'), null)
    assert.equal(cache.get('second'), 'b')
    assert.equal(cache.get('third'), 'c')
})
