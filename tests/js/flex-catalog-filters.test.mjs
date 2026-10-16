import assert from 'node:assert/strict'
import { test } from 'node:test'
import catalog from '../../resources/js/components/flex-catalog.js'

const entries = [
    { id: 'rating-field', categories: ['controls'], kinds: ['field'], installed: true, official: true, search: 'Rating field Ocena' },
    { id: 'rating-column', categories: ['display'], kinds: ['column'], installed: true, official: true, search: 'Rating column Ocena' },
    { id: 'cover-card', categories: ['collections'], kinds: ['display'], installed: true, official: true, search: 'Cover card Łódź' },
    { id: 'confirm', categories: ['actions'], kinds: ['action'], installed: true, official: true, search: 'Hold confirm' },
    { id: 'external-rating', categories: ['controls'], kinds: ['field', 'column'], installed: false, official: false, search: 'ACME Rating' },
    { id: 'installed-column', categories: ['display'], kinds: ['column'], installed: true, official: false, search: 'Community column' },
    { id: 'unknown-plugin', categories: ['controls'], kinds: [], installed: false, official: false, search: 'Unknown plugin' },
]

test('type selection distinguishes actual field, column, content and action surfaces', () => {
    const vm = catalog({ entries })
    const expectations = {
        field: ['rating-field', 'external-rating'],
        column: ['rating-column', 'external-rating', 'installed-column'],
        display: ['cover-card'],
        action: ['confirm'],
        entry: [],
    }
    for (const [kind, ids] of Object.entries(expectations)) {
        vm.componentKind = kind
        assert.deepEqual([...vm.matchingIds()], ids)
        assert.equal(vm.resultCount, ids.length)
        assert.equal(vm.isFiltering, true)
    }
})

test('collection and component-type filters intersect', () => {
    const vm = catalog({ entries })
    vm.componentKind = 'column'
    vm.collection = 'installed'
    assert.deepEqual([...vm.matchingIds()], ['rating-column', 'installed-column'])
    vm.collection = 'community'
    assert.deepEqual([...vm.matchingIds()], ['external-rating', 'installed-column'])
    vm.componentKind = 'field'
    assert.deepEqual([...vm.matchingIds()], ['external-rating'])
    vm.componentKind = 'all'
    assert.deepEqual([...vm.matchingIds()], ['external-rating', 'installed-column', 'unknown-plugin'])
})

test('text, category, collection and kind filters combine without admitting another surface', () => {
    const vm = catalog({ entries })
    vm.query = 'rating'
    vm.componentKind = 'column'
    vm.collection = 'installed'
    assert.deepEqual([...vm.matchingIds()], ['rating-column'])
    vm.category = 'controls'
    assert.equal(vm.resultCount, 0)
    vm.collection = 'community'
    assert.deepEqual([...vm.matchingIds()], ['external-rating'])
    assert.equal(vm.visible('rating-field'), false)
    assert.equal(vm.groupVisible(['rating-field', 'external-rating']), true)
})

test('reset restores all filter dimensions and unclassified recommendations', () => {
    const vm = catalog({ entries })
    vm.query = 'nonsense'
    vm.category = 'controls'
    vm.collection = 'community'
    vm.componentKind = 'column'
    assert.equal(vm.resultCount, 0)
    vm.resetFilters()
    assert.equal(vm.query, '')
    assert.equal(vm.category, 'all')
    assert.equal(vm.collection, 'all')
    assert.equal(vm.componentKind, 'all')
    assert.equal(vm.isFiltering, false)
    assert.equal(vm.resultCount, entries.length)
    assert.equal(vm.visible('unknown-plugin'), true)
})

test('search normalizes localized names while keeping the kind restriction', () => {
    const vm = catalog({ entries })
    vm.query = 'LODZ'
    vm.componentKind = 'display'
    assert.deepEqual([...vm.matchingIds()], ['cover-card'])
    vm.componentKind = 'field'
    assert.equal(vm.resultCount, 0)
})

test('all card and group reads reuse one result set until a filter changes', () => {
    const vm = catalog({ entries })
    const initial = vm.matchingIds()
    for (let i = 0; i < 1000; i++) {
        vm.visible('cover-card')
        vm.groupVisible(['cover-card', 'rating-field'])
        assert.equal(vm.matchingIds(), initial)
    }
    vm.componentKind = 'column'
    const columns = vm.matchingIds()
    assert.notEqual(columns, initial)
    assert.equal(vm.matchingIds(), columns)
})

test('index snapshots kind and category arrays rather than external mutable aliases', () => {
    const source = { id: 'one', kinds: ['field'], categories: ['controls'], search: 'One', official: true, installed: true }
    const vm = catalog({ entries: [source] })
    source.kinds[0] = 'column'
    source.categories[0] = 'display'
    vm.componentKind = 'field'
    vm.category = 'controls'
    assert.equal(vm.visible('one'), true)
})

test('older unclassified metadata remains visible without guessing a kind', () => {
    const vm = catalog({ entries: [{ id: 'legacy', categories: [], search: 'Legacy' }] })
    assert.equal(vm.visible('legacy'), true)
    vm.componentKind = 'field'
    assert.equal(vm.visible('legacy'), false)
})
