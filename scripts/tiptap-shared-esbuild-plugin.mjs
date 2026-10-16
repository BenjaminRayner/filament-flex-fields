import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const tiptapSharedKeys = {
    '@tiptap/core': 'core',
    '@tiptap/pm/state': 'pmState',
    '@tiptap/pm/view': 'pmView',
    '@tiptap/pm/model': 'pmModel',
}

/** Reproducible generated facade, with the generator and installed module pinned. */
export async function describeTiptapSharedInput(specifier, root) {
    if (!Object.hasOwn(tiptapSharedKeys, specifier)) throw new Error('Unsupported shared Tiptap input.')
    const realModule = await import(specifier)
    const namedExports = Object.keys(realModule).filter(key => key !== '__esModule' && key !== 'default')
    const key = tiptapSharedKeys[specifier]
    let contents = `const __module = window.FilamentRichEditor.tiptap.${key};\n`
    if (namedExports.length) contents += `export const { ${namedExports.join(', ')} } = __module;\n`
    contents += 'export default __module?.default ?? __module;\n'
    const resolved = fileURLToPath(import.meta.resolve(specifier))
    let directory = path.dirname(resolved)
    while (!fs.existsSync(path.join(directory, 'package.json'))) {
        const parent = path.dirname(directory)
        if (parent === directory) throw new Error('Shared Tiptap package metadata not found.')
        directory = parent
    }
    const provenance = [resolved, path.join(directory, 'package.json'), fileURLToPath(import.meta.url)]
        .map(file => path.relative(root, fs.realpathSync(file)).split(path.sep).join('/')).sort()
    return { identity: `virtual/tiptap-shared-v1/${key}.js`, provider: 'tiptap-shared-v1', contents, provenance }
}

export function tiptapSharedEsbuildPlugin({ virtualInputs = null, root = path.resolve(import.meta.dirname, '..') } = {}) {
    return {
        name: 'tiptap-shared',
        setup(build) {
            build.onResolve({ filter: /^@tiptap\/(core|pm\/(state|view|model))$/ }, (args) => ({
                path: args.path,
                namespace: 'tiptap-shared',
            }))

            build.onLoad({ filter: /.*/, namespace: 'tiptap-shared' }, async (args) => {
                const descriptor = await describeTiptapSharedInput(args.path, root)
                virtualInputs?.set(`tiptap-shared:${args.path}`, descriptor)
                return { contents: descriptor.contents, loader: 'js' }
            })
        },
    }
}
