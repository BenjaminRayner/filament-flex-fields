import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { createHash } from 'node:crypto'
import { execFileSync } from 'node:child_process'
import { createServer } from 'node:http'
import { launchCoreBrowser, browserEngine } from './browser-profile.mjs'

// Same-source synthetic package revisions isolate the deployment protocol. This
// does not certify compatibility with an arbitrary historical FLEX release.
const root = path.resolve(import.meta.dirname, '../..')
const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'fff-core-dist-rollout-'))
const application = path.join(temporary, 'application')
const fixture = path.join(application, 'fixture')
const publicRoot = path.join(fixture, 'public')
const installed = path.join(application, 'vendor/janczakb/filament-flex-fields')
const php = process.env.PHP_BINARY ?? 'php'
const run = (command, args, cwd = application, extra = {}) => execFileSync(command, args, {
    cwd, encoding: 'utf8', timeout: 240000, maxBuffer: 8 * 1024 * 1024,
    env: { ...process.env, ...extra },
})
const json = filename => JSON.parse(fs.readFileSync(filename, 'utf8'))
const digest = filename => createHash('sha256').update(fs.readFileSync(filename)).digest('hex')
const originalSeal = json(path.join(root, 'resources/dist/asset-versions.json'))
const originalCss = digest(path.join(root, 'resources/css/components/flex-text-input.css'))
const currentVersion = fs.readFileSync(path.join(root, 'VERSION'), 'utf8').trim()
assert.match(currentVersion, /^\d+\.\d+\.\d+$/)
const [major, minor, patch] = currentVersion.split('.').map(Number)
const nextVersion = `${major}.${minor}.${patch + 1}`
let browser, server, failure
const requests = [], errors = [], phases = []
const navigations = new WeakMap()
try {
    fs.mkdirSync(application, { recursive: true })
    const metadata = json(path.join(root, 'composer.json'))
    const archives = []
    for (const [index, version] of [currentVersion, nextVersion].entries()) {
        const source = path.join(temporary, 'source-' + index)
        fs.mkdirSync(source)
        for (const name of ['src', 'resources', 'config', 'stubs']) fs.cpSync(path.join(root, name), path.join(source, name), { recursive: true })
        // Git does not preserve empty optional package directories.
        for (const name of ['routes', 'database']) if (fs.existsSync(path.join(root, name))) fs.cpSync(path.join(root, name), path.join(source, name), { recursive: true })
        for (const name of ['composer.json', '.gitattributes', 'VERSION']) fs.copyFileSync(path.join(root, name), path.join(source, name))
        if (index === 1) {
            fs.cpSync(path.join(root, 'scripts'), path.join(source, 'scripts'), { recursive: true })
            fs.copyFileSync(path.join(root, 'tsconfig.json'), path.join(source, 'tsconfig.json'))
            fs.symlinkSync(path.join(root, 'node_modules'), path.join(source, 'node_modules'), 'dir')
            fs.symlinkSync(path.join(root, 'vendor'), path.join(source, 'vendor'), 'dir')
            fs.appendFileSync(path.join(source, 'resources/css/components/flex-text-input.css'), '\n[data-fff-text-input] { --fff-rollout-fixture: 1; }\n')
            fs.writeFileSync(path.join(source, 'VERSION'), version + '\n')
            run(process.execPath, ['scripts/build-component-css.mjs', 'flex-text-input'], source)
            run(process.execPath, ['scripts/assets/write-asset-versions.mjs'], source)
            run(php, ['scripts/assets/validate-build.php'], source)
            fs.unlinkSync(path.join(source, 'node_modules'))
            fs.unlinkSync(path.join(source, 'vendor'))
        }
        const archive = path.join(temporary, 'core-' + version + '.zip')
        run('git', ['init', '--quiet'], source)
        run('git', ['add', '.'], source)
        run('git', ['-c', 'user.name=FLEX Acceptance', '-c', 'user.email=acceptance@example.invalid', 'commit', '--quiet', '-m', 'Synthetic isolated core rollout fixture'], source)
        run('git', ['archive', '--format=zip', '--output=' + archive, 'HEAD'], source)
        archives.push({ ...metadata, version, dist: { type: 'zip', url: 'file://' + archive }, fixtureRelease: json(path.join(source, 'resources/dist/asset-versions.json')).release })
    }
    assert.notEqual(archives[0].fixtureRelease, archives[1].fixtureRelease, 'Rebuilt source CSS did not produce a distinct sealed browser release.')
    const versions = JSON.parse(run(php, ['-r', `require 'vendor/autoload.php'; echo json_encode(['filament'=>Composer\\InstalledVersions::getPrettyVersion('filament/filament'),'testbench'=>Composer\\InstalledVersions::getPrettyVersion('orchestra/testbench')]);`], root))
    const configuration = {
        name: 'acceptance/core-rollout', type: 'project',
        require: { 'janczakb/filament-flex-fields': currentVersion, 'filament/filament': versions.filament, 'orchestra/testbench': versions.testbench },
        repositories: [{ type: 'package', package: archives.map(({ fixtureRelease, ...definition }) => definition) }],
        config: { 'allow-plugins': false, platform: { php: '8.3.33' } }, 'minimum-stability': 'stable', 'prefer-stable': true,
    }
    const saveConfiguration = version => {
        configuration.require['janczakb/filament-flex-fields'] = version
        fs.writeFileSync(path.join(application, 'composer.json'), JSON.stringify(configuration, null, 2))
    }
    saveConfiguration(currentVersion)
    run('composer', ['install', '--quiet', '--no-interaction', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress'])
    fs.mkdirSync(fixture, { recursive: true })
    fs.writeFileSync(path.join(fixture, '.startup-fixture'), '')
    const originalFixture = fs.readFileSync(path.join(root, 'scripts/core/fixtures/startup-panel.php'), 'utf8')
    const autoload = "require dirname(__DIR__, 3).'/vendor/autoload.php';"
    assert.equal(originalFixture.split(autoload).length, 2, 'Fixture autoload boundary changed.')
    fs.writeFileSync(path.join(fixture, 'startup-panel.php'), originalFixture.replace(autoload, "require dirname(__DIR__).'/vendor/autoload.php';"))
    fs.copyFileSync(path.join(root, 'scripts/core/fixtures/startup-panel.blade.php'), path.join(fixture, 'startup-panel.blade.php'))
    const render = mode => JSON.parse(run(php, [path.join(fixture, 'startup-panel.php'), mode, fixture]).trim())
    const verifyInstalled = expectedVersion => {
        assert.equal(fs.lstatSync(installed).isSymbolicLink(), false, 'Core package is not a real Composer dist installation.')
        assert.equal(fs.existsSync(path.join(installed, '.internal')), false, 'Private engineering material leaked into the Composer ZIP.')
        assert.equal(fs.existsSync(path.join(installed, 'node_modules')), false)
        assert.equal(fs.readFileSync(path.join(installed, 'VERSION'), 'utf8').trim(), expectedVersion)
        const info = JSON.parse(run(php, ['-r', `require 'vendor/autoload.php'; echo json_encode(['version'=>Composer\\InstalledVersions::getPrettyVersion('janczakb/filament-flex-fields'),'reference'=>Composer\\InstalledVersions::getReference('janczakb/filament-flex-fields')]);`]))
        assert.equal(info.version, expectedVersion)
        return info
    }
    let currentHtml, currentCookies
    const publish = version => {
        const installedVersion = verifyInstalled(version)
        const publication = render('publish')
        const seal = json(path.join(installed, 'resources/dist/asset-versions.json'))
        const expected = archives.find(archive => archive.version === version)
        assert.equal(seal.release, expected.fixtureRelease)
        for (const [relative, descriptor] of Object.entries(seal.assets)) {
            const published = path.join(publicRoot, 'filament-flex-fields-assets/core', seal.release, relative)
            assert.equal(digest(published), typeof descriptor === 'string' ? descriptor : descriptor.sha256 ?? descriptor.digest, 'Published artifact differs from the installed sealed dist: ' + relative)
        }
        const rendered = render('tabs')
        currentHtml = fs.readFileSync(path.join(fixture, 'tabs.html'), 'utf8')
        currentCookies = rendered.cookies
        phases.push({ version, release: seal.release, composer: installedVersion, publication, sealedArtifacts: Object.keys(seal.assets).length })
        return seal
    }
    const firstSeal = publish(currentVersion)
    server = createServer((request, response) => {
        const url = new URL(request.url, 'http://localhost')
        requests.push(url.pathname)
        if (url.pathname === '/cost') {
            const origin = `http://127.0.0.1:${server.address().port}`
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store', 'Set-Cookie': currentCookies })
            response.end(currentHtml.replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin)); return
        }
        if (url.pathname === '/favicon.ico') { response.writeHead(204); response.end(); return }
        let filename = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname))
        if (/^\/livewire-[a-z0-9]+\/livewire\.min\.js$/.test(url.pathname)) filename = path.join(application, 'vendor/livewire/livewire/dist/livewire.min.js')
        else if (!filename.startsWith(publicRoot + path.sep) || !fs.existsSync(filename) || !fs.realpathSync(filename).startsWith(fs.realpathSync(publicRoot) + path.sep)) filename = null
        if (!filename || !fs.statSync(filename).isFile()) { errors.push('Missing published artifact: ' + url.pathname); response.writeHead(404); response.end(); return }
        response.writeHead(200, { 'Content-Type': { '.js': 'text/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' }[path.extname(filename)] ?? 'application/octet-stream',
            'Cache-Control': url.pathname.startsWith('/filament-flex-fields-assets/') ? 'public, max-age=31536000, immutable' : 'no-store' })
        response.end(fs.readFileSync(filename))
    })
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve))
    browser = await launchCoreBrowser({ headless: true })
    const origin = `http://127.0.0.1:${server.address().port}`
    const open = async () => {
        const page = await browser.newPage()
        page.setDefaultTimeout(20000)
        page.on('pageerror', error => errors.push(error.message))
        page.on('requestfailed', request => errors.push(request.url() + ': ' + request.failure()?.errorText))
        await page.goto(origin + '/cost?case=tabs', { waitUntil: 'domcontentloaded' })
        await page.getByRole('tab', { name: 'Basics', exact: true }).waitFor({ state: 'visible' })
        navigations.set(page, 0)
        page.on('framenavigated', frame => {
            if (frame === page.mainFrame()) navigations.set(page, navigations.get(page) + 1)
        })
        assert.equal(await page.evaluate(() => !!document.querySelector('[data-fff-text-input]')?._fffFactoryReady), false)
        return page
    }
    const activate = async (page, seal, marker, value) => {
        await page.getByRole('tab', { name: 'FLEX details', exact: true }).click()
        const input = page.getByRole('textbox', { name: 'Tab message', exact: true })
        await input.waitFor({ state: 'visible' })
        await page.waitForFunction(() => document.querySelector('[data-fff-text-input]')?._fffFactoryReady === true)
        await input.fill(value)
        assert.equal(await input.inputValue(), value)
        const info = await page.evaluate(() => ({
            marker: getComputedStyle(document.querySelector('[data-fff-text-input]')).getPropertyValue('--fff-rollout-fixture').trim(),
            requests: performance.getEntriesByType('resource').map(entry => new URL(entry.name).pathname).filter(name => name.startsWith('/filament-flex-fields-assets/core/')),
        }))
        assert.equal(info.marker, marker)
        assert.ok(info.requests.some(name => name.endsWith('/flex-text-input.js')), 'Deferred field did not load its real module.')
        assert.ok(info.requests.every(name => name.startsWith('/filament-flex-fields-assets/core/' + seal.release + '/')), 'Open tab mixed incompatible browser releases.')
        return { valuePreserved: true, deferredModuleLoaded: true, requests: info.requests.length, marker: info.marker, release: seal.release }
    }
    const oldTab = await open()
    await oldTab.evaluate(() => { window.__rolloutDocument = 'original-document' })
    assert.equal(requests.filter(name => name.endsWith('/flex-text-input.js')).length, 0, 'Old tab warmed the supposedly deferred field.')
    saveConfiguration(nextVersion)
    run('composer', ['update', 'janczakb/filament-flex-fields', '--quiet', '--no-interaction', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress'])
    const nextSeal = publish(nextVersion)
    assert.notEqual(firstSeal.release, nextSeal.release)
    assert.equal(navigations.get(oldTab), 0, 'Old tab was navigated during upgrade.')
    assert.equal(await oldTab.evaluate(() => window.__rolloutDocument), 'original-document', 'Old document was replaced during upgrade.')
    const upgradeOld = await activate(oldTab, firstSeal, '', 'Old tab still works after update')
    const newTab = await open()
    const upgradeNew = await activate(newTab, nextSeal, '1', 'New tab uses rebuilt stylesheet')
    const nextDeferredTab = await open()
    await nextDeferredTab.evaluate(() => { window.__rolloutDocument = 'next-deferred-document' })
    assert.equal(await nextDeferredTab.evaluate(() => performance.getEntriesByType('resource').filter(entry => new URL(entry.name).pathname.endsWith('/flex-text-input.js')).length), 0, 'Rollback tab warmed its deferred field before rollback.')
    saveConfiguration(currentVersion)
    run('composer', ['update', 'janczakb/filament-flex-fields', '--quiet', '--no-interaction', '--no-scripts', '--no-plugins', '--prefer-dist', '--no-progress'])
    const rollbackSeal = publish(currentVersion)
    assert.equal(rollbackSeal.release, firstSeal.release, 'Rollback did not restore the exact original sealed browser release.')
    const rollbackOpen = await activate(nextDeferredTab, nextSeal, '1', 'B tab still works after rollback')
    assert.equal(navigations.get(nextDeferredTab), 0, 'B tab was navigated during rollback.')
    assert.equal(await nextDeferredTab.evaluate(() => window.__rolloutDocument), 'next-deferred-document', 'B document was replaced during rollback.')
    const rollbackTab = await open()
    const rollbackFresh = await activate(rollbackTab, firstSeal, '', 'Fresh tab restored A')
    assert.equal(await oldTab.evaluate(() => window.__rolloutDocument), 'original-document')
    assert.equal(navigations.get(oldTab), 0)
    assert.equal(await oldTab.getByRole('textbox', { name: 'Tab message', exact: true }).inputValue(), 'Old tab still works after update')
    assert.deepEqual(errors, [])
    console.log(JSON.stringify({ passed: true, engine: browserEngine, browser: await browser.version(),
        scope: 'Actual core Composer ZIP A→B→A with rebuilt fixture-only source CSS; same open documents and deferred native Filament tabs. Synthetic current-source revisions, not historical-version compatibility, provider CDN or multi-node deployment.',
        phases, upgradeOld, upgradeNew, rollbackOpen, rollbackFresh, sourcePreserved: true }, null, 2))
} catch (error) { failure = error }
finally {
    if (browser) await browser.close()
    if (server) await new Promise(resolve => server.close(resolve))
    assert.deepEqual(json(path.join(root, 'resources/dist/asset-versions.json')), originalSeal, 'Probe changed source release.')
    assert.equal(digest(path.join(root, 'resources/css/components/flex-text-input.css')), originalCss, 'Probe changed source CSS.')
    fs.rmSync(temporary, { recursive: true, force: true })
}
if (failure) throw failure
