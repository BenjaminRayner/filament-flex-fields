import assert from 'node:assert/strict'
import { execFileSync, spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { digest, sourceSnapshot } from './acceptance-contract.mjs'
import { verifyReleaseSeal } from './release-metadata.mjs'

const excluded = /^(?:\.git|\.internal|build|vendor|node_modules)(?:\/|$)/

/** Copy public files, never Git metadata, private audit inputs or forwarded PHP dependencies. */
export function copyPublicBuildSnapshot(root, destination) {
    assert.ok(!fs.existsSync(destination), 'Build snapshot must start in a new directory.')
    fs.mkdirSync(destination, { recursive: true })
    const names = [...new Set(execFileSync('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard'], { cwd: root })
        .toString().split('\0'))].filter(name => name && !excluded.test(name) && fs.existsSync(path.join(root, name))).sort()
    for (const name of names) {
        assert.ok(!path.isAbsolute(name) && !name.split('/').some(part => part === '..'), 'Unsafe public build source.')
        const source = path.join(root, name), target = path.join(destination, name)
        assert.ok(fs.lstatSync(source).isFile(), 'Public build inputs must be regular files: ' + name)
        fs.mkdirSync(path.dirname(target), { recursive: true })
        fs.copyFileSync(source, target, fs.constants.COPYFILE_FICLONE)
        fs.chmodSync(target, fs.statSync(source).mode & 0o777)
    }
    assert.ok(!fs.existsSync(path.join(destination, '.internal')), 'Private build inputs must not be copied.')
    assert.ok(fs.existsSync(path.join(destination, 'tsconfig.json')), 'The package compiler boundary is required.')
    return names
}

/** Validate actual generated files, including metadata, rather than trusting a release label. */
export function buildIdentity(root) {
    const manifest = fs.readFileSync(path.join(root, 'resources/dist/asset-versions.json'))
    const seal = JSON.parse(manifest)
    verifyReleaseSeal(root, seal)
    return { release: seal.release, assets: seal.assets, metadata: seal.metadata, assetManifestSha256: digest(manifest) }
}

export function assertMatchingBuilds(expected, observed) {
    assert.deepEqual(observed, expected, 'Isolated build differs from the checked-in sealed assets or build metadata.')
}

export function distIdentity(root) {
    const directory = path.join(root, 'resources/dist')
    const result = {}
    const visit = relative => {
        for (const entry of fs.readdirSync(path.join(directory, relative), { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name, 'en'))) {
            const name = relative ? relative + '/' + entry.name : entry.name
            if (entry.isDirectory()) visit(name)
            else {
                assert.ok(entry.isFile(), 'Original dist must contain regular files only.')
                result[name] = digest(fs.readFileSync(path.join(directory, name)))
            }
        }
    }
    visit('')
    return result
}

function run(command, cwd, environment) {
    const result = spawnSync(command[0], command.slice(1), { cwd, env: environment, stdio: 'inherit', timeout: 14 * 60 * 1000 })
    assert.ok(!result.error && result.status === 0 && result.signal === null, 'Isolated build command failed: ' + command[0])
}

export function reproduceBuild(root, destination) {
    assert.ok(!fs.existsSync(destination), 'Do not overwrite an existing reproducibility report.')
    const sources = sourceSnapshot(root), sourceSha256 = digest(JSON.stringify(sources))
    const gitState = execFileSync('git', ['status', '--porcelain'], { cwd: root, encoding: 'utf8' })
    const expected = buildIdentity(root)
    const originalDist = distIdentity(root)
    const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-public-build-'))
    const snapshot = path.join(temporary, 'package')
    const environment = { ...process.env }
    environment.PATH = path.dirname(process.execPath) + path.delimiter + (environment.PATH ?? '')
    delete environment.FLEX_FIELDS_ROOT
    delete environment.FLEX_FIELDS_AUTOLOAD
    try {
        const files = copyPublicBuildSnapshot(root, snapshot)
        assert.ok(!files.some(name => excluded.test(name)))
        run(['composer', 'install', '--no-interaction', '--prefer-dist', '--no-scripts', '--no-plugins', '--no-progress'], snapshot, environment)
        const php = environment.PHP_BINARY ?? 'php'
        const reflected = execFileSync(php, ['-r',
            'require $argv[1]; echo (new ReflectionClass(Bjanczak\\FilamentFlexFields\\Support\\Assets\\Build\\AssetBuildValidator::class))->getFileName();',
            path.join(snapshot, 'vendor/autoload.php')], { cwd: snapshot, env: environment, encoding: 'utf8' })
        assert.equal(fs.realpathSync(reflected), fs.realpathSync(path.join(snapshot, 'src/Support/Assets/Build/AssetBuildValidator.php')),
            'Isolated PHP autoload forwarded to the source checkout.')
        run(['npm', 'ci', '--no-audit', '--no-fund'], snapshot, environment)
        run(['npm', 'run', 'build'], snapshot, environment)
        const built = buildIdentity(snapshot)
        assertMatchingBuilds(expected, built)
        const report = { format: 1, sourceSha256, ...built, publicInputFiles: files.length,
            privateInputsCopied: false, isolatedPhpAutoload: true, isolatedNodeDependencies: true,
            sourceUnchanged: true, originalBuildUnchanged: true }
        assert.equal(JSON.stringify(sourceSnapshot(root)), JSON.stringify(sources), 'Original build sources changed during reproduction.')
        assertMatchingBuilds(expected, buildIdentity(root))
        assert.equal(execFileSync('git', ['status', '--porcelain'], { cwd: root, encoding: 'utf8' }), gitState,
            'Isolated reproduction changed the original working tree.')
        fs.mkdirSync(path.dirname(destination), { recursive: true })
        fs.writeFileSync(destination, JSON.stringify(report, null, 2) + '\n')
        process.stdout.write(JSON.stringify({ sourceSha256, release: built.release, artifacts: Object.keys(built.assets).length,
            isolatedPhpAutoload: true, isolatedNodeDependencies: true, sourceUnchanged: true, originalBuildUnchanged: true }) + '\n')
        return report
    } finally {
        fs.rmSync(temporary, { recursive: true, force: true })
        assert.equal(JSON.stringify(sourceSnapshot(root)), JSON.stringify(sources), 'Original build sources changed during reproduction.')
        assert.deepEqual(distIdentity(root), originalDist, 'Isolated reproduction changed original dist files.')
        assert.equal(execFileSync('git', ['status', '--porcelain'], { cwd: root, encoding: 'utf8' }), gitState,
            'Isolated reproduction changed the original working tree.')
    }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const args = process.argv.slice(2)
    assert.ok(args.length === 1 && args[0].startsWith('--output=') && args[0].length > 9, 'Provide --output=<new report file>.')
    reproduceBuild(path.resolve(import.meta.dirname, '../..'), path.resolve(args[0].slice(9)))
}
