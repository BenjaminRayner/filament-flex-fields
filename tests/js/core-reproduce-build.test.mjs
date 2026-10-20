import assert from 'node:assert/strict'
import test from 'node:test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { copyPublicBuildSnapshot, buildIdentity, distIdentity, assertMatchingBuilds } from '../../scripts/core/reproduce-build.mjs'
import { digest } from '../../scripts/core/acceptance-contract.mjs'
import { writeBundleMetrics } from '../../scripts/bundle-metrics.mjs'

function fixture(t) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-build-isolation-test-'))
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }))
    const root = path.join(directory, 'source')
    fs.mkdirSync(root)
    execFileSync('git', ['init', '--quiet'], { cwd: root })
    const write = (name, bytes) => {
        const target = path.join(root, name)
        fs.mkdirSync(path.dirname(target), { recursive: true })
        fs.writeFileSync(target, bytes)
    }
    write('.gitignore', '/.internal/\n/vendor/\n/node_modules/\n/build/\n')
    write('tsconfig.json', '{"compilerOptions":{"alwaysStrict":true}}\n')
    write('scripts/build.mjs', '// real public build tool\n')
    write('resources/dist/sample.js', 'const value = 1;\n')
    write('resources/dist/components/alpine-manifest.json', '{}\n')
    write('src/Support/Assets/BuiltInComponentIndex.php', '<?php\n')
    const assets = { 'sample.js': digest('const value = 1;\n') }, bytes = { 'sample.js': 17 }
    const metadata = { 'resources/dist/components/alpine-manifest.json': digest('{}\n'), 'src/Support/Assets/BuiltInComponentIndex.php': digest('<?php\n') }
    write('resources/dist/asset-versions.json', JSON.stringify({ format: 2, release: digest(JSON.stringify({ assets, bytes, metadata })), assets, bytes, metadata }) + '\n')
    execFileSync('git', ['add', '.'], { cwd: root })
    write('.internal/private.md', 'must never be an input')
    write('vendor/autoload.php', 'must not be forwarded')
    write('node_modules/private.txt', 'dependency tree is handled separately')
    write('build/receipt.json', 'not an input')
    return { directory, root, write }
}

test('public build copies maintainer tools and compiler policy without export-ignore or private inputs', t => {
    const f = fixture(t), destination = path.join(f.directory, 'isolated')
    f.write('.gitattributes', '/scripts/ export-ignore\n/tsconfig.json export-ignore\n')
    execFileSync('git', ['add', '.gitattributes'], { cwd: f.root })
    const before = distIdentity(f.root)
    const names = copyPublicBuildSnapshot(f.root, destination)
    assert.ok(names.includes('scripts/build.mjs') && names.includes('tsconfig.json'))
    for (const name of ['.internal', '.git', 'vendor', 'node_modules', 'build']) assert.equal(fs.existsSync(path.join(destination, name)), false)
    assert.deepEqual(buildIdentity(destination), buildIdentity(f.root))
    fs.appendFileSync(path.join(destination, 'resources/dist/sample.js'), '// changed snapshot only')
    assert.deepEqual(distIdentity(f.root), before)
    assert.throws(() => buildIdentity(destination), /asset size|asset content/)
    assert.throws(() => copyPublicBuildSnapshot(f.root, destination), /new directory/)
})

test('actual mutable metrics generation does not need to dirty the source checkout', t => {
    const f = fixture(t), destination = path.join(f.directory, 'isolated')
    f.write('resources/dist/bundle-metrics.json', JSON.stringify({ generatedAt: '2000-01-01T00:00:00.000Z', css: {}, js: {} }))
    execFileSync('git', ['add', 'resources/dist/bundle-metrics.json'], { cwd: f.root })
    const before = distIdentity(f.root)
    copyPublicBuildSnapshot(f.root, destination)
    writeBundleMetrics(destination, { css: {}, js: {} })
    assert.notEqual(fs.readFileSync(path.join(destination, 'resources/dist/bundle-metrics.json'), 'utf8'), fs.readFileSync(path.join(f.root, 'resources/dist/bundle-metrics.json'), 'utf8'))
    assert.deepEqual(distIdentity(f.root), before)
    assertMatchingBuilds(buildIdentity(f.root), buildIdentity(destination))
})

test('build comparison verifies generated metadata as well as the asset release and bytes', t => {
    const f = fixture(t), identity = buildIdentity(f.root)
    assertMatchingBuilds(identity, structuredClone(identity))
    assert.throws(() => assertMatchingBuilds(identity, { ...identity, metadata: {} }), /build metadata/)
    assert.throws(() => assertMatchingBuilds(identity, { ...identity, assetManifestSha256: digest('other') }), /build metadata/)
    f.write('src/Support/Assets/BuiltInComponentIndex.php', '<?php // drift\n')
    assert.throws(() => buildIdentity(f.root), /metadata differs/)
})

test('public snapshot refuses symlinked build inputs', t => {
    const f = fixture(t)
    fs.symlinkSync('tsconfig.json', path.join(f.root, 'alias.json'))
    execFileSync('git', ['add', 'alias.json'], { cwd: f.root })
    assert.throws(() => copyPublicBuildSnapshot(f.root, path.join(f.directory, 'isolated')), /regular files/)
})
