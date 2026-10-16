import { GeocodingApiError, searchMapboxPlaces } from "../../../support/mapbox-geocoding.js";

/** geocoding search policy; descriptors are composed on the final field instance. */
export function createGeocodingSearchMixin({
  minSearchLength,
  searchDebounce
}) {
  return {
    updateSearchHasMinQuery() {
      this.searchHasMinQuery = this.searchQuery.trim().length >= minSearchLength;
    },
    ensureGeocodingResultsVisible() {
      if (this.searchResults.length > 0) {
        return true;
      }
      if (this.restoreGeocodingResultsForCurrentQuery()) {
        return true;
      }
      const query = this.searchQuery.trim();
      if (this.selectedResultId) {
        const fromRecent = this.geocodingRecentResults.find(entry => entry.id === this.selectedResultId);
        if (fromRecent) {
          this.searchResults = [{
            ...fromRecent
          }];
          this.syncGeocodingHighlightForSelected();
          return true;
        }
      }
      if (query !== '' && query === this.selectedLabel.trim() && this.geocodingRecentResults.length > 0) {
        this.searchResults = this.geocodingRecentResults.map(entry => ({
          ...entry
        }));
        this.syncGeocodingHighlightForSelected();
        return true;
      }
      return false;
    },
    refreshGeocodingDropdownResults({
      force = false
    } = {}) {
      this.updateSearchHasMinQuery();
      if (!this.searchHasMinQuery) {
        this.searchResults = [];
        this.searchLoading = false;
        this.searchRefreshing = false;
        return;
      }
      const hasVisibleResults = this.ensureGeocodingResultsVisible();
      if (!force && hasVisibleResults) {
        this.scheduleGeocodingSearch({
          background: true
        });
        return;
      }
      this.scheduleGeocodingSearch({
        force
      });
    },
    scheduleGeocodingSearch({
      force = false,
      background = false,
      immediate = false
    } = {}) {
      if (this.__fffPickerDisposed) return;
      const epoch = this._geocodingRequests.invalidate();
      window.clearTimeout(this.searchDebounceTimer);
      this.updateSearchHasMinQuery();
      if (!this.searchHasMinQuery) {
        this.searchLoading = false;
        this.searchRefreshing = false;
        return;
      }
      const hasVisibleResults = this.searchResults.length > 0;
      if (background && hasVisibleResults) {
        this.searchRefreshing = true;
        this.searchLoading = false;
      } else if (!hasVisibleResults || force) {
        this.searchLoading = true;
        this.searchRefreshing = false;
      } else {
        this.searchRefreshing = true;
        this.searchLoading = false;
      }
      const delay = immediate ? 0 : searchDebounce;
      this.searchDebounceTimer = window.setTimeout(() => {
        if (this._geocodingRequests.epoch === epoch && !this.__fffPickerDisposed) void this.performGeocodingSearch();
      }, delay);
    },
    async performGeocodingSearch() {
      const query = this.searchQuery.trim();
      if (!this.searchable || !this.canGeocode() || Array.isArray(this.countries) && this.countries.length === 0) {
        this.searchResults = [];
        this.searchLoading = false;
        this.searchRefreshing = false;
        return;
      }
      if (query.length > 256) { this.geocodeError = this.labels?.queryTooLong ?? 'Search is limited to 256 characters.'; this.searchLoading = false; this.searchRefreshing = false; return; }
      if (query.length < minSearchLength) {
        this.searchLoading = false;
        this.searchRefreshing = false;
        return;
      }
      const lease = this._geocodingRequests.begin('search');
      if (!lease) return;
      this.geocodeError = null;
      try {
        const features = await searchMapboxPlaces({
          query,
          signal: lease.signal,
          accessToken: this.accessToken,
          geocodeSearchUrl: this.geocodeSearchUrl,
          countries: this.countries,
          language: this.language,
          streetAddressesOnly: this.streetAddressesOnly,
          types: this.searchTypes,
          limit: this.searchConfiguration?.limit ?? 6,
          bounds: this.searchConfiguration?.bounds ?? null,
          proximity: this.searchConfiguration?.proximity ?? null
        });
        if (!lease.current()) {
          return;
        }
        const results = this.mapFeaturesToSearchResults(features);
        const hadStaleResults = this.searchRefreshing && this.searchResults.length > 0;
        if (results.length > 0) {
          this.searchResults = results;
          this.writeGeocodingQueryCache(query, results);
          this.syncGeocodingHighlightForSelected();
        } else if (!hadStaleResults) {
          this.searchResults = results;
          this.highlightedIndex = -1;
        }
      } catch (error) {
        if (!lease.current()) {
          return;
        }
        if (error?.name === 'AbortError') return;
        console.error(error);
        this.highlightedIndex = -1;
        this.geocodeError = error instanceof GeocodingApiError ? error.message : this.labels?.geocodeFailed ?? 'Geocoding search failed.';
        if (!this.searchResults.length) {
          this.searchResults = [];
        }
      } finally {
        if (lease.current()) {
          this.searchLoading = false;
          this.searchRefreshing = false;
          if (this.searchOpen) {
            this.scheduleGeocodingDropdownPosition?.();
          }
        }
        lease.finish();
      }
    }
  };
}
