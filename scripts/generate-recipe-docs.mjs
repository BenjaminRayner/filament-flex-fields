import { readFile, readdir, realpath, writeFile } from 'node:fs/promises'
import { dirname, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')

export async function renderRecipeBlocks(content, packageRoot = root) {
    const sourceRoot = await realpath(resolve(packageRoot, 'resources/recipes'))
    const pattern = /\{\/\* recipe-source: ([^\r\n]+) \*\/\}\n```(php|blade) ([^\r\n]+)\n[\s\S]*?^```\n\{\/\* \/recipe-source \*\/\}/gm
    const matches = [...content.matchAll(pattern)]
    if ((content.match(/recipe-source:/g) ?? []).length !== matches.length) throw new Error('Malformed recipe source block.')
    let result = content
    for (const match of matches.reverse()) {
        const [, path, language, label] = match
        if (!/^resources\/recipes\/[a-zA-Z0-9/_.-]+$/.test(path)) throw new Error('Invalid recipe source path.')
        const file = await realpath(resolve(packageRoot, path))
        if (!file.startsWith(sourceRoot + sep) || !file.endsWith('.php')) throw new Error('Recipe source is outside its directory.')
        const buffer = await readFile(file)
        const source = buffer.toString('utf8').trimEnd()
        if (buffer.length > 131072 || source.includes('\ufffd') || source.includes('\u0000') || /^\s*```/m.test(source)) throw new Error('Invalid recipe source.')
        const block = `{/* recipe-source: ${path} */}\n\`\`\`${language} ${label}\n${source}\n\`\`\`\n{/* /recipe-source */}`
        result = result.slice(0, match.index) + block + result.slice(match.index + match[0].length)
    }
    return result
}

async function main() {
    if (process.argv.slice(2).some(arg => arg !== '--check')) throw new Error('Usage: node scripts/generate-recipe-docs.mjs [--check]')
    const check = process.argv.includes('--check')
    const changes = []
    for (const entry of await readdir(resolve(root, 'docs'), { recursive: true })) {
        if (!/\.mdx?$/.test(entry)) continue
        const path = resolve(root, 'docs', entry)
        const before = await readFile(path, 'utf8')
        if (!before.includes('recipe-source:')) continue
        const after = await renderRecipeBlocks(before)
        if (before !== after) changes.push([path, after])
    }
    if (check && changes.length) throw new Error('Recipe documentation is stale. Run npm run docs:recipes.')
    for (const [path, content] of changes) await writeFile(path, content)
    console.log(check ? 'Recipe documentation matches shipped source.' : `Updated ${changes.length} recipe pages.`)
}

if (process.argv[1] && await realpath(process.argv[1]).catch(() => null) === fileURLToPath(import.meta.url)) {
    main().catch(error => { console.error(error.message); process.exitCode = 1 })
}
