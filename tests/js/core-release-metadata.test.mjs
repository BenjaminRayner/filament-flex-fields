import assert from 'node:assert/strict'
import test from 'node:test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import crypto from 'node:crypto'
import { digest } from '../../scripts/core/acceptance-contract.mjs'
import { createLockfileSbom, createBuildProvenance, safeReleaseFile, verifyReleaseSeal } from '../../scripts/core/release-metadata.mjs'

function inputs() {
    return {
        version: '4.0.9', composer: { name: 'janczakb/filament-flex-fields', license: 'proprietary' },
        composerLock: { packages: [{ name: 'vendor/runtime', version: '1.2.3', license: ['MIT'] }],
            'packages-dev': [{ name: 'vendor/tool', version: '2.0.0' }] },
        npmLock: { lockfileVersion: 3, packages: { '': {},
            'node_modules/@scope/tool': { version: '3.0.1', dev: true, optional: true, license: 'MIT',
                resolved: 'https://private:credential@example.invalid/tool.tgz',
                integrity: 'sha512-' + crypto.createHash('sha512').update('bytes').digest('base64') },
            'node_modules/parent/node_modules/@scope/tool': { version: '3.0.1', dev: true },
        } },
    }
}
function sealFixture(t) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-seal-metadata-'))
    t.after(() => fs.rmSync(root, { recursive: true, force: true }))
    const write = (name, bytes) => { fs.mkdirSync(path.dirname(path.join(root, name)), { recursive: true }); fs.writeFileSync(path.join(root, name), bytes) }
    write('resources/dist/sample.js', 'export {}')
    write('resources/dist/components/alpine-manifest.json', '{}')
    write('src/Support/Assets/BuiltInComponentIndex.php', '<?php')
    const assets = { 'sample.js': digest('export {}') }, bytes = { 'sample.js': 9 }
    const metadata = { 'resources/dist/components/alpine-manifest.json': digest('{}'), 'src/Support/Assets/BuiltInComponentIndex.php': digest('<?php') }
    return { root, write, seal: { format: 2, assets, bytes, metadata, release: digest(JSON.stringify({ assets, bytes, metadata })) } }
}

test('SBOM preserves exact versions, scoped purls, build/optional roles and nested installed identities', () => {
    const bom = createLockfileSbom(inputs())
    assert.equal(bom.bomFormat, 'CycloneDX')
    assert.equal(bom.specVersion, '1.6')
    assert.equal(bom.components.length, 4)
    assert.equal(new Set(bom.components.map(c => c['bom-ref'])).size, 4)
    const npm = bom.components.find(c => c['bom-ref'] === 'npm:node_modules/@scope/tool')
    assert.equal(npm.purl, 'pkg:npm/%40scope/tool@3.0.1')
    assert.equal(npm.properties[0].value, 'development,optional')
    assert.equal(npm.hashes[0].content, crypto.createHash('sha512').update('bytes').digest('hex'))
    assert.equal(bom.compositions[0].aggregate, 'incomplete')
})

test('SBOM never copies registry credentials or claims to resolve a customer application', () => {
    const output = JSON.stringify(createLockfileSbom(inputs()))
    assert.ok(!output.includes('credential') && !output.includes('example.invalid'))
    assert.ok(output.includes('customer dependency resolutions are outside'))
})

test('SBOM output is deterministic despite lock package insertion order', () => {
    const first = inputs(), second = inputs()
    second.npmLock.packages = Object.fromEntries(Object.entries(second.npmLock.packages).reverse())
    assert.deepEqual(createLockfileSbom(first), createLockfileSbom(second))
})

for (const [name, change] of [
    ['unsupported lockfile format', value => { value.npmLock.lockfileVersion = 2 }],
    ['unresolved workspace link', value => { value.npmLock.packages['node_modules/@scope/tool'].link = true }],
    ['missing exact version', value => { delete value.npmLock.packages['node_modules/@scope/tool'].version }],
    ['invalid integrity', value => { value.npmLock.packages['node_modules/@scope/tool'].integrity = 'sha512-YQ==' }],
    ['duplicate Composer component', value => { value.composerLock['packages-dev'].push(value.composerLock.packages[0]) }],
]) test('SBOM refuses ' + name, () => {
    const value = inputs(); change(value)
    assert.throws(() => createLockfileSbom(value))
})

test('asset provenance reads actual bytes and the complete sealed metadata identity', t => {
    const f = sealFixture(t)
    assert.deepEqual(verifyReleaseSeal(f.root, f.seal), f.seal.assets)
    f.write('resources/dist/sample.js', 'export []')
    assert.throws(() => verifyReleaseSeal(f.root, f.seal), /content differs/)
})

test('asset provenance rejects tampered metadata and forged seal identities', t => {
    const f = sealFixture(t)
    assert.throws(() => verifyReleaseSeal(f.root, { ...f.seal, release: digest('forged') }), /identity differs/)
    f.write('resources/dist/components/alpine-manifest.json', '{"changed":true}')
    assert.throws(() => verifyReleaseSeal(f.root, f.seal), /metadata differs/)
})

test('asset provenance rejects traversal and symlinked input directories', t => {
    const f = sealFixture(t)
    assert.throws(() => safeReleaseFile(f.root, '../secrets'), /Unsafe/)
    fs.renameSync(path.join(f.root, 'resources/dist'), path.join(f.root, 'original-dist'))
    fs.symlinkSync(path.join(f.root, 'original-dist'), path.join(f.root, 'resources/dist'))
    assert.throws(() => verifyReleaseSeal(f.root, f.seal), /symlinks/)
})

test('local provenance binds lockfiles, source, asset bytes and BOM without claiming a signature', () => {
    const record = createBuildProvenance({ commit: 'a'.repeat(40), sourceSha256: digest('source'), release: digest('release'),
        assets: { 'a.js': digest('asset') }, materials: { 'composer.lock': digest('lock') },
        sbomSha256: digest('bom'), environment: { node: process.version } })
    assert.equal(record.signed, false)
    assert.equal(record.materials['composer.lock'], digest('lock'))
    assert.equal(record.sbomSha256, digest('bom'))
    assert.throws(() => createBuildProvenance({ ...record, commit: 'invalid' }))
})
