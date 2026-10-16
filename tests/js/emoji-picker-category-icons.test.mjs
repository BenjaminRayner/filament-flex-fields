import assert from 'node:assert/strict'
import test from 'node:test'

import {
    getCategoryIconGroupIds,
    getCategoryIconSvg,
    prepareIconSvg,
} from '../../resources/js/emoji/icons.js'

test('getCategoryIconGroupIds returns every emoji-picker-element nav group', () => {
    assert.deepEqual(getCategoryIconGroupIds(), [
        '-1',
        '0',
        '1',
        '3',
        '4',
        '5',
        '6',
        '7',
        '8',
        '9',
    ])
})

test('getCategoryIconSvg returns Hugeicons SVG markup for known groups', () => {
    const svg = getCategoryIconSvg(0)

    assert.match(svg, /<svg[^>]*viewBox="0 0 24 24"/)
    assert.match(svg, /stroke="currentColor"/)
})

test('prepareIconSvg adds the shared icon class and removes fixed dimensions', () => {
    const prepared = prepareIconSvg('<svg width="16" height="16" viewBox="0 0 16 16"><path /></svg>')

    assert.match(prepared, /class="fff-ui-icon"/)
    assert.doesNotMatch(prepared, /width="16"/)
    assert.doesNotMatch(prepared, /height="16"/)
})

test('category layout and indicator use the same equal-column geometry in static CSS', async () => {
    const { readFile } = await import('node:fs/promises')
    const styles = await readFile(new URL('../../resources/css/shadow/emoji-picker.css', import.meta.url), 'utf8')
    assert.match(styles, /grid-template-columns:\s*repeat\(var\(--num-groups\),minmax\(0,1fr\)\)/)
    assert.match(styles, /width:\s*calc\(100% \/ var\(--num-groups\)\)/)
    assert.match(styles, /--indicator-color: var\(--fff-emoji-primary, var\(--primary-500/)
    assert.match(styles, /\.nav-emoji \.fff-ui-icon/)
})
