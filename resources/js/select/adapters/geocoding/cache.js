/** geocoding cache policy; descriptors are composed on the final field instance. */
export function createGeocodingCacheMixin({
  maxRecentSelections,
  maxQueryCacheEntries
}) {
  return {
    initGeocodingComboboxState() {
      if (this.__fffPickerDisposed) return;
      if (!this._geocodingCleanupBound) {
        this._geocodingCleanupBound = true;
        this.ensurePickerResourceScope?.().defer(() => this.disposeGeocodingCombobox());
      }
      if (!this.__geocodingQueryCache) {
        this.__geocodingQueryCache = new Map();
      }
    },
    normalizeGeocodingQuery(query) {
      return String(query ?? '').trim().toLowerCase();
    },
    disposeGeocodingCombobox() {
      this._geocodingRequests?.dispose();
      clearTimeout(this.searchDebounceTimer);
      clearTimeout(this.searchBlurTimer);
      this.searchDebounceTimer = null;
      this.searchBlurTimer = null;
      this.__geocodingQueryCache?.clear();
      this.geocodingRecentResults = [];
      this.searchLoading = false;
      this.searchRefreshing = false;
    },
    mapFeaturesToSearchResults(features) {
      if (!Array.isArray(features) || features.length > 1000) throw new TypeError('Invalid geocoding features.');
      const seen = new Set();
      return features.filter(feature => {
        if (!feature || typeof feature.id !== 'string' || typeof feature.place_name !== 'string' || seen.has(feature.id)) return false;
        seen.add(feature.id);
        return true;
      }).map(feature => ({
        id: feature.id,
        label: feature.place_name,
        feature
      }));
    },
    readGeocodingQueryCache(query) {
      const key = JSON.stringify([this.normalizeGeocodingQuery(query), this.language, this.countries, this.searchTypes, this.streetAddressesOnly, this.searchConfiguration, this.geocodeSearchUrl, this.accessToken]);
      if (!key) {
        return [];
      }
      const cached = this.__geocodingQueryCache?.get(key);
      if (!cached) return [];
      if (Date.now() - cached.at >= 60000) { this.__geocodingQueryCache.delete(key); return []; }
      this.__geocodingQueryCache.delete(key);
      this.__geocodingQueryCache.set(key, cached);
      return cached.results.map(result => ({
        ...result
      }));
    },
    writeGeocodingQueryCache(query, results) {
      const key = JSON.stringify([this.normalizeGeocodingQuery(query), this.language, this.countries, this.searchTypes, this.streetAddressesOnly, this.searchConfiguration, this.geocodeSearchUrl, this.accessToken]);
      if (!key || !Array.isArray(results)) {
        return;
      }
      if (!this.__geocodingQueryCache) {
        this.__geocodingQueryCache = new Map();
      }
      this.__geocodingQueryCache.delete(key);
      this.__geocodingQueryCache.set(key, {
        at: Date.now(),
        results: results.map(result => ({
          ...result
        }))
      });
      this.lastFetchedQuery = String(query ?? '').trim();
      while (this.__geocodingQueryCache.size > maxQueryCacheEntries) {
        this.__geocodingQueryCache.delete(this.__geocodingQueryCache.keys().next().value);
      }
    },
    rememberGeocodingRecentResult(result) {
      if (!result?.id) {
        return;
      }
      const normalized = {
        ...result
      };
      this.geocodingRecentResults = [normalized, ...this.geocodingRecentResults.filter(entry => entry.id !== normalized.id)].slice(0, maxRecentSelections);
    },
    restoreGeocodingResultsForCurrentQuery() {
      const query = this.searchQuery.trim();
      const cached = this.readGeocodingQueryCache(query);
      if (cached.length === 0) {
        return false;
      }
      this.searchResults = cached;
      this.syncGeocodingHighlightForSelected();
      return true;
    },
    finalizeGeocodingSelection(result) {
      this.selectedResultId = result?.id ?? null;
      this.rememberGeocodingRecentResult(result);
      const query = this.searchQuery.trim();
      const label = String(result?.label ?? '').trim();
      const snapshot = this.searchResults.length > 0 ? this.searchResults.map(entry => ({
        ...entry
      })) : [{
        ...result
      }];
      if (query !== '') {
        this.writeGeocodingQueryCache(query, snapshot);
      }
      if (label !== '' && label !== query) {
        this.writeGeocodingQueryCache(label, snapshot);
      }
    }
  };
}
