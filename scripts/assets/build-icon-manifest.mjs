import { readdir, mkdir, writeFile, readFile } from 'node:fs/promises'
import { createHash } from 'node:crypto'
import { fileURLToPath } from 'node:url'

// Resolve the one Composer-owned icon source at build time, never on a page request.
const root = fileURLToPath(new URL('../../', import.meta.url))
const directory = `${root}vendor/janczakb/blade-hugeicons/resources/svg`
const icons = (await readdir(directory)).filter(name => /^[a-z0-9-]+\.svg$/.test(name))
    .map(name => `hugeicons-${name.slice(0, -4)}`).sort()
if (!icons.length) throw new Error('Install blade-hugeicons before building FLEX assets.')
await mkdir(`${root}resources/dist`, { recursive: true })
await writeFile(`${root}resources/dist/icon-catalog-manifest.json`, `${JSON.stringify({ hugeicons: { prefix: 'hugeicons', label: 'Hugeicons', icons } })}\n`)
// The sealed dist contains a content identity, not thousands of source files.
// Runtime cache invalidation never scans SVG directories or trusts mtime/size.
const sets = []
for (const [name, source] of [['hugeicons', directory], ['flexhugeicons', `${root}resources/svg/hugeicons`], ['flexvideojs', `${root}resources/svg/videojs`]]) {
    const hash = createHash('sha256')
    const files = (await readdir(source)).filter(file => /^[a-z0-9-]+\.svg$/.test(file)).sort()
    for (const file of files) {
        const bytes = await readFile(`${source}/${file}`)
        hash.update(`${file}\0${bytes.length}\0`).update(bytes)
    }
    sets.push({ name, files: files.length, sha256: hash.digest('hex') })
}
await mkdir(`${root}resources/dist/assets/icons`, { recursive: true })
await writeFile(`${root}resources/dist/assets/icons/source-revision.json`, `${JSON.stringify({ format: 1, sets })}\n`)
console.log(`Compiled ${icons.length} Hugeicons catalog entries.`)
