/** The PHP catalog supplies both kind mappings and versioned, declared asset URLs. */
export function fileIconUrl(file, catalog = {}) {
    const mime = String(file.type ?? '').split(';', 1)[0].trim().toLowerCase()
    let kind = catalog.mimeTypes?.[mime]
    if (!kind) kind = Object.entries(catalog.mimePrefixes ?? {}).find(([prefix]) => mime.startsWith(prefix))?.[1]
    kind ??= catalog.extensions?.[String(file.extension ?? '').replace(/^\./, '').toLowerCase()] ?? 'attachment'
    return catalog.urls?.[kind] ?? catalog.urls?.attachment ?? null
}
