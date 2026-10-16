import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'

const css = readFileSync(new URL('../../resources/css/components/flex-file-upload.css', import.meta.url), 'utf8')
for (const size of [96, 128, 160]) test(`avatar ${size}px contains every visible action inside its circular footprint`, () => {
    const unit = size / 8, radius = size / 2
    // Same proportions as native 128px circle, independently resolved per instance.
    for (const [diameter, bottom, offset] of [[unit * 1.625, unit * 1.45, unit * 3 - unit * 1.625 / 2], [unit * 2, unit * .375, 0]]) {
        const y = radius - bottom - diameter / 2
        assert.ok(Math.hypot(offset, y) + diameter / 2 < radius - 2, 'Complete circular button remains inside the image, including its focus ring')
    }
    assert.match(css, /--fff-avatar-unit: calc\(var\(--fff-upload-avatar-size,128px\) \/ 8\)/)
})
