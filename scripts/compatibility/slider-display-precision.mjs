import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { configureSlider } from '../../resources/js/flex-slider/configuration.js'
import { displayPrecision, displayNumber, formatValue, snap } from '../../resources/js/flex-slider/value.js'
import { createSliderNumbers } from '../../resources/js/flex-slider/numbers.js'

const cases = [
    { step: null, value: 48.9213456544, expected: '49' },
    { step: null, value: -0.123456789, expected: '0' },
    { step: 1, value: 49, expected: '49' },
    { step: 0.1, value: 0.1 + 0.2, expected: '0.3' },
    { step: 0.125, value: 1.375, expected: '1.375' },
    { step: 1e-7, value: 0.0000003, expected: '0.0000003' },
    { step: null, decimalPlaces: 2, value: 48.9213456544, expected: '48.92' },
    { step: null, decimalPlaces: 2, value: 49, expected: '49.00' },
    { step: 0.25, decimalPlaces: 2, locale: 'de_DE', prefix: '€', suffix: ' / hour', value: 1250.5, expected: '€1.250,50 / hour' },
]
// Run the actual PHP presentation path, not a reimplementation of its policy.
const server = JSON.parse(execFileSync(process.env.FFF_PHP ?? 'php', ['-r', String.raw`
require 'vendor/autoload.php';
Orchestra\Testbench\Foundation\Application::create();
$results = [];
foreach (json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR) as $case) {
    $field = Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider::make('value')
        ->minValue(-2000)->maxValue(2000)->step($case['step'])
        ->decimalPlaces($case['decimalPlaces'] ?? null)->locale($case['locale'] ?? null)
        ->prefix($case['prefix'] ?? null)->suffix($case['suffix'] ?? null);
    $results[] = ['text' => $field->formatDisplayValue($case['value']), 'precision' => $field->getDisplayDecimalPlaces()];
}
echo json_encode($results, JSON_THROW_ON_ERROR);
`, JSON.stringify(cases)], { encoding: 'utf8' }))

for (const [index, fixture] of cases.entries()) {
    const config = configureSlider({ minValue: -2000, maxValue: 2000, ...fixture })
    assert.equal(formatValue(fixture.value, config), fixture.expected)
    assert.deepEqual(server[index], { text: fixture.expected, precision: displayPrecision(config) })
    const updates = [], flow = { update(value) { updates.push({ value, animated: this.animated }) } }
    const node = { dataset: { sliderNumber: '0' }, querySelector: () => flow, setAttribute() {} }
    let reduced = false
    const numbers = createSliderNumbers({ querySelectorAll: () => [node] }, config, () => reduced)
    numbers.update([fixture.value])
    numbers.update([fixture.value])
    assert.equal(updates.length, 1)
    assert.equal(updates[0].animated, false)
    const intl = new Intl.NumberFormat(flow.locales, flow.format)
    assert.equal((config.prefix ?? '') + intl.format(flow.value ?? updates[0].value) + (config.suffix ?? ''), fixture.expected)
    assert.equal(flow.format.maximumFractionDigits, displayPrecision(config))
    numbers.update([fixture.value + 1]); assert.equal(updates.at(-1).animated, true)
    reduced = true; numbers.update([fixture.value + 2]); assert.equal(updates.at(-1).animated, false)
    const count = updates.length; numbers.destroy(); numbers.update([fixture.value + 3]); assert.equal(updates.length, count)
}

const continuous = configureSlider({ minValue: 0, maxValue: 100, state: 48.9213456544 })
assert.equal(snap(48.9213456544, continuous), 48.9213456544)
assert.equal(displayNumber(snap(48.9213456544, continuous), continuous), 49)
assert.equal(formatValue(48.9213456544, { ...continuous, tooltips: { to: v => `Raw ${v}` } }, 0, true), 'Raw 48.9213456544')
let writes = 0
const flow = { update() { writes++ } }
const numbers = createSliderNumbers({ querySelectorAll: () => [{ dataset: { sliderNumber: '0' }, querySelector: () => flow, setAttribute() {} }] }, continuous, () => false)
for (let index = 0; index < 1000; index++) numbers.update([48.6 + index / 10000])
assert.equal(writes, 1, 'subpixel input inside one displayed number must not restart rolling digits')
numbers.destroy()
console.log(`PASS: ${cases.length} PHP/JS/NumberFlow format cases; continuous state, custom tooltips, animation, reduced motion, disposal and 1,000 repeated display updates.`)
