import assert from 'node:assert/strict'
import test from 'node:test'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync } from 'node:child_process'
import { acceptanceRequirements, digest, reportDestination, verifySecurityPolicy } from '../../scripts/core/acceptance-contract.mjs'
import { verifyAcceptanceEvidence } from '../../scripts/core/acceptance-evidence.mjs'
import { collectAcceptanceEnvironment, verifyAcceptanceEnvironment } from '../../scripts/core/acceptance-environment.mjs'

const root = path.resolve(import.meta.dirname, '../..')

function fixture(t) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-release-evidence-'))
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }))
    const sources = { 'composer.lock': digest('composer'), 'package-lock.json': digest('npm'), 'src/example.php': digest('trusted source') }, assetHashes = { 'sample.js': digest('asset') }, release = digest('release'), commit = 'a'.repeat(40)
    const assetManifestSha256 = digest('seal')
    const requirements = acceptanceRequirements(root), receipts = {}
    for (const [key, names] of Object.entries(requirements)) {
        const [phase, engine] = key.split('/'), folder = path.join(directory, phase + '-' + engine)
        fs.mkdirSync(folder)
        const checks = names.map(name => {
            const content = 'passed ' + name + '\n'
            fs.writeFileSync(path.join(folder, name + '.log'), content)
            return { name, passed: true, code: 0, signal: null, expired: false, launchError: null,
                log: name + '.log', logSha256: digest(content),
                ...(phase === 'reproducibility' ? { release, artifacts: 580, identicalImmutableArtifacts: true } : {}) }
        })
        receipts[key] = { folder, value: { format: 2, phase, engine: engine === 'all' ? null : engine,
            commit, sources, sourceSha256: digest(JSON.stringify(sources)), sourceUnchanged: true,
            environment: { node: 'v24.14.0', platform: 'linux', architecture: 'x64', php: '8.3.33', flexVersion: '4.0.9',
                packages: { 'filament/filament': 'v5.9.0', 'livewire/livewire': 'v4.4.4' },
                browser: engine === 'all' ? null : { engine, version: engine === 'firefox' ? '157.0' : '156.0.8078.4', connection: engine === 'firefox' ? 'remote' : 'local' } },
            workingTreeDirty: false, release, passed: true, checks } }
    }
    const metadataEntry = receipts['contracts/all']
    const metadataFolder = path.join(metadataEntry.folder, 'release-metadata')
    fs.mkdirSync(metadataFolder)
    const bom = JSON.stringify({ bomFormat: 'CycloneDX', specVersion: '1.6' })
    const provenance = JSON.stringify({ format: 1, signed: false, commit,
        sourceSha256: digest(JSON.stringify(sources)), release, artifacts: assetHashes,
        materials: { 'composer.lock': sources['composer.lock'], 'package-lock.json': sources['package-lock.json'], 'resources/dist/asset-versions.json': assetManifestSha256 },
        sbomSha256: digest(bom) })
    fs.writeFileSync(path.join(metadataFolder, 'sbom.cdx.json'), bom)
    fs.writeFileSync(path.join(metadataFolder, 'build-provenance.json'), provenance)
    metadataEntry.value.checks.find(check => check.name === 'release-metadata').artifacts = {
        'release-metadata/sbom.cdx.json': digest(bom), 'release-metadata/build-provenance.json': digest(provenance),
    }
    const write = () => { for (const { folder, value } of Object.values(receipts)) fs.writeFileSync(path.join(folder, 'receipt.json'), JSON.stringify(value)) }
    write()
    return { directory, commit, sources, release, assetHashes, assetManifestSha256, requirements, receipts, write }
}

test('complete source-matched evidence aggregates three engines and keeps deployment certification separate', t => {
    const f = fixture(t), report = verifyAcceptanceEvidence(f)
    assert.equal(Object.keys(report.phases).length, 10)
    assert.equal(report.automatedComplete, true)
    assert.equal(report.releaseComplete, false)
    assert.equal(report.sourceSha256, digest(JSON.stringify(f.sources)))
    assert.deepEqual(report.phases['browser/firefox'].environment, f.receipts['browser/firefox'].value.environment)
})

test('certification preserves differing executed runtime axes instead of assuming identical CI hosts', t => {
    const f = fixture(t)
    const value = f.receipts['browser/webkit'].value
    value.environment.php = '8.5.8'
    value.environment.platform = 'darwin'
    value.environment.architecture = 'arm64'
    value.environment.packages['filament/filament'] = '5.x-dev'
    value.environment.browser.version = '26.0'
    f.write()
    const report = verifyAcceptanceEvidence(f)
    assert.equal(report.phases['contracts/all'].environment.php, '8.3.33')
    assert.deepEqual(report.phases['browser/webkit'].environment, value.environment)
})

test('runtime inventory reads the PHP process and actual installed Composer versions', async () => {
    const environment = await collectAcceptanceEnvironment({ root, php: process.env.PHP_BINARY ?? 'php' })
    const installed = JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', ['-r',
        "require $argv[1]; echo json_encode([PHP_VERSION, Composer\\InstalledVersions::getPrettyVersion('filament/filament'), Composer\\InstalledVersions::getPrettyVersion('livewire/livewire')], JSON_THROW_ON_ERROR);",
        path.join(root, 'vendor/autoload.php')], { encoding: 'utf8' }))
    assert.equal(environment.php, installed[0])
    assert.equal(environment.packages['filament/filament'], installed[1])
    assert.equal(environment.packages['livewire/livewire'], installed[2])
    assert.equal(environment.flexVersion, fs.readFileSync(path.join(root, 'VERSION'), 'utf8').trim())
    assert.equal(environment.browser, null)
})

for (const [label, mutate] of [
    ['missing environment', r => { delete r.environment }],
    ['missing PHP version', r => { delete r.environment.php }],
    ['invalid PHP version', r => { r.environment.php = 'unknown' }],
    ['missing Composer package', r => { delete r.environment.packages['livewire/livewire'] }],
    ['invalid Composer version', r => { r.environment.packages['filament/filament'] = 'unknown version' }],
    ['missing browser version', r => { delete r.environment.browser.version }],
    ['missing browser runtime', r => { r.environment.browser = null }],
    ['missing browser connection', r => { delete r.environment.browser.connection }],
    ['mismatched browser', r => { r.environment.browser.engine = 'webkit' }],
    ['unbounded browser version', r => { r.environment.browser.version = '1'.repeat(101) }],
    ['unknown environment data', r => { r.environment.secret = 'not part of the environment contract' }],
]) test('certification rejects ' + label, t => {
    const f = fixture(t)
    mutate(f.receipts['browser/chromium'].value)
    f.write()
    assert.throws(() => verifyAcceptanceEvidence(f), /environment|version|fields|browser|PHP|package/i)
})

test('non-browser phases cannot claim an unobserved browser runtime', t => {
    const f = fixture(t), environment = f.receipts['contracts/all'].value.environment
    environment.browser = { engine: 'chromium', version: '156.0.0.1' }
    assert.throws(() => verifyAcceptanceEnvironment(environment), /must not claim/)
})

test('missing browser evidence cannot be replaced by another engine or duplicate phase', t => {
    const f = fixture(t)
    fs.rmSync(f.receipts['browser/firefox'].folder, { recursive: true })
    assert.throws(() => verifyAcceptanceEvidence(f), /Missing required/)
    const duplicate = path.join(f.directory, 'duplicate')
    fs.cpSync(f.receipts['browser/chromium'].folder, duplicate, { recursive: true })
    assert.throws(() => verifyAcceptanceEvidence(f), /duplicate acceptance phase/)
})

for (const [label, mutate, message] of [
    ['different commit', r => { r.commit = 'b'.repeat(40) }, /different commit/],
    ['dirty checkout', r => { r.workingTreeDirty = true }, /clean checkout/],
    ['different source', r => { r.sourceSha256 = digest('other') }, /fingerprint/],
    ['forged source inventory', r => { r.sources = {} }, /inventory differs/],
    ['source drift', r => { r.sourceUnchanged = false }, /Source changed/],
    ['different release', r => { r.release = digest('other') }, /release differs/],
    ['failed phase', r => { r.passed = false }, /did not pass/],
    ['omitted checks', r => { r.checks.pop() }, /incomplete/],
    ['timed out check', r => { r.checks[0].expired = true }, /successfully/],
    ['killed check', r => { r.checks[0].signal = 'SIGKILL' }, /successfully/],
    ['escaped log path', r => { r.checks[0].log = '../outside.log' }, /local check/],
]) {
    test('rejects ' + label, t => {
        const f = fixture(t)
        mutate(f.receipts['contracts/all'].value)
        f.write()
        assert.throws(() => verifyAcceptanceEvidence(f), message)
    })
}

test('acceptance refuses altered logs even if the phase claims success', t => {
    const f = fixture(t), entry = f.receipts['contracts/all']
    fs.appendFileSync(path.join(entry.folder, entry.value.checks[0].log), 'changed')
    assert.throws(() => verifyAcceptanceEvidence(f), /log was modified/)
})

test('acceptance does not follow symlinked logs or directories', t => {
    const f = fixture(t), entry = f.receipts['contracts/all']
    const log = path.join(entry.folder, entry.value.checks[0].log), replacement = path.join(f.directory, 'other.log')
    fs.renameSync(log, replacement); fs.symlinkSync(replacement, log)
    assert.throws(() => verifyAcceptanceEvidence(f), /regular file|symlink/)
})

test('reproducibility requires two identical complete immutable builds', t => {
    const f = fixture(t)
    f.receipts['reproducibility/all'].value.checks[1].identicalImmutableArtifacts = false
    f.write()
    assert.throws(() => verifyAcceptanceEvidence(f), /not identical/)
})

test('reports are generated and checked in isolated output without consulting private history', t => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-current-report-'))
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }))
    const target = path.join(directory, 'architecture.json')
    const run = (...args) => execFileSync(process.execPath, ['scripts/core/architecture-inventory.mjs', '--output=' + target, ...args], { cwd: root, stdio: 'pipe' })
    run(); run('--check')
    const inventory = JSON.parse(fs.readFileSync(target))
    assert.ok(inventory.serviceBindings.length > 0)
    fs.writeFileSync(target, '{}')
    assert.throws(() => run('--check'), /Command failed/)
    run(); run('--check')
})

test('report argument handling rejects ambiguity and uses build output by default', () => {
    assert.equal(reportDestination(root, 'example.json', []).destination, path.join(root, 'build/core-reports/example.json'))
    for (const args of [['--unknown'], ['--check', '--check'], ['--output=a', '--output=b'], ['--output=']]) {
        assert.throws(() => reportDestination(root, 'example.json', args), /Unknown or repeated/)
    }
})

test('security policy must support the current major exactly once', () => {
    const valid = '| 4.x | Security updates for the latest 4.x patch |\n| 3.x | Security updates for the latest 3.x patch |'
    assert.equal(verifySecurityPolicy('4.0.9', valid).major, 4)
    for (const invalid of ['', '| 4.x | In development; not yet publicly released |', valid + '\n' + valid, valid.replace('4.x patch', '3.x patch')]) {
        assert.throws(() => verifySecurityPolicy('4.0.9', invalid))
    }
    assert.throws(() => verifySecurityPolicy('unknown', valid))
    verifySecurityPolicy(fs.readFileSync(path.join(root, 'VERSION'), 'utf8').trim(), fs.readFileSync(path.join(root, 'SECURITY.md'), 'utf8'))
})

test('changed SBOM cannot be substituted by updating its attachment digest alone', t => {
    const f = fixture(t), entry = f.receipts['contracts/all'], check = entry.value.checks.find(c => c.name === 'release-metadata')
    const file = path.join(entry.folder, 'release-metadata/sbom.cdx.json')
    fs.appendFileSync(file, '\n')
    check.artifacts['release-metadata/sbom.cdx.json'] = digest(fs.readFileSync(file))
    f.write()
    assert.throws(() => verifyAcceptanceEvidence(f), /not bound to provenance/)
})

for (const [name, mutate, message] of [
    ['commit', record => { record.commit = 'b'.repeat(40) }, /Provenance commit/],
    ['source', record => { record.sourceSha256 = digest('other') }, /Provenance source/],
    ['assets', record => { record.artifacts = {} }, /asset inventory/],
    ['signature claim', record => { record.signed = true }, /claim a signature/],
    ['lockfile', record => { record.materials['composer.lock'] = digest('other') }, /lockfile identity/],
    ['seal', record => { record.materials['resources/dist/asset-versions.json'] = digest('other') }, /seal identity/],
]) test('provenance rejects a changed ' + name + ' even with updated attachment hash', t => {
    const f = fixture(t), entry = f.receipts['contracts/all'], check = entry.value.checks.find(c => c.name === 'release-metadata')
    const file = path.join(entry.folder, 'release-metadata/build-provenance.json')
    const record = JSON.parse(fs.readFileSync(file))
    mutate(record); fs.writeFileSync(file, JSON.stringify(record))
    check.artifacts['release-metadata/build-provenance.json'] = digest(fs.readFileSync(file))
    f.write()
    assert.throws(() => verifyAcceptanceEvidence(f), message)
})
