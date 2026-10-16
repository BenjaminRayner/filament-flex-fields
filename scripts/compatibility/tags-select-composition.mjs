import assert from 'node:assert/strict'
import {readFileSync} from 'node:fs'
import {createTagsController} from '../../resources/js/tags/controller.js'
import {acceptTag, splitTagPaste} from '../../resources/js/tags/value-policy.js'
const row = value => ({value, label: value})
const tags = createTagsController({select: {state: ['old'], initialState: ['old'], options: [row('old'), row('new')], maxItems: 3}, duplicateInsensitive: true})
// Exercise the actual shared engine and callbacks without emulating DOM mounting.
tags.$refs = {}; tags.$nextTick = fn => fn?.(); tags.queueSelectLayoutRefresh = () => {}
tags.comboboxCloseMenu = () => { tags.comboboxOpen = false }; tags.syncClearablePresentation = () => {}
tags.queueOptionCheckEnter = () => {}; tags.comboboxSetQuery = function(value) {this.comboboxQuery = value; this._engine.setQuery(value)}
tags.createSelectEngine(['old']); tags._syncFromEngine()
assert.equal(tags.addTag('OLD'), false)
assert.equal(tags.addTag('new'), true)
assert.equal(tags.addTag('free'), true)
assert.deepEqual(tags.state, ['old', 'new', 'free'])
assert.equal(tags.addTag('fourth'), false)
tags.deleteTag('new'); assert.deepEqual(tags.state, ['old', 'free'])
tags.isReorderable = true; tags.reorderTags({oldIndex: 1, newIndex: 0})
assert.deepEqual(tags.state, ['free', 'old'])
tags.disabled = true; assert.equal(tags.addTag('no'), false)
tags.disabled = false; tags._selectDisposed = true; assert.equal(tags.addTag('removed'), false)
tags._engine.destroy()
assert.equal(acceptTag('free', {selected: [], options: [row('allowed')], suggestionsOnly: true}), null)
assert.equal(acceptTag('ALLOWED', {selected: [], options: [row('allowed')], suggestionsOnly: true, insensitive: true}), 'allowed')
assert.equal(acceptTag('x', {selected: [], options: [], maximum: 0}), null)
assert.deepEqual(splitTagPaste('one,two;three', [',', ';', 'Tab']), ['one', 'two', 'three'])
assert.deepEqual(splitTagPaste('one two', ['Tab']), ['one two'])
const view = readFileSync(new URL('../../resources/views/forms/components/tags-field.blade.php', import.meta.url), 'utf8')
assert(view.includes('::select-menu'))
assert(!view.includes('x-teleport'))
assert(!view.includes('modulepreload'))
const schema = readFileSync(new URL('../../resources/components/tags-field.php', import.meta.url), 'utf8')
assert(schema.includes("uses: ['select-field']"))
console.log('PASS: Tags executes the real Select engine; creation, suggestions, duplicate case policy, maximum, removal, reorder, disabled/disposed owners, paste and a single shared menu contract.')
const {acceptTagBatch} = await import('../../resources/js/tags/value-policy.js')
assert.deepEqual(splitTagPaste('a\tb\nc', ['Tab', 'Enter']), ['a', 'b', 'c'])
assert.deepEqual(acceptTagBatch(['OLD', 'new', 'new', 'free', 'extra'], {selected:['old'], options:[row('new')], maximum:3, insensitive:true}), ['old','new','free'])
assert.equal(acceptTagBatch(Array.from({length:10000}, (_,i)=>`tag-${i}`), {selected:[], options:[]}).length,10000)
assert.equal(acceptTag('😀'.repeat(20000), {selected:[], options:[]}), null)
assert(view.includes("array_diff_key($selectConfiguration, array_flip(['initialState', 'state']))"))
console.log('PASS: bounded linear clipboard batches, semantic paste separators, UTF-8 byte limits and stable structural identity independent of selected state.')
