import { readFile, realpath, writeFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const versionPattern = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/

export function parseChangelog(source) {
    // Ignore example headings inside fenced code while retaining source offsets.
    let fence = null
    const visibleSource = source.split(/(?<=\n)/).map(line => {
        const marker = line.match(/^\s{0,3}(`{3,}|~{3,})(.*)$/m)
        const hidden = fence !== null || marker !== null
        if (marker) {
            if (fence === null) fence = { character: marker[1][0], length: marker[1].length }
            else if (marker[1][0] === fence.character && marker[1].length >= fence.length && marker[2].trim() === '') fence = null
        }
        return hidden ? line.replace(/[^\r\n]/g, ' ') : line
    }).join('')
    const headings = [...visibleSource.matchAll(/^## \[([^\]\r\n]+)\](?: - ([^\r\n]+))?\r?$/gm)]
    if (headings.length === 0) throw new Error('CHANGELOG.md contains no release entries.')

    const seen = new Set()
    return headings.flatMap((heading, index) => {
        const [, version, date] = heading
        if (seen.has(version)) throw new Error(`Duplicate changelog version: ${version}`)
        seen.add(version)

        if (version !== 'Unreleased' && !versionPattern.test(version)) {
            throw new Error(`Invalid changelog version: ${version}`)
        }

        const pending = version === 'Unreleased' || date === 'Unreleased'
        if (!pending && (!/^\d{4}-\d{2}-\d{2}$/.test(date ?? '')
            || !Number.isFinite(Date.parse(`${date}T00:00:00Z`))
            || new Date(`${date}T00:00:00Z`).toISOString().slice(0, 10) !== date)) {
            throw new Error(`Missing or invalid release date for ${version}`)
        }
        if (version === 'Unreleased' && date && date !== 'Unreleased') {
            throw new Error('Unreleased entries must not have a publication date.')
        }

        const body = source.slice(heading.index + heading[0].length, headings[index + 1]?.index ?? source.length).trim()
        if (!body && version === 'Unreleased') return []
        if (!body) throw new Error(`Empty changelog entry: ${version}`)
        if (body.includes('.internal/')) throw new Error(`Internal report reference in public release ${version}`)

        return [{ version, date, pending, body, major: version === 'Unreleased' ? null : Number(version.split('.')[0]) }]
    })
}

export function markdownForDocs(markdown) {
    let fence = null
    return markdown.split('\n').map(line => {
        const marker = line.match(/^\s{0,3}(`{3,}|~{3,})(.*)$/)
        if (marker) {
            if (fence === null) fence = { character: marker[1][0], length: marker[1].length }
            else if (marker[1][0] === fence.character && marker[1].length >= fence.length && marker[2].trim() === '') fence = null
            return line
        }
        if (fence !== null) return line

        // Keep code spans literal while preventing Markdown text from becoming JSX.
        return line.split(/(`+[^`]*`+)/g).map((part, index) => index % 2 ? part : part
            .replace(/\]\(docs\/([^\s)]+?)\.(?:mdx|md)(#[^\s)]*)?\)/g, '](/docs/$1$2)')
            .replace(/\]\((README\.md|COMPONENTS\.md|SUPPORT\.md|docs\.json)(#[^\s)]*)?\)/g, '](https://github.com/janczakb/filament-flex-fields/blob/main/$1$2)')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\{/g, '&#123;')
            .replace(/\}/g, '&#125;')
        ).join('')
    }).join('\n')
}

function renderEntry(entry) {
    const label = entry.version === 'Unreleased' ? 'Upcoming changes' : `v${entry.version}`
    const description = entry.pending ? 'In development' : entry.date
    const tags = [...entry.body.matchAll(/^### (.+)$/gm)].map(match => match[1])
        .filter(tag => ['Added', 'Changed', 'Fixed', 'Security', 'Upgrade notes'].includes(tag))
    const rss = {
        title: `FLEX Fields ${entry.version}${entry.pending ? ' — In development' : ''}`,
        description: entry.pending
            ? `Upcoming changes in FLEX Fields ${entry.version}. This version has not yet been publicly released.`
            : `Changes, improvements and fixes in FLEX Fields ${entry.version}.`,
        ...(!entry.pending ? { date: entry.date } : {}),
    }

    return `<Update label=${JSON.stringify(label)} description=${JSON.stringify(description)} tags={${JSON.stringify([...new Set(tags)])}} rss={${JSON.stringify(rss)}}>\n\n${markdownForDocs(entry.body)}\n\n</Update>`
}

export function generatePages(source) {
    const entries = parseChangelog(source)
    const latest = entries.find(entry => entry.major !== null)
    if (!latest) throw new Error('CHANGELOG.md needs at least one versioned entry.')

    const current = entries.filter(entry => entry.major === null || entry.major === latest.major)
    const archive = entries.filter(entry => entry.major !== null && entry.major !== latest.major)
    const latestReleased = entries.find(entry => !entry.pending)
    const generated = '{/* Generated from CHANGELOG.md by scripts/generate-changelog-docs.mjs. Do not edit this page directly. */}'
    const frontmatter = (title, description, icon) => `---\ntitle: ${JSON.stringify(title)}\ndescription: ${JSON.stringify(description)}\nicon: ${JSON.stringify(icon)}\nrss: true\n---\n\n${generated}`
    const badge = latest.pending ? `Next version · ${latest.version} · In development` : `Latest recorded version · ${latest.version}`
    const status = latest.pending
        ? `Version ${latest.version} is being prepared and has not yet been publicly released.${latestReleased ? ` The latest recorded release is ${latestReleased.version}.` : ''}`
        : 'Filter the timeline by change type. Subscribe with RSS on the publicly hosted documentation.'

    return new Map([
        ['docs/changelog.mdx', `${frontmatter('Changelog', 'New components, improvements and fixes in each FLEX Fields release.', 'newspaper')}\n\n<Badge color="purple" shape="pill">${badge}</Badge>\n\n${status}\n\n${current.map(renderEntry).join('\n\n')}\n\n<CardGroup cols={2}>\n  <Card title="Updating FLEX Fields" icon="circle-arrow-up" href="/docs/updating">Review update steps and migration requirements.</Card>\n  <Card title="Earlier releases" icon="archive" href="/docs/changelog-archive">Browse the release archive.</Card>\n</CardGroup>\n\n[Complete change record on GitHub](https://github.com/janczakb/filament-flex-fields/blob/main/CHANGELOG.md)\n`],
        ['docs/changelog-archive.md', `${frontmatter('Release archive', 'Earlier FLEX Fields releases and their original change records.', 'archive')}\n\nThese notes describe earlier releases at their release dates. For current rights and prices, see [Licensing](/docs/licensing) and [Plans](/docs/plans).\n\n[Back to the current changelog](/docs/changelog)\n\n${archive.length ? archive.map(renderEntry).join('\n\n') : 'No earlier releases are recorded yet.'}\n`],
    ])
}

async function main() {
    const args = process.argv.slice(2)
    if (args.some(arg => arg !== '--check')) throw new Error('Usage: node scripts/generate-changelog-docs.mjs [--check]')
    const check = args.includes('--check')
    const pages = generatePages(await readFile(resolve(packageRoot, 'CHANGELOG.md'), 'utf8'))
    const stale = []

    for (const [name, content] of pages) {
        const path = resolve(packageRoot, name)
        let existing
        try { existing = await readFile(path, 'utf8') } catch (error) {
            if (error.code !== 'ENOENT') throw error
        }
        if (existing === content) continue
        if (check) stale.push(name)
        else {
            await writeFile(path, content)
            console.log(`Generated ${name}`)
        }
    }

    if (stale.length) throw new Error(`Changelog documentation is stale: ${stale.join(', ')}. Run npm run docs:changelog and commit the generated pages.`)
    if (check) console.log('Changelog documentation matches CHANGELOG.md.')
}

const invokedPath = process.argv[1] ? await realpath(process.argv[1]).catch(() => null) : null
if (invokedPath === fileURLToPath(import.meta.url)) {
    main().catch(error => {
        console.error(error.message)
        process.exitCode = 1
    })
}
