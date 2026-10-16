import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import {
    collectSocialLinkRowErrors,
    dehydrateSocialLinksState,
    firstSocialLinkValidationError,
    formatSocialLinkUrl,
    hasSocialLinkValidationErrors,
    normalizeSocialLinksState,
    validateSocialLink,
} from '../../resources/js/support/social-link-validation.js'

const platformDefinitions = [
    {
        value: 'instagram',
        label: 'Instagram',
        placeholder: 'https://instagram.com/username',
        hosts: ['instagram.com', 'instagr.am'],
    },
    {
        value: 'mastodon',
        label: 'Mastodon',
        placeholder: 'https://mastodon.social/@username',
        hosts: ['mastodon.social'],
    },
    {
        value: 'website',
        label: 'Website',
        placeholder: 'https://example.com',
        hosts: [],
    },
]

describe('social-link-validation', () => {
    it('validates platform hostnames from dynamic definitions', () => {
        assert.equal(validateSocialLink('instagram', 'https://instagram.com/user', platformDefinitions), null)
        assert.equal(validateSocialLink('instagram', 'https://example.com/user', platformDefinitions), 'platform_mismatch')
        assert.equal(validateSocialLink('website', 'https://example.com', platformDefinitions), null)
        assert.equal(validateSocialLink('mastodon', 'https://mastodon.social/@user', platformDefinitions), null)
        assert.equal(validateSocialLink('mastodon', 'https://example.com/@user', platformDefinitions), 'platform_mismatch')
    })

    it('keeps empty-url rows during normalization and strips them on dehydrate', () => {
        assert.deepEqual(
            normalizeSocialLinksState([
                { platform: 'instagram', url: '' },
                { platform: 'github', url: 'https://github.com/laravel' },
            ]),
            [
                { platform: 'instagram', url: '' },
                { platform: 'github', url: 'https://github.com/laravel' },
            ],
        )

        assert.deepEqual(
            dehydrateSocialLinksState([
                { platform: 'instagram', url: '' },
                { platform: 'github', url: 'https://github.com/laravel' },
            ]),
            [
                { platform: 'github', url: 'https://github.com/laravel' },
            ],
        )
    })

    it('collects row errors and exposes submit-guard helpers', () => {
        const links = [
            { platform: 'instagram', url: '' },
            { platform: 'mastodon', url: 'https://example.com/@user' },
        ]

        const errors = collectSocialLinkRowErrors(links, platformDefinitions)

        assert.equal(hasSocialLinkValidationErrors(errors), true)
        assert.equal(firstSocialLinkValidationError(errors), 'required')
    })

    it('auto-formats urls without an existing scheme', () => {
        assert.equal(formatSocialLinkUrl(' example.com '), 'https://example.com')
        assert.equal(formatSocialLinkUrl('https://instagram.com/user'), 'https://instagram.com/user')
        assert.equal(formatSocialLinkUrl(''), '')
    })
})


it('does not admit a built-in platform outside an explicit choice set', () => {
    assert.equal(validateSocialLink('github', 'https://github.com/a', platformDefinitions), 'unknown_platform')
    assert.equal(validateSocialLink('website', 'https://example.com', new Map()), 'unknown_platform')
})

it('rejects unsafe schemes, embedded credentials and overlong URLs', () => {
    for (const value of ['javascript:alert(1)', 'https://trusted.example@evil.example', 'https://example.com/' + 'x'.repeat(4096)]) {
        assert.equal(validateSocialLink('website', value, platformDefinitions), 'invalid_url')
    }
    assert.equal(formatSocialLinkUrl('javascript:alert(1)'), 'javascript:alert(1)')
    assert.equal(formatSocialLinkUrl('example.com:8080/path'), 'https://example.com:8080/path')
})
