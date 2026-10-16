import assert from 'node:assert/strict'
import { compile } from '@tailwindcss/node'
import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(import.meta.dirname, '../..')
const runtimeSources = ['../../src/**/*.php', '../views/**/*.blade.php', '../js/**/*.js']

/** Inspect Tailwind's resolved compiler contract, rather than regex-matching imports.
 * Renderer sources include the optional catalog's actual renderers. Docs, examples,
 * diagnostics tooling, tests, generated output and the invoking application's cwd
 * must never add utilities to a distributed release.
 */
export async function verifyCssSourceContract(root = packageRoot) {
    const base = resolve(root, 'resources/css')
    const compiler = await compile(await readFile(resolve(base, 'core.css'), 'utf8'), {
        base, onDependency() {},
    })
    assert.equal(compiler.root, 'none', 'Core CSS must disable automatic working-directory detection.')
    assert.deepEqual(compiler.sources.map(source => ({ path: resolve(source.base, source.pattern), negated: source.negated }))
        .sort((a, b) => a.path.localeCompare(b.path)), runtimeSources.map(pattern => ({ path: resolve(base, pattern), negated: false }))
        .sort((a, b) => a.path.localeCompare(b.path)), 'The core utility closure must use only the declared PHP, Blade and JS renderers.')
    const baseline = await compile(await readFile(resolve(base, 'utilities-baseline.css'), 'utf8'), {
        base, onDependency() {},
    })
    assert.equal(baseline.root, 'none', 'Shared component utility definitions must not restore automatic scanning.')
    assert.equal(baseline.sources.length, 0, 'Shared utility definitions must not add an implicit component-wide source scan.')
    return { valid: true, sources: compiler.sources.length, automaticDetection: false }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    process.stdout.write(JSON.stringify(await verifyCssSourceContract()) + '\n')
}
