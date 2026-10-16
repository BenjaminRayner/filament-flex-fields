import assert from 'node:assert/strict'
import { chromium, firefox, webkit } from '@playwright/test'

/** All core browser proofs select the same real engine; emulation is never a device certificate. */
export const browserEngine = process.env.FFF_BROWSER ?? 'chromium'
assert.ok(['chromium', 'firefox', 'webkit'].includes(browserEngine), 'Unknown core browser profile')

export async function launchCoreBrowser(options = { headless: true }) {
    const type = { chromium, firefox, webkit }[browserEngine]
    const endpoint = process.env.FFF_BROWSER_WS
    if (endpoint) {
        const url = new URL(endpoint)
        assert.ok(['ws:', 'wss:'].includes(url.protocol) && !url.username && !url.password, 'Invalid browser server endpoint')
        return type.connect(endpoint, { exposeNetwork: '<loopback>', timeout: 30000 })
    }
    return type.launch(options)
}
