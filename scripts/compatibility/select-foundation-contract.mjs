import { assertSelectRuntimePolicy } from '../../resources/js/select/configuration/runtime-policy.js'
import { createSelectionCommandsMixin } from '../../resources/js/select/adapters/alpine/selection-commands.js'
import assert from 'node:assert/strict'
import { normalizeSelectPage, mergeSelectOptionPages } from '../../resources/js/select/async/page-publication.js'
import { flattenHeadlessOptions } from '../../resources/js/select/options/option-tree.js'
import { createComboboxEngine } from '../../resources/js/select/engine/combobox-engine.js'
import { createVirtualWindowSession } from '../../resources/js/select/virtualization/virtual-window-session.js'
import { createVirtualWindowMixin } from '../../resources/js/select/adapters/virtual/window.js'
import { createSelectSearchLoaderMixin } from '../../resources/js/select/adapters/livewire/search-loader.js'
import { createRequestCoordinator } from '../../resources/js/select/async/request-coordinator.js'
import { createSelectMenuRequestsMixin } from '../../resources/js/select/adapters/livewire/menu-requests.js'

const group = (label, options) => ({label, options})
const row = value => ({value, label: `Option ${value}`})
const first = [group('Europe', [group('West', [row('FR')]), row('PL')])]
const second = [group('Europe', [group('West', [row('FR'), row('NL')]), row('DE')]), group('Asia', [row('JP')])]
const before = JSON.stringify([first, second])
const merged = mergeSelectOptionPages(first, second)
assert.deepEqual(flattenHeadlessOptions(merged).map(x => x.value), ['FR', 'NL', 'PL', 'DE', 'JP'])
assert.equal(merged[0].options[0].options.length, 2)
assert.equal(JSON.stringify([first, second]), before, 'Providers must never be mutated')
assert.throws(() => normalizeSelectPage({items: [], hasMore: true, cursor: 'a'}, {visitedCursors: new Set(['a'])}))
assert.throws(() => normalizeSelectPage({items: [row('a')], hasMore: false}, {maxBytes: 8}))
assert.throws(() => normalizeSelectPage({items: [{value: NaN}], hasMore: false}))
const cyclic = []; cyclic.push(group('cycle', cyclic))
assert.throws(() => mergeSelectOptionPages([], cyclic))

// A real TanStack session stays bounded at arbitrary offsets; variable-height revisions invalidate it.
const session = createVirtualWindowSession()
const params = {revision: 1, count: 10000, scrollTop: 0, viewportHeight: 256, viewportWidth: 320, getItemKey: i => i, estimateSize: () => 36, overscan: 5}
for (const scrollTop of [0, 1000, 45000, 359500]) {
    const view = session.read({...params, scrollTop})
    assert(view.indexes.length <= 20)
    assert.equal(view.totalSize, 360000)
    assert(view.paddingTop >= 0 && view.paddingBottom >= 0)
}
assert.equal(session.read({...params, revision: 2, estimateSize: i => i === 0 ? 80 : 36}).totalSize, 360044)
session.dispose(); assert.throws(() => session.read(params))

const mobileRows = Array.from({length: 96}, (_, index) => ({key: String(index), type: 'option', height: 36}))
const resized = {...createVirtualWindowMixin({HEADLESS_SELECT_VIRTUAL_OVERSCAN: 10}),
    _virtualRowsRevision: 1, _virtualMeasurementVersion: 1, _virtualViewportHeight: 258,
    _virtualOptionMinHeight: 44, _virtualMeasuredHeights: new Map(mobileRows.map(row => [row.key, 36])),
    $refs: {headlessOptionsList: {clientWidth: 320}}, isGridLayout: false}
const mobileWindow = resized.resolveVirtualWindowForFlatRows(mobileRows, 0)
assert.equal(mobileWindow.totalSize, 96 * 44, 'A delayed desktop observation cannot override mobile touch geometry')
assert.equal(resized.virtualOffsetForFlatRowIndex(95, 'end'), 96 * 44 - 258)
resized._virtualWindowSession.dispose()
const resizingSession = createVirtualWindowSession()
resizingSession.read({...params, count: 96, scrollTop: 3232, viewportHeight: 224})
assert.equal(resizingSession.captureScrollAnchor().atEnd, true)
resizingSession.read({...params, revision: 2, count: 96, scrollTop: 3232, viewportHeight: 258, estimateSize: () => 44})
assert.equal(resizingSession.offsetForIndex(95, 'end'), 3966)
resizingSession.dispose()

// Integrate real page loader, request coordinator and engine under concurrent query/page completions.
const pending = []
const engine = createComboboxEngine({options: [row('selected')], multiple: true, initialSelectedValues: ['selected']})
const runtime = {
    ...createSelectSearchLoaderMixin(), _selectSearchRequests: createRequestCoordinator(),
    hasDynamicSearchResults: true, hasPaginatedSearchResults: true, comboboxOpen: true,
    comboboxQuery: 'old', options: [row('selected')], maxPageBytes: 1048576,
    callSchemaMethod(_method, args) { return new Promise(resolve => pending.push({args, resolve})) },
    applyRemoteOptions(options) { this.options = options; engine.setOptions(flattenHeadlessOptions(options)) },
    populateLabelRepositoryFromOptions() {}, captureSelectRequestFailure() {},
    $nextTick(callback) { callback() },
}
const old = runtime.fetchSearchResults('old')
runtime.comboboxQuery = 'new'
const fresh = runtime.fetchSearchResults('new')
pending[1].resolve({items: first, hasMore: true, cursor: 'next'})
await fresh
pending[0].resolve({items: [row('STALE')], hasMore: false})
await old
assert.deepEqual(runtime.options, first)
assert.deepEqual([...engine.getSnapshot().selectedValues], ['selected'])
const page = runtime.loadMoreSearchResults()
pending[2].resolve({items: second, hasMore: true, cursor: 'last'})
await page
assert.deepEqual(runtime.options, merged)
const retryCycle = runtime.loadMoreSearchResults()
pending[3].resolve({items: [row('SHOULD_NOT_PUBLISH')], hasMore: true, cursor: 'next'})
await retryCycle
assert(runtime.searchError)
assert.deepEqual(runtime.options, merged)
assert.equal(runtime.searchResultsCursor, 'last')
runtime.searchError = null
const removedOwner = runtime.loadMoreSearchResults()
runtime._selectDisposed = true
runtime._selectSearchRequests.dispose()
pending[4].resolve({items: [row('REMOVED')], hasMore: false})
await removedOwner
assert.deepEqual(runtime.options, merged)
engine.destroy()
console.log('PASS: immutable nested grouped pages, global deduplication, cursor-cycle/payload rejection, actual TanStack 10,000-row bounds, measurements, stale response suppression, selection preservation, failed-page retention and disposed owners.')

for (const invalid of [{virtualWindowSize: 100000}, {virtualRowHeight: NaN}, {disabled: 'false'}, {searchDebounce: -1}, {hasPaginatedSearchResults: true}, {onChange: 'execute'}]) assert.throws(() => assertSelectRuntimePolicy(invalid))
assert.doesNotThrow(() => assertSelectRuntimePolicy({maxItems: 0, hasDynamicSearchResults: true, hasPaginatedSearchResults: true}))
assert.equal(createSelectionCommandsMixin().hasReachedMaxItems.call({maxItems: 0, comboboxSelectedValues: []}), true)
console.log('PASS: public composition policy bounds and a zero-item selection limit.')

// Dependency selection is reconciled locally before any server completion.
const { createDependencyInvalidationMixin } = await import('../../resources/js/select/adapters/dependency-invalidation.js')
const { createSelectEngineBindingMixin } = await import('../../resources/js/select/adapters/alpine/engine-binding.js')
const { createHeadlessComboboxEngineSyncMixin } = await import('../../resources/js/components/select-field/headless-combobox-engine-sync.js')
for (const multiple of [false, true]) {
    for (const resetOnDependencyChange of [false, true]) {
        const watchers = new Map()
        let notifications = 0
        const child = {
            ...createDependencyInvalidationMixin(),
            ...createSelectEngineBindingMixin({ engineConfig: {}, userOnChange: null }),
            ...createHeadlessComboboxEngineSyncMixin(),
            _selectDisposed: false, _userHasMutatedSelection: false,
            dependencyStatePaths: ['form.country', 'form.service'], resetOnDependencyChange,
            state: multiple ? ['old'] : 'old', multiple, comboboxSelectedValues: ['old'],
            comboboxOpen: false, clearable: false,
            $watch(path, callback) { watchers.set(path, callback) },
            syncClearablePresentation() {}, syncInlineSearchInputAfterClose() {},
            invalidateDynamicOptionsCache() {},
            _engine: createComboboxEngine({ options: [row('old')], multiple,
                initialSelectedValues: ['old'], onChange() { notifications++ } }),
        }
        child.bindSelectDependencies()
        child.bindSelectDependencies()
        assert.equal(watchers.size, 2)
        assert.deepEqual(child.comboboxSelectedValues, ['old'], 'Binding must not clear a hydrated selection')
        const parentChanged = [...watchers.values()][0]
        parentChanged()
        assert.deepEqual(child.comboboxSelectedValues, resetOnDependencyChange ? [] : ['old'])
        assert.deepEqual(child.state, resetOnDependencyChange ? (multiple ? [] : null) : (multiple ? ['old'] : 'old'))
        assert.equal(child._userHasMutatedSelection, resetOnDependencyChange, 'An intentional empty state must not revive its SSR selection')
        assert.equal(notifications, 0, 'Dependency reconciliation must not run the user-command callback')
        parentChanged()
        child._selectDisposed = true
        child.state = 'unchanged-after-disposal'
        parentChanged()
        assert.equal(child.state, 'unchanged-after-disposal')
        child._engine.destroy()
    }
}
assert.throws(() => assertSelectRuntimePolicy({ resetOnDependencyChange: 'false' }))
console.log('PASS: opt-in dependency reset is immediate, supports single/multiple and non-clearable fields, preserves hydration and ignores disposed owners.')

// Closing resets page cursors and the visible query, but retains painted rows.
// Opening must restore a first page even when an earlier search left rows behind.
const reopenedQueries = []
const reopened = {
    ...createSelectMenuRequestsMixin(),
    _selectOpenEffectsGeneration: 1, _selectDisposed: false, disabled: false,
    comboboxOpen: true, comboboxQuery: '', hasDynamicOptions: false,
    hasDynamicSearchResults: true, hasPaginatedSearchResults: true,
    isPreloaded: false, flatOptions: [row('previous-query-result')],
    minSearchLength: 0, searchPending: false,
    fetchSearchResults: async (...args) => reopenedQueries.push(args),
    hasMissingSelectedLabels: () => false,
}
await reopened.onHeadlessMenuOpenedForLivewire()
assert.deepEqual(reopenedQueries, [['', '', {append: false}]])
reopened.comboboxQuery = 'New query'
await reopened.onHeadlessMenuOpenedForLivewire()
assert.deepEqual(reopenedQueries[1], ['New query', 'New query', {append: false}])
for (const policy of [{searchPending: true}, {disabled: true}, {_selectDisposed: true}, {comboboxOpen: false}, {comboboxQuery: '', minSearchLength: 2}]) {
    await {...reopened, ...policy}.onHeadlessMenuOpenedForLivewire()
}
assert.equal(reopenedQueries.length, 2, 'Closed/disposed/disabled/pending/min-length owners must not start another page request')
console.log('PASS: paginated reopen restores the current-query first page without losing owner or min-search guards.')

// Match Alpine's merged-scope setter: new keys otherwise leak into the last parent.
const { publishSelectCapabilities } = await import('../../resources/js/select/adapters/alpine/capability-owner.js')
const parent = {}, firstOwner = {}, secondOwner = {}
const scope = owner => new Proxy(owner, {get(target, key) {return key === '_pickerHostElement' ? {_x_dataStack: [owner, parent]} : target[key] ?? parent[key]},
    set(target, key, value) { (Object.hasOwn(target, key) ? target : parent)[key] = value; return true }})
publishSelectCapabilities(scope(firstOwner), {_selectSearchRequests: createRequestCoordinator()})
publishSelectCapabilities(scope(secondOwner), {_selectSearchRequests: createRequestCoordinator()})
assert(!Object.hasOwn(parent, '_selectSearchRequests'))
firstOwner._selectSearchRequests.dispose()
assert.equal(secondOwner._selectSearchRequests.disposed, false)
assert(secondOwner._selectSearchRequests.begin('search'))
console.log('PASS: lazy adapter publication stays on each Alpine owner; disposal cannot poison sibling request coordinators.')

const {readFileSync, readdirSync} = await import('node:fs')
const {default: headlessComboboxAlpine} = await import('../../resources/js/components/select-field/headless-combobox-alpine.js')
const {createHeadlessComboboxLivewireMixin} = await import('../../resources/js/components/select-field/headless-combobox-livewire.js')
const local = {...headlessComboboxAlpine(), ...createHeadlessComboboxLivewireMixin()}
for (const directory of ['components/select-field', 'select/adapters/alpine', 'select/adapters/livewire', 'select/adapters/surface', 'select/adapters/virtual', 'select/adapters/layout']) {
    const root = new URL(`../../resources/js/${directory}/`, import.meta.url)
    for (const name of readdirSync(root).filter(name => name.endsWith('.js'))) {
        const source = readFileSync(new URL(name, root), 'utf8')
        for (const match of source.matchAll(/this\.([\w$]+)\s*(?:\?\?=|\|\|=|\+\+|--|\+=|-=|=(?!=|>))/g)) {
            assert(Object.hasOwn(local, match[1]), `${directory}/${name} writes undeclared owner state ${match[1]}`)
        }
    }
}
const dormant = headlessComboboxAlpine()
for (const key of ['_selectSearchRequests', '_selectOptionsRequests', '_selectLabelRequests', '_relationshipAdapter']) {
    assert(Object.hasOwn(dormant, key)); assert.equal(dormant[key], null)
}
console.log('PASS: Select runtime assignments have explicit local slots; dormant capabilities mask ancestor resources.')
