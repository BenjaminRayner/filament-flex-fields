import { execFile } from 'node:child_process';
import { availableParallelism } from 'node:os';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';
import { collectCssMetrics, fileSizeMetrics, writeBundleMetrics } from './bundle-metrics.mjs';
import { withComponentOutputLock } from './assets/component-output-lock.mjs';
import { pruneStylesheetOutput } from './assets/prune-stylesheet-output.mjs';
import { createCoreCssFoundation } from './assets/css-core-foundation.mjs';

const packageRoot = path.resolve(import.meta.dirname, '..');
const runCompiler = promisify(execFile);
const cliManifestPath = fileURLToPath(import.meta.resolve('@tailwindcss/cli/package.json'));
const cliManifest = JSON.parse(fs.readFileSync(cliManifestPath, 'utf8'));
const cliPath = path.resolve(path.dirname(cliManifestPath), cliManifest.bin.tailwindcss);
const configuredWorkers = process.env.FFF_CSS_BUILD_WORKERS;
if (configuredWorkers !== undefined && !/^[1-8]$/.test(configuredWorkers)) {
    throw new Error('FFF_CSS_BUILD_WORKERS must be an integer from 1 to 8.');
}
const parallelism = configuredWorkers === undefined ? Math.min(4, availableParallelism()) : Number(configuredWorkers);
await withComponentOutputLock(packageRoot, async lease => {
    const entriesRoot = path.join(packageRoot, 'resources/css/entries');
    const distRoot = path.join(packageRoot, 'resources/dist/css');
    const coreOutput = path.join(distRoot, 'core.css');

    fs.mkdirSync(distRoot, { recursive: true });
    const foundation = createCoreCssFoundation(fs.readFileSync(coreOutput));
    let sharedDeclarations = 0, sharedProperties = 0;

    function buildScopedEntrySource(entrySource) {
        const scopedLines = entrySource
            .split('\n')
            .filter((line) => {
                const trimmed = line.trim();

                return ! trimmed.includes('utilities-baseline.css')
                    && ! /^@import\s+["']\.\.\/base\.css["'];?\s*$/.test(trimmed);
            });

        return [
            '@reference "../utilities-baseline.css";',
            '@reference "../base.css";',
            '',
            scopedLines.join('\n').trim(),
            '',
        ].join('\n');
    }

    const entries = fs.readdirSync(entriesRoot).filter((file) => (
        file.endsWith('.css') && ! file.startsWith('.scoped-build.') && file !== '.foundation.build.css'
    )).sort();

    const requested = process.argv.slice(2);
    for (const name of requested) {
        if (!entries.includes(`${name}.css`)) throw new Error(`Unknown component stylesheet: ${name}`);
    }
    const selectedEntries = requested.length ? entries.filter(entry => requested.includes(entry.slice(0, -4))) : entries;
    async function buildEntry(entry) {
        const name = entry.replace(/\.css$/, '');
        const input = path.join(entriesRoot, entry);
        const scopedInput = path.join(entriesRoot, `.scoped-build.${entry}`);
        const output = path.join(distRoot, `${name}.css`);

        fs.writeFileSync(scopedInput, buildScopedEntrySource(fs.readFileSync(input, 'utf8')));

        try {
            await runCompiler(process.execPath, [cliPath, '-i', scopedInput, '-o', output, '--minify'], {
                cwd: packageRoot,
                timeout: 60000,
                maxBuffer: 1024 * 1024,
            });
            const pruned = foundation.prune(fs.readFileSync(output), `${name}.css`);
            fs.writeFileSync(output, pruned.code);
            sharedDeclarations += pruned.removedDeclarations;
            sharedProperties += pruned.removedProperties;
        } catch (error) {
            const stderr = error.stderr?.toString?.() ?? '';
            const stdout = error.stdout?.toString?.() ?? '';

            throw new Error(`Failed to build ${entry}:\n${stderr || stdout || error.message}`);
        } finally {
            if (fs.existsSync(scopedInput)) {
                fs.unlinkSync(scopedInput);
            }
        }
    }

    // Hold the output lease until every running child has completed its cleanup.
    // A failure stops new work; it never leaves unawaited compilers writing later.
    let cursor = 0, failure;
    const workers = Array.from({ length: Math.min(parallelism, selectedEntries.length) }, async () => {
        while (!failure && cursor < selectedEntries.length) {
            const entry = selectedEntries[cursor++];
            try { await buildEntry(entry); }
            catch (error) { failure ??= error; }
        }
    });
    const results = await Promise.allSettled(workers);
    for (const result of results) {
        if (result.status === 'rejected') failure ??= result.reason;
    }
    if (failure) throw failure;

    if (!requested.length) {
        const cleanup = pruneStylesheetOutput(packageRoot, selectedEntries, lease);
        console.log(`Removed ${cleanup.removed.length} obsolete generated CSS outputs.`);
    }

    // Reject optimized output that would silently lose blur in standard browsers.
    await import('./assets/audit-css-browser-compatibility.mjs');

    const cssMetrics = collectCssMetrics(distRoot);

    if (fs.existsSync(coreOutput)) {
        cssMetrics['core.css'] = fileSizeMetrics(coreOutput);
    }

    if (fs.existsSync(path.join(distRoot, 'playground.css'))) {
        cssMetrics['playground.css'] = fileSizeMetrics(path.join(distRoot, 'playground.css'));
    }

    writeBundleMetrics(packageRoot, { css: cssMetrics });

    console.log(`Built ${selectedEntries.length} scoped component CSS bundles with ${Math.min(parallelism, selectedEntries.length)} compiler workers.`);
    console.log(`Core owns ${sharedDeclarations} shared Tailwind initializers and ${sharedProperties} matching property registrations removed from component output.`);
});
