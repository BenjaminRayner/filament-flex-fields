import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'

const root = path.resolve(import.meta.dirname, '../..')
const registry = JSON.parse(execFileSync('php', ['scripts/export-asset-registry.php'], { cwd: root, encoding: 'utf8' }))
assert.equal(registry.format, 4, 'Unsupported portable registry format')
const owners = new Map()
const walk = directory => fs.readdirSync(directory, { withFileTypes: true }).flatMap(item => item.isDirectory() ? walk(path.join(directory, item.name)) : [path.join(directory, item.name)])
const exclusions = new Set(['utilities-baseline.css', 'base.css'])
const visitedFor = (file, seen = new Set()) => {
    if (seen.has(file) || exclusions.has(path.basename(file))) return seen
    assert.ok(fs.existsSync(file), `Missing stylesheet source: ${file}`)
    seen.add(file)
    for (const match of fs.readFileSync(file, 'utf8').matchAll(/@import\s+["']([^"']+)["']/g)) {
        if (match[1].startsWith('.')) visitedFor(path.resolve(path.dirname(file), match[1]), seen)
    }
    return seen
}
for (const id of registry.lazy_stylesheets) {
    const entry = path.join(root, `resources/css/entries/${id}.css`)
    for (const source of visitedFor(entry)) {
        assert.ok(!owners.has(source), `CSS copied into both ${owners.get(source)} and ${id}: ${path.relative(root, source)}`)
        owners.set(source, id)
    }
}
const refs = new Set()
for (const file of walk(path.join(root, 'resources/views')).filter(file => file.endsWith('.blade.php'))) {
    for (const match of fs.readFileSync(file, 'utf8').matchAll(/'component'\s*=>\s*'([^']+)'/g)) refs.add(match[1])
}
for (const id of refs) assert.ok(registry.bundles[id] || registry.playground_aliases[id], `Blade component has no schema: ${id}`)
for (const [id, bundle] of Object.entries(registry.bundles)) {
    assert.ok(['field', 'display', 'column', 'entry', 'action', 'primitive'].includes(bundle.componentKind), `Missing typed schema: ${id}`)
    assert.equal(bundle.stylesheets.length, new Set(bundle.stylesheets).size, `Duplicate CSS demand: ${id}`)
    assert.equal(bundle.chunks.length, new Set(bundle.chunks).size, `Duplicate JS demand: ${id}`)
    for (const [references, type] of [[bundle.stylesheets, 'stylesheet'], [bundle.chunks, 'module'], [bundle.ssrScripts, 'script']]) {
        for (const reference of references) assert.equal(registry.resources[reference]?.type, type, `Missing or wrong resource type: ${id} -> ${reference}`)
    }
    if (bundle.entry) assert.equal(registry.resources[bundle.entry]?.type, 'module', `Wrong entry type: ${id}`)
}
console.log(`PASS: ${Object.keys(registry.bundles).length} typed schemas, ${refs.size} literal Blade boundaries and ${owners.size} uniquely owned CSS source files.`)
