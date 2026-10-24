import { createPromotionDeck } from '../catalog/promotion-deck.js'
import { createSponsorRail } from '../catalog/sponsor-rail.js'
import { observeCatalogSearch } from '../catalog/sticky-search.js'

/** Immutable search index and one result set per filter revision; no requests or card recreation. */
export default function flexCatalogComponent({ entries = [] } = {}) {
    const normalize = value => String(value ?? '').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/ł/g, 'l')
    const index = entries.map(entry => ({ ...entry, categories: Array.isArray(entry.categories) ? [...entry.categories] : [], kinds: Array.isArray(entry.kinds) ? [...entry.kinds] : [], search: normalize(entry.search) }))
    let disposeStickySearch = null
    let revision = null
    let matching = new Set(index.map(entry => entry.id))
    return {
        query: '', category: 'all', collection: 'all', componentKind: 'all', searchStuck: false,
        init() {
            disposeStickySearch = observeCatalogSearch(this.$el, stuck => { this.searchStuck = stuck })
        },
        destroy() { disposeStickySearch?.(); disposeStickySearch = null },
        sponsorRail() { return createSponsorRail() },
        promotionDeck(ids, options) { return createPromotionDeck(ids, options) },
        get isFiltering() { return Boolean(this.query.trim() || this.category !== 'all' || this.collection !== 'all' || this.componentKind !== 'all') },
        matchingIds() {
            const key = JSON.stringify([this.query, this.category, this.collection, this.componentKind])
            if (key !== revision) {
                const tokens = normalize(this.query.slice(0, 256)).trim().split(/\s+/).filter(Boolean).slice(0, 16)
                matching = new Set(index.filter(entry =>
                    (this.category === 'all' || entry.categories.includes(this.category))
                    && (this.collection !== 'installed' || entry.installed)
                    && (this.collection !== 'community' || !entry.official)
                    && (this.componentKind === 'all' || entry.kinds.includes(this.componentKind))
                    && tokens.every(token => entry.search.includes(token)),
                ).map(entry => entry.id))
                revision = key
            }
            return matching
        },
        visible(id) { return this.matchingIds().has(id) },
        groupVisible(ids) { const matches = this.matchingIds(); return ids.some(id => matches.has(id)) },
        get resultCount() { return this.matchingIds().size },
        resetFilters() { this.query = ''; this.category = 'all'; this.collection = 'all'; this.componentKind = 'all' },
    }
}
