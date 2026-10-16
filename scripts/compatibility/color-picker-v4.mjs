import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { parseColorValue, serializeColor } from '../../resources/js/color-picker/value.js'
import { createColorState } from '../../resources/js/color-picker/state.js'
import { createColorChannels } from '../../resources/js/color-picker/channels.js'
import { ringsCollide } from '../../resources/js/location/geometry/collisions.js'
import { snapPoint } from '../../resources/js/location/geometry/snapping.js'
import { displayDimensions } from '../../resources/js/location/geometry/units.js'
assert.equal(serializeColor(parseColorValue('hsl(120, 100%, 50%)')), '#00FF00')
assert.equal(serializeColor(parseColorValue('hsl(0, 100%, 50%)')), '#FF0000')
const values = ['#abc', '#AABBCCDD', '#oops', '#12345', 'rgb(256, 0, 0)', 'rgb(1, 2, 3, 0.5)', 'rgba(1, 2, 3)', 'rgba(1, 2, 3, 0.5)', 'hsl(120, 100%, 50%)', 'hsla(120, 100%, 50%, 0.25)', 'hsla(120, 500%, 50%, 0.5)', 'rgb(2.2, 3, 4)', 'url(bad)']
const php = spawnSync('php', ['-r', `require 'vendor/autoload.php'; foreach(json_decode(stream_get_contents(STDIN),true) as $value) echo \\Bjanczak\\FilamentFlexFields\\Support\\ColorPicker\\ColorValue::valid($value) ? "1" : "0";`], { input: JSON.stringify(values), encoding: 'utf8' })
assert.equal(php.status, 0, php.stderr); assert.equal(php.stdout, values.map(value => parseColorValue(value) ? '1' : '0').join(''))
const color = { $el: {}, state: '#FF0000', format: 'hex', alphaEnabled: false, readOnly: false, labels: { invalid: 'invalid' }, ...createColorState(), ...createColorChannels() }
color.syncExternalColor(color.state); color.valueInput = 'bad'; color.onValueInput(); assert.equal(color.state, '#FF0000'); assert.equal(color.colorError, 'invalid')
color.valueInput = '#12345678'; color.onValueInput(); assert.equal(color.state, '#123456'); assert.equal(color.hsva.a, 1)
color.inputFormat = 'hsl'; color.updateColorChannel('h', 120); assert.equal(color.hsva.h, 120);
color.inputFormat = 'rgb'; color.updateColorChannel('r', 255); assert.equal(color.displayColorChannels()[0].value, 255);
const beforeReadonly = color.state; color.readOnly = true; color.selectGridColor('#00FF00'); assert.equal(color.state, beforeReadonly)
const box = (w, s, e, n) => [[w, s], [e, s], [e, n], [w, n], [w, s]]
const a = box(0, 0, 2, 2), same = box(0, 0, 2, 2), touch = box(2, 0, 4, 2), overlap = box(1, 0, 3, 2), inside = box(.5, .5, 1, 1)
assert(ringsCollide(a, same)); assert(ringsCollide(a, overlap)); assert(ringsCollide(a, inside)); assert(!ringsCollide(a, touch)); assert(ringsCollide(a, touch, false)); assert(!ringsCollide(a, box(3, 0, 4, 1)))
const collisionPhp = spawnSync('php', ['-r', `require 'vendor/autoload.php'; [$a,$cases]=json_decode(stream_get_contents(STDIN),true); foreach($cases as [$b,$touch]) echo \\Bjanczak\\FilamentFlexFields\\Support\\Location\\ZoneCollision::rings($a,$b,$touch) ? "1" : "0";`], { input: JSON.stringify([a, [[same,true],[touch,true],[touch,false],[overlap,true],[inside,true]]]), encoding: 'utf8' })
assert.equal(collisionPhp.status, 0, collisionPhp.stderr); assert.equal(collisionPhp.stdout, '10111')
const map = { project: ([x,y]) => ({ x, y }), unproject: ([lng,lat]) => ({ lng, lat }) }, line = { geometry: { type:'LineString', coordinates:[[0,0],[10,0]] } }
assert.deepEqual(snapPoint(map,[5,2],[line],3), [5,0]); assert.deepEqual(snapPoint(map,[.2,.3],[line],1,{segments:false}), [0,0])
assert.equal(displayDimensions({radius:1852},'nautical').radius,1)
console.log('PASS: color conversion, bounded PHP/JS grammar parity, invalid-input preservation, alpha omission, readonly policy, zone collision/touching parity, magnetic segment/vertex snapping and unit conversion.')

const { createFlexDropdownRegistry } = await import('../../resources/js/overlay/ownership/controller-registry.js')
const controllers = createFlexDropdownRegistry()
let parentOpen = true, childOpen = false, siblingOpen = true
controllers.register('parent', () => ({ isOpen: () => parentOpen, close: () => { parentOpen = false } }))
controllers.register('child', () => ({ parentOwnerId: 'parent', isOpen: () => childOpen, close: () => { childOpen = false } }))
controllers.register('sibling', () => ({ isOpen: () => siblingOpen, close: () => { siblingOpen = false } }))
controllers.openExclusive('child'); childOpen = true
assert(parentOpen); assert(!siblingOpen)
controllers.openExclusive('sibling'); siblingOpen = true
assert(!parentOpen); assert(!childOpen)
const firstBundle = await import('../../resources/js/overlay/ownership/document-controllers.js')
const secondBundle = await import('../../resources/js/overlay/ownership/document-controllers.js?isolated-entry')
const doc = {}
assert.equal(firstBundle.documentDropdownControllers(doc), secondBundle.documentDropdownControllers(doc))
assert.equal(firstBundle.documentDropdownOwnership(doc).ids, secondBundle.documentDropdownOwnership(doc).ids)
assert.notEqual(firstBundle.documentDropdownControllers(doc), firstBundle.documentDropdownControllers({}))
Object.defineProperty(color, 'availableFormats', Object.getOwnPropertyDescriptor(createColorState(), 'availableFormats'));
color.readOnly = false; color.inputFormat = 'hsl'; color.allowedFormats = ['hsl', 'rgb']; color.selectInputFormat('hex'); assert.equal(color.inputFormat, 'hsl')
color.hsva = parseColorValue('hsl(219,58%,93%)'); color.updateSliderChannel('l', 50, true)
assert.equal(Math.round(color.sliderColorChannels()[2].value), 50); assert.equal(Math.round(color.sliderColorChannels()[1].value), 58)
color.selectInputFormat('rgb'); color.updateSliderChannel('r', 255, true); assert.equal(color.sliderColorChannels()[0].value, 255)
console.log('PASS: cross-bundle document ownership, nested parent retention, sibling displacement, format restrictions and HSL/RGB slider commits.')
const { resolveOwnedOverlayStack } = await import('../../resources/js/overlay/ownership/stacking.js')
const styleWrites = new Map()
const portal = { dataset: { fffDropdownOwner: 'parent' } }
const anchor = { closest: () => portal, ownerDocument: { defaultView: { getComputedStyle: () => ({ zIndex: '1200' }) } } }
const stack = resolveOwnedOverlayStack({ style: { setProperty: (key, value) => styleWrites.set(key, value) } }, anchor)
assert.equal(stack.panel, 1202); assert.equal(stack.backdrop, 1201); assert.equal(stack.ownerId, 'parent'); assert.equal(styleWrites.get('--fff-nested-overlay-z'), '1202')
assert.deepEqual(resolveOwnedOverlayStack({}, null), { panel: 60, backdrop: 50, nested: false })
console.log('PASS: nested mobile panel/dimmer stacking above the parent without changing ordinary overlay layers.')
const { resolveColorPalette } = await import('../../resources/js/color-picker/palette.js')
assert.deepEqual(resolveColorPalette({ layout: 'advanced', showSwatches: false }), [])
const preset = { layout: 'swatches' }
const palette = resolveColorPalette(preset)
assert.equal(palette.length, 9); palette[0] = '#000000'; assert.equal(resolveColorPalette(preset)[0], '#EF4444')
assert.equal(resolveColorPalette({ layout: 'grid', gridColumns: 17, gridRows: 11 }).length, 187)
console.log('PASS: no unused advanced/map palette allocation and no shared mutable swatch arrays.')
