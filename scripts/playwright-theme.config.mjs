import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: '../tests/e2e',
    testMatch: 'field-smoke-theme.spec.mjs',
    outputDir: '../build/theme-test-results',
    reporter: 'list',
    projects: ['chromium', 'firefox', 'webkit'].map(browserName => ({
        name: browserName,
        use: { browserName },
    })),
})
