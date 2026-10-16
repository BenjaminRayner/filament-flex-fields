globalThis.requestAnimationFrame ??= (callback) => setTimeout(callback, 0)
import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import {
    resetCountryRegistryCache,
    resolveCountriesFromRegistry,
} from '../../resources/js/core/country-registry.js'

function registryTemplate(payload) {
    return {
        tagName: 'TEMPLATE',
        content: {textContent: JSON.stringify(payload)},
        innerHTML: JSON.stringify(payload),
        textContent: JSON.stringify(payload),
    }
}

describe('country registry locale names', () => {
    it('uses extra locale names when the field locale differs from the registry', async () => {
        const originalDocument = globalThis.document

        globalThis.document = {
            querySelectorAll() {
                return [registryTemplate({
                    locale: 'en',
                    pools: {
                        iso: {
                            PL: { c: 'PL', n: 'Poland', d: '+48', f: 'https://example.test/pl.svg' },
                        },
                    },
                    locale_names: {
                        pl: {
                            iso: {
                                PL: 'Polska',
                            },
                        },
                    },
                })]
            },
            addEventListener() {},
        }

        resetCountryRegistryCache()

        const english = await resolveCountriesFromRegistry({ pool: 'iso', locale: 'en' })
        const polish = await resolveCountriesFromRegistry({ pool: 'iso', locale: 'pl' })

        assert.equal(english[0].name, 'Poland')
        assert.equal(polish[0].name, 'Polska')
        assert.equal(polish[0].code, 'PL')

        globalThis.document = originalDocument
        resetCountryRegistryCache()
    })
})


it('keeps explicit empty and extended country filters exact', async () => {
    const originalDocument = globalThis.document
    globalThis.document = {
        querySelectorAll() {
            return [registryTemplate({locale: 'en', pools: {
                'country-extended': {
                    PL: {c: 'PL', n: 'Poland', d: '+48', f: '/local/pl.svg'},
                    XK: {c: 'XK', n: 'Kosovo', d: '+383', f: '/local/xk.svg'},
                    IC: {c: 'IC', n: 'Canary Islands', d: '', f: '/local/ic.svg'},
                },
            }, filters: {market: ['PL', 'XK'], empty: []}})]
        },
        addEventListener() {},
    }
    resetCountryRegistryCache()
    try {
        assert.deepEqual(await resolveCountriesFromRegistry({pool: 'country-extended', allowedCountryCodes: []}), [])
        assert.deepEqual(await resolveCountriesFromRegistry({pool: 'country-extended', countryFilterKey: 'empty'}), [])
        const markets = await resolveCountriesFromRegistry({pool: 'country-extended', countryFilterKey: 'market'})
        assert.deepEqual(markets.map(country => country.code), ['PL', 'XK'])
        assert.equal(markets[1].flag_url, '/local/xk.svg')
    } finally {
        globalThis.document = originalDocument
        resetCountryRegistryCache()
    }
})
