import fs from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';

export function fileSizeMetrics(filePath) {
    const buffer = fs.readFileSync(filePath);

    return {
        bytes: buffer.length,
        kb: Number((buffer.length / 1024).toFixed(2)),
        gzipKb: Number((gzipSync(buffer).length / 1024).toFixed(2)),
    };
}

export function collectCssMetrics(cssRoot) {
    const metrics = {};

    for (const file of fs.readdirSync(cssRoot).filter((entry) => entry.endsWith('.css'))) {
        metrics[file] = fileSizeMetrics(path.join(cssRoot, file));
    }

    return metrics;
}

export function collectJsMetrics(componentsRoot) {
    const metrics = {};
    const manifestPath = path.join(componentsRoot, 'alpine-manifest.json');
    // Old content-addressed chunks intentionally survive a build for open pages.
    // Measure the current deploy graph, never the accumulated release archive.
    const manifest = fs.existsSync(manifestPath)
        ? JSON.parse(fs.readFileSync(manifestPath, 'utf8')) : null;
    if (manifest && (!Array.isArray(manifest.__entries__) || !Array.isArray(manifest.__shared_chunks__))) {
        throw new Error('Invalid Alpine manifest: bundle metrics require entries and shared chunks.');
    }
    const files = manifest
        ? [...new Set([...manifest.__entries__.map((entry) => `${entry}.js`), ...manifest.__shared_chunks__])]
        : fs.readdirSync(componentsRoot).filter((entry) => entry.endsWith('.js'));

    for (const file of files) {
        if (path.basename(file) !== file || !file.endsWith('.js')) throw new Error(`Invalid bundle filename: ${file}`);
        metrics[file] = fileSizeMetrics(path.join(componentsRoot, file));
    }

    return metrics;
}

export function readBundleMetrics(metricsPath) {
    if (! fs.existsSync(metricsPath)) {
        return null;
    }

    return JSON.parse(fs.readFileSync(metricsPath, 'utf8'));
}

export function writeBundleMetrics(packageRoot, partialMetrics) {
    const metricsPath = path.join(packageRoot, 'resources/dist/bundle-metrics.json');
    const existing = readBundleMetrics(metricsPath) ?? {
        generatedAt: null,
        css: {},
        js: {},
    };

    const metrics = {
        generatedAt: new Date().toISOString(),
        css: partialMetrics.css ?? existing.css,
        js: partialMetrics.js ?? existing.js,
    };

    fs.mkdirSync(path.dirname(metricsPath), { recursive: true });
    fs.writeFileSync(metricsPath, `${JSON.stringify(metrics, null, 2)}\n`);

    return metricsPath;
}
