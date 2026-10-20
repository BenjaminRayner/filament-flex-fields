import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import fs from 'node:fs'
import path from 'node:path'

const exactKeys = (value, keys, label) => {
    assert.ok(value && typeof value === 'object' && !Array.isArray(value), label + ' must be an object.')
    assert.deepEqual(Object.keys(value).sort(), [...keys].sort(), label + ' fields are missing or unknown.')
}
const version = (value, pattern, label) => {
    assert.ok(typeof value === 'string' && value.length <= 100 && pattern.test(value), label + ' version is missing or invalid.')
    return value
}

/** Retain each executed CI axis; an engine label or lockfile is not an observed runtime version. */
export function verifyAcceptanceEnvironment(environment, engine = null) {
    exactKeys(environment, ['node', 'platform', 'architecture', 'php', 'flexVersion', 'packages', 'browser'], 'Acceptance environment')
    version(environment.node, /^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/, 'Node')
    version(environment.php, /^\d+\.\d+\.\d+[0-9A-Za-z.+_-]*$/, 'PHP')
    version(environment.flexVersion, /^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/, 'FLEX')
    for (const name of ['platform', 'architecture']) {
        assert.ok(typeof environment[name] === 'string' && /^[a-zA-Z0-9_-]{1,32}$/.test(environment[name]), 'Invalid acceptance ' + name + '.')
    }
    exactKeys(environment.packages, ['filament/filament', 'livewire/livewire'], 'Installed package versions')
    for (const [name, value] of Object.entries(environment.packages)) {
        version(value, /^(?:v?\d+(?:\.(?:\d+|x)){1,3}(?:[-+][0-9A-Za-z.-]+)?|dev-[0-9A-Za-z._/-]+)$/, name)
    }
    if (engine === null) {
        assert.equal(environment.browser, null, 'Non-browser acceptance must not claim a browser runtime.')
    } else {
        exactKeys(environment.browser, ['engine', 'version', 'connection'], 'Observed browser')
        assert.equal(environment.browser.engine, engine, 'Observed browser engine differs from its acceptance phase.')
        version(environment.browser.version, /^\d+(?:\.\d+)*[0-9A-Za-z.+_-]*$/, 'Browser')
        assert.ok(['local', 'remote'].includes(environment.browser.connection), 'Observed browser connection is missing or invalid.')
    }
    return structuredClone(environment)
}

/** Read installed Composer metadata without booting Laravel or executing application providers. */
export async function collectAcceptanceEnvironment({ root, php, engine = null }) {
    const phpCode = `require $argv[1]; echo json_encode([
        'php' => PHP_VERSION,
        'packages' => [
            'filament/filament' => Composer\\InstalledVersions::getPrettyVersion('filament/filament'),
            'livewire/livewire' => Composer\\InstalledVersions::getPrettyVersion('livewire/livewire'),
        ],
    ], JSON_THROW_ON_ERROR);`
    const installed = JSON.parse(execFileSync(php, ['-r', phpCode, path.join(root, 'vendor/autoload.php')], {
        cwd: root, encoding: 'utf8', timeout: 30000, maxBuffer: 65536,
    }))
    let observed = null
    if (engine !== null) {
        const { browserEngine, launchCoreBrowser } = await import('../compatibility/browser-profile.mjs')
        assert.equal(browserEngine, engine, 'Browser environment must select the phase engine before collection.')
        const browser = await launchCoreBrowser({ headless: true })
        try {
            observed = { engine: browserEngine, version: browser.version(), connection: process.env.FFF_BROWSER_WS ? 'remote' : 'local' }
        } finally {
            await browser.close()
        }
    }
    return verifyAcceptanceEnvironment({ node: process.version, platform: process.platform, architecture: process.arch,
        php: installed.php, flexVersion: fs.readFileSync(path.join(root, 'VERSION'), 'utf8').trim(),
        packages: installed.packages, browser: observed }, engine)
}
