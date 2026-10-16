import fs from 'node:fs'
import path from 'node:path'
import crypto from 'node:crypto'

const root = path.resolve(import.meta.dirname, '../..')
const source = path.join(root, 'resources/images/country-flags')
const target = path.join(root, 'resources/dist/assets/country-flags')
const provenance = JSON.parse(fs.readFileSync(path.join(source, 'source.json'), 'utf8'))
const files = Object.entries(provenance.flags).map(([code, metadata]) => {
    if (!/^[A-Z]{2}$/.test(code)) throw new Error('Invalid country flag identity')
    const file = `${code.toLowerCase()}.svg`
    const data = fs.readFileSync(path.join(source, file))
    if (crypto.createHash('sha256').update(data).digest('hex') !== metadata.sha256) {
        throw new Error(`Country flag source differs from the pinned snapshot: ${code}`)
    }
    if (!data.toString('utf8').startsWith('<svg ')) throw new Error(`Unresolved flag alias: ${code}`)
    return file
})
fs.mkdirSync(target, { recursive: true })
for (const file of files) fs.copyFileSync(path.join(source, file), path.join(target, file))
for (const file of fs.readdirSync(target)) {
    if (!files.includes(file)) fs.unlinkSync(path.join(target, file))
}
console.log(`Built ${files.length} local country flags from the pinned upstream snapshot.`)
