import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { digest } from './acceptance-contract.mjs'

const sha256 = /^[a-f0-9]{64}$/
const sorted = value => Object.fromEntries(Object.entries(value).sort(([a], [b]) => a.localeCompare(b, 'en')))
const licenses = input => (Array.isArray(input) ? input : typeof input === 'string' ? [input] : [])
    .filter(value => typeof value === 'string' && value.length > 0 && value.length <= 512).map(name => ({ license: { name } }))

export function safeReleaseFile(root, name) {
    assert.ok(typeof name === 'string' && name.length <= 1024 && !name.includes('\\') && !name.includes('\0')
        && !path.isAbsolute(name) && name.split('/').every(part => part && part !== '.' && part !== '..'), 'Unsafe release material path.')
    let current = root
    for (const part of name.split('/')) {
        current = path.join(current, part)
        assert.ok(!fs.lstatSync(current).isSymbolicLink(), 'Release materials cannot use symlinks.')
    }
    const stat = fs.lstatSync(current)
    assert.ok(stat.isFile() && stat.size <= 256 * 1024 * 1024, 'Release material must be a bounded regular file.')
    return fs.readFileSync(current)
}

export function verifyReleaseSeal(root, seal) {
    assert.equal(seal.format, 2, 'Unsupported asset seal format.')
    assert.match(seal.release, sha256)
    assert.ok(Object.keys(seal.assets).length > 0 && Object.keys(seal.assets).length <= 10000, 'Invalid sealed asset count.')
    assert.deepEqual(Object.keys(seal.assets).sort(), Object.keys(seal.bytes).sort(), 'Asset byte inventory differs.')
    for (const [name, expected] of Object.entries(seal.assets)) {
        assert.match(expected, sha256)
        const bytes = safeReleaseFile(root, 'resources/dist/' + name)
        assert.equal(bytes.length, seal.bytes[name], 'Sealed asset size differs: ' + name)
        assert.equal(digest(bytes), expected, 'Sealed asset content differs: ' + name)
    }
    assert.deepEqual(Object.keys(seal.metadata).sort(), ['resources/dist/components/alpine-manifest.json', 'src/Support/Assets/BuiltInComponentIndex.php'])
    for (const [name, expected] of Object.entries(seal.metadata)) assert.equal(digest(safeReleaseFile(root, name)), expected, 'Sealed build metadata differs.')
    assert.equal(digest(JSON.stringify({ assets: seal.assets, bytes: seal.bytes, metadata: seal.metadata })), seal.release, 'Asset release identity differs.')
    return seal.assets
}

/** Exact lockfile inventory, including build/dev and optional platform packages. Not a customer application's resolved graph. */
export function createLockfileSbom({ version, composer, composerLock, npmLock }) {
    assert.ok(Array.isArray(composerLock.packages) && Array.isArray(composerLock['packages-dev']), 'Incomplete Composer lockfile.')
    assert.equal(npmLock.lockfileVersion, 3, 'Only reviewed npm v3 lockfiles are supported.')
    const components = [], ids = new Set()
    const component = (ecosystem, name, version, reference, usage, license, hashes = []) => {
        assert.ok(typeof name === 'string' && name.length > 0 && name.length <= 512
            && typeof version === 'string' && version.length > 0 && version.length <= 128, 'Dependency needs an exact name and version.')
        assert.ok(!ids.has(reference), 'Duplicate dependency identity.')
        ids.add(reference)
        components.push({ type: 'library', 'bom-ref': reference, name, version,
            purl: 'pkg:' + ecosystem + '/' + name.split('/').map(encodeURIComponent).join('/') + '@' + encodeURIComponent(version),
            ...(licenses(license).length ? { licenses: licenses(license) } : {}),
            ...(hashes.length ? { hashes } : {}),
            properties: [{ name: 'flex:lockfile-usage', value: usage }] })
    }
    for (const [key, usage] of [['packages', 'runtime'], ['packages-dev', 'development']]) {
        for (const item of composerLock[key]) component('composer', item.name, item.version, 'composer:' + item.name, usage, item.license)
    }
    for (const [location, item] of Object.entries(npmLock.packages)) {
        if (location === '') continue
        assert.ok(!item.link && location.includes('node_modules/'), 'Unresolved npm links need an explicit SBOM adapter.')
        const hashes = []
        if (item.integrity) for (const integrity of item.integrity.split(/\s+/)) {
            const match = integrity.match(/^(sha1|sha256|sha384|sha512)-([A-Za-z0-9+/]+={0,2})$/)
            assert.ok(match, 'Unsupported npm integrity declaration.')
            const content = Buffer.from(match[2], 'base64').toString('hex')
            assert.equal(content.length, { sha1: 40, sha256: 64, sha384: 96, sha512: 128 }[match[1]], 'Invalid npm integrity digest.')
            hashes.push({ alg: { sha1: 'SHA-1', sha256: 'SHA-256', sha384: 'SHA-384', sha512: 'SHA-512' }[match[1]], content })
        }
        component('npm', item.name ?? location.split('node_modules/').at(-1), item.version, 'npm:' + location,
            [item.dev ? 'development' : 'runtime', ...(item.optional ? ['optional'] : [])].join(','), item.license, hashes)
    }
    components.sort((a, b) => a['bom-ref'].localeCompare(b['bom-ref'], 'en'))
    return {
        $schema: 'http://cyclonedx.org/schema/bom-1.6.schema.json', bomFormat: 'CycloneDX', specVersion: '1.6', version: 1,
        metadata: {
            component: { type: 'library', 'bom-ref': 'flex-package', name: composer.name, version, licenses: licenses(composer.license) },
            properties: [
                { name: 'flex:inventory-scope', value: 'Exact maintainer Composer/npm lockfiles, including development and optional platform packages. Optional CDN resources, application overrides and customer dependency resolutions are outside this inventory.' },
                { name: 'flex:dependency-edges', value: 'Not inferred from constraints; this BOM lists resolved locked components.' },
            ],
        },
        components, compositions: [{ aggregate: 'incomplete', assemblies: ['flex-package'] }],
    }
}

export function createBuildProvenance({ commit, sourceSha256, release, assets, materials, sbomSha256, environment }) {
    assert.match(commit, /^[a-f0-9]{40}(?:[a-f0-9]{24})?$/)
    for (const value of [sourceSha256, release, sbomSha256, ...Object.values(materials), ...Object.values(assets)]) assert.match(value, sha256)
    return { format: 1, kind: 'FLEX build provenance record', signed: false, commit, sourceSha256, release,
        artifacts: sorted(assets), materials: sorted(materials), sbomSha256, environment,
        scope: 'Local hash-bound record. Authenticity requires the separate GitHub/Sigstore attestation; hashes alone are not signatures.' }
}
