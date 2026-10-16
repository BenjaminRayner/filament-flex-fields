/** SDK clustering may drop GeoJSON ids: the render-only property preserves selection identity. */
export function mapRenderCollection(collection) {
    return { type: 'FeatureCollection', features: (collection?.features ?? []).map(feature => ({
        ...feature, properties: { ...feature.properties, __fffIdentity: String(feature.id) },
    })) }
}

/** Hide the selected DOM marker without removing its point from cluster input/counts. */
export function unselectedPointFilter(selectedId, clustered = false) {
    const filter = ['all', ['==', ['geometry-type'], 'Point'], ['!', ['has', 'point_count']]]
    if (!clustered && selectedId !== null && selectedId !== undefined) filter.push(['!=', ['get', '__fffIdentity'], String(selectedId)])
    return filter
}
