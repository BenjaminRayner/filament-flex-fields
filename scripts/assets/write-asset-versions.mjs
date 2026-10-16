import fs from 'node:fs'
import path from 'node:path'
import crypto from 'node:crypto'

const packageRoot = path.resolve(import.meta.dirname, '../..')
const dist = path.join(packageRoot, 'resources/dist')
const manifest = JSON.parse(fs.readFileSync(path.join(dist, 'components/alpine-manifest.json'), 'utf8'))
const activeComponents = new Set([
    ...(manifest.__entries__ ?? []).map((entry) => `${entry}.js`),
    ...(manifest.__shared_chunks__ ?? []),
])
const files = []
const collectResources = (directory) => {
    const current = path.join(dist, directory)
    if (!fs.existsSync(current)) return
    for (const file of fs.readdirSync(current, { withFileTypes: true })) {
        const name = `${directory}/${file.name}`
        if (file.isDirectory()) collectResources(name)
        else if (file.isFile()) files.push(name)
    }
}
collectResources('audio')
collectResources('assets')
for (const directory of ['components', 'core', 'support', 'css', 'playground']) {
    const root = path.join(dist, directory)
    if (!fs.existsSync(root)) continue
    for (const name of fs.readdirSync(root).sort()) {
        if (!/\.(?:js|css)$/.test(name) || (directory === 'components' && !activeComponents.has(name))) continue
        files.push(`${directory}/${name}`)
    }
}
const assets = Object.fromEntries(files.sort().map((name) => [name, crypto.createHash('sha256').update(fs.readFileSync(path.join(dist, name))).digest('hex')]))
const bytes = Object.fromEntries(files.map((name) => [name, fs.statSync(path.join(dist, name)).size]))
// One deterministic seal binds PHP projection, eager/dynamic JS graph and CSS bytes.
// It is output parity, not a claim that the whole legacy deployment is atomic.
const metadata = Object.fromEntries([
    'resources/dist/components/alpine-manifest.json',
    'src/Support/Assets/BuiltInComponentIndex.php',
].map((name) => [name, crypto.createHash('sha256').update(fs.readFileSync(path.join(packageRoot, name))).digest('hex')]))
const release = crypto.createHash('sha256').update(JSON.stringify({ assets, bytes, metadata })).digest('hex')
const target = path.join(dist, 'asset-versions.json')
const temporary = `${target}.${process.pid}.tmp`
try {
    fs.writeFileSync(temporary, `${JSON.stringify({ format: 2, release, assets, bytes, metadata }, null, 2)}\n`)
    fs.renameSync(temporary, target)
} finally {
    if (fs.existsSync(temporary)) fs.unlinkSync(temporary)
}
console.log(`Versioned ${files.length} asset files by content.`)
