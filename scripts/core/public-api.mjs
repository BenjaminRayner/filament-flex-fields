import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'
import { pathToFileURL } from 'node:url'
import { build } from 'esbuild'
import { createFlexSdkPlugin } from '../assets/flex-sdk-plugin.mjs'

/** Conservative declaration compatibility. Behavior and serialized-value migrations have separate tests. */
export function comparePublicApi(baseline, current) {
    assert.equal(baseline.format, 1, 'Unsupported API baseline format.')
    assert.equal(current.format, 1, 'Unsupported API snapshot format.')
    const breaking = [], additions = []
    const compareMembers = (before, after, location) => {
        for (const [name, value] of Object.entries(before)) {
            if (!Object.hasOwn(after, name)) breaking.push(location + '.' + name + ': removed')
            else if (JSON.stringify(value) !== JSON.stringify(after[name])) breaking.push(location + '.' + name + ': declaration changed')
        }
        for (const name of Object.keys(after)) if (!Object.hasOwn(before, name)) additions.push(location + '.' + name)
    }
    for (const [name, before] of Object.entries(baseline.php.classes)) {
        const after = current.php.classes[name]
        if (!after) { breaking.push(name + ': removed from public scope'); continue }
        for (const key of ['kind', 'final', 'abstract', 'readonly', 'parent', 'interfaces']) {
            if (JSON.stringify(before[key]) !== JSON.stringify(after[key])) breaking.push(name + ': ' + key + ' changed')
        }
        for (const group of ['methods', 'properties', 'constants']) {
            compareMembers(before[group], after[group], name + '.' + group)
            if (group === 'methods') for (const [method, declaration] of Object.entries(after.methods)) {
                if (!Object.hasOwn(before.methods, method) && (after.kind === 'interface' || declaration.abstract)) {
                    breaking.push(name + '.' + method + ': new implementation requirement')
                }
            }
            if (group === 'constants' && after.kind === 'enum') for (const key of Object.keys(after.constants)) {
                if (!Object.hasOwn(before.constants, key)) breaking.push(name + '.' + key + ': new enum case requires exhaustive-match review')
            }
        }
    }
    for (const name of Object.keys(current.php.classes)) if (!Object.hasOwn(baseline.php.classes, name)) additions.push(name)
    if (baseline.sdk.abi !== current.sdk.abi) breaking.push('JavaScript SDK ABI changed')
    compareMembers(baseline.sdk.exports, current.sdk.exports, 'sdk')
    return { format: 1, compatible: breaking.length === 0, breaking, additions,
        policy: 'Removed or changed existing declarations require explicit compatibility review. This conservative gate does not certify behavior, PHPDoc shapes, SDK option objects or data migrations.' }
}

export async function capturePublicApi(root) {
    const scope = JSON.parse(fs.readFileSync(path.join(root, 'scripts/core/contracts/public-api.json')))
    const php = JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', ['scripts/core/export-php-api.php'], { cwd: root, maxBuffer: 16 * 1024 * 1024 }))
    const result = await build({
        stdin: { contents: 'export * from "@bjanczak/flex-fields/sdk/v1";', resolveDir: root },
        absWorkingDir: root, bundle: true, format: 'esm', metafile: true, write: false, plugins: [createFlexSdkPlugin()],
    })
    const names = Object.values(result.metafile.outputs)[0].exports.sort()
    const runtime = await import(pathToFileURL(path.join(root, 'resources/js/sdk/v1.js')).href)
    for (const name of scope.sdk.exports) assert.ok(names.includes(name), 'Declared public SDK export is missing: ' + name)
    const exports = Object.fromEntries(names.map(name => {
        assert.ok(Object.hasOwn(runtime, name), 'SDK facade has no runtime implementation: ' + name)
        const value = runtime[name]
        return [name, typeof value === 'function' ? { kind: 'function', requiredArguments: value.length } : { kind: typeof value, value }]
    }))
    assert.equal(runtime.sdkABI, scope.sdk.abi, 'SDK protocol differs from the reviewed public scope.')
    return { format: 1, php, sdk: { abi: runtime.sdkABI, exports } }
}
