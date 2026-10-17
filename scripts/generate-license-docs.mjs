import { readFile, writeFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')

export function parseLicense(source) {
    const version = source.match(/SOURCE-AVAILABLE & COMMERCIAL LICENSE — VERSION (\d+\.\d+)/)?.[1]
    const effective = source.match(/^\s*Effective: (\d{1,2} [A-Za-z]+ \d{4})$/m)?.[1]
    const clarification = source.match(/^\s*GitHub platform-rights clarification: (\d{1,2} [A-Za-z]+ \d{4})$/m)?.[1]
    const headings = [...source.matchAll(/^={20,}\nSECTION (\d+) — ([^\n]+)\n={20,}\n/gm)]
    if (!version || !effective || headings.length !== 10 || !/\nEND OF LICENSE\n={20,}\n$/.test(source)) {
        throw new Error('LICENSE must declare its version, effective date and ten complete sections.')
    }
    const sections = headings.map((heading, index) => {
        if (Number(heading[1]) !== index + 1) throw new Error('License sections must be ordered 1–10.')
        const body = source.slice(heading.index + heading[0].length, headings[index + 1]?.index ?? source.length)
        if (!body.trim()) throw new Error(`Empty license section ${heading[1]}.`)
        return { number: index + 1, title: heading[2].toLowerCase(), body }
    })
    return { version, effective, clarification, preamble: source.slice(0, headings[0].index), sections }
}

export function validatePlanSummaries(source, summaries) {
    const prices = [...source.matchAll(/^5\.[2-5]  ([A-Z /]+) — [^\n]*USD ([0-9]+(?:,[0-9]{3})*)/gm)]
    if (prices.length !== 4) throw new Error('LICENSE must declare all four commercial price points.')
    const names = ['Single Product', 'Unlimited Company', 'Enterprise Group', 'Custom / OEM']
    const expected = prices.map(match => `$${match[2]}`)
    for (const [path, summary] of summaries) {
        const text = summary.replace(/&#36;/g, '$')
        for (const [index, name] of names.entries()) {
            let actual
            if (path === 'COMMERCIAL.md') {
                actual = text.split('\n').find(line => line.startsWith('| **Current price**'))?.split('|')[index + 2]
            } else if (path === 'docs/index.md' || path === 'docs/plans.md') {
                const start = text.indexOf(`<h3>${name}`)
                const rest = text.slice(start)
                actual = name === 'Custom / OEM'
                    ? rest.match(/^<h3>Custom \/ OEM <span>([^<]+)/)?.[1]
                    : rest.match(/<p className="fff-landing-price">([^<]+)/)?.[1]
            } else {
                actual = text.split('\n').find(line => line.startsWith(`| **${name}`) || line.startsWith(`| ${name}`))
            }
            if (actual?.match(/\$[0-9]+(?:,[0-9]{3})*/)?.[0] !== expected[index]) {
                throw new Error(`${path}: ${name} price does not match LICENSE.`)
            }
        }
    }
}

function legalParagraphs(source) {
    // Only layout changes: unwrap print-width lines and retain every legal word.
    const text = source.replace(/^={20,}\n/gm, '').replace(/\n[ \t]+(?=\([a-z]\) )/g, '\n\n')
    return text.trim().split(/\n\s*\n/).map(paragraph => paragraph.split('\n').map(line => line.trim()).join(' ')
        .replace(/\\/g, '\\\\').replace(/([`*_[\]])/g, '\\$1')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/\{/g, '&#123;').replace(/\}/g, '&#125;'))
        .join('\n\n')
}

export function renderLicenseDocs(source) {
    const license = parseLicense(source)
    const body = [legalParagraphs(license.preamble), ...license.sections.map(section =>
        `## Section ${section.number}: ${section.title}\n\n${legalParagraphs(section.body)}`)].join('\n\n')
    return `---
title: "License terms"
description: "The complete FLEX Fields source-available and commercial License v${license.version}, effective ${license.effective}."
icon: "scale"
---

<Badge color="gray" shape="pill">Version ${license.version} · Effective ${license.effective}</Badge>

These are the complete terms for FLEX Fields. For help choosing a plan, read the
[licensing overview](/docs/licensing), [plan comparison](/docs/plans) or
[licensing questions](/docs/licensing-faq). Your accepted License Confirmation
identifies your purchased scope; expressly agreed Custom terms can vary it.

[Read the official license](https://github.com/janczakb/filament-flex-fields/blob/main/LICENSE)
· [Open or save the plain-text license](https://raw.githubusercontent.com/janczakb/filament-flex-fields/main/LICENSE)

${license.clarification ? `The ${license.clarification} clarification acknowledges GitHub platform permissions.
Prices, commercial scope and previously issued grants are unchanged.\n\n` : ''}${body}
`
}

async function main() {
    if (process.argv.slice(2).some(argument => argument !== '--check')) throw new Error('Use --check or no arguments.')
    const source = await readFile(resolve(root, 'LICENSE'), 'utf8')
    const version = parseLicense(source).version.split('.')[0]
    const paths = ['COMMERCIAL.md', 'README.md', 'docs/index.md', 'docs/plans.md', 'docs/form-os-one-pager.md']
    const summaries = new Map(await Promise.all(paths.map(async path => [path, await readFile(resolve(root, path), 'utf8')])))
    validatePlanSummaries(source, summaries)
    const outputs = new Map([
        ['docs/license-terms.md', renderLicenseDocs(source)],
        [`art/flex-fields-license-v${version}.txt`, source],
    ])
    for (const [path, expected] of outputs) {
        const destination = resolve(root, path)
        if (process.argv.includes('--check')) {
            const actual = await readFile(destination, 'utf8')
            if (actual !== expected) throw new Error(`${path} differs from LICENSE. Run npm run docs:license.`)
        } else {
            await writeFile(destination, expected)
        }
    }
    console.log(`${process.argv.includes('--check') ? 'PASS' : 'Generated'}: license text copies and five customer pricing summaries match LICENSE.`)
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    main().catch(error => { console.error(error.message); process.exitCode = 1 })
}
