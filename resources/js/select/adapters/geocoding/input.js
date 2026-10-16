/** geocoding input policy; descriptors are composed on the final field instance. */
export function createGeocodingInputMixin({
  minSearchLength,
  searchInputRef,
  menuRef
}) {
  return {
    syncGeocodingHighlightForSelected() {
      if (!this.searchResults.length) {
        this.highlightedIndex = -1;
        return;
      }
      if (this.selectedResultId) {
        const selectedIndex = this.searchResults.findIndex(result => result.id === this.selectedResultId);
        if (selectedIndex >= 0) {
          this.highlightedIndex = selectedIndex;
          return;
        }
      }
      if (typeof this.syncGeocodingHighlightedIndex === 'function') {
        this.syncGeocodingHighlightedIndex();
      } else {
        this.highlightedIndex = 0;
      }
    },
    dismissGeocodingDropdown({
      restoreQuery = true
    } = {}) {
      this._geocodingRequests.invalidate();
      window.clearTimeout(this.searchDebounceTimer);
      window.clearTimeout(this.searchBlurTimer);
      this.searchOpen = false;
      this.searchLoading = false;
      this.searchRefreshing = false;
      this.highlightedIndex = -1;
      if (restoreQuery) {
        this.searchQuery = this.selectedLabel;
      }
      this.updateSearchHasMinQuery();
    },
    onGeocodingSearchInput() {
      if (this.__fffPickerDisposed || this.readOnly) return;
      this._geocodingRequests.invalidate();
      this.highlightedIndex = -1;
      this.searchOpen = true;
      this.updateSearchHasMinQuery();
      const query = this.searchQuery.trim();

      // Below min chars: drop stale results so empty/min-chars chrome is alone
      // (do not leave prior suggestions under the “type at least N” message).
      if (query.length < minSearchLength) {
        this.searchResults = [];
        this.searchLoading = false;
        this.searchRefreshing = false;
        window.clearTimeout(this.searchDebounceTimer);
        return;
      }
      const normalized = this.normalizeGeocodingQuery(query);
      if (normalized !== this.normalizeGeocodingQuery(this.lastFetchedQuery)) {
        const cached = this.readGeocodingQueryCache(query);
        if (cached.length > 0) {
          this.searchResults = cached;
        }
      }
      this.scheduleGeocodingSearch();
    },
    onGeocodingSearchFocus() {
      if (this.__fffPickerDisposed || this.readOnly) return;
      window.clearTimeout(this.searchBlurTimer);
      this.searchFocused = true;
      this.searchOpen = true;
      this.refreshGeocodingDropdownResults();
      this.$nextTick(() => {
        if (!this.__fffPickerDisposed && this.searchFocused && this.searchOpen) this.$refs[searchInputRef]?.select?.();
      });
    },
    onGeocodingSearchBlur() {
      this.searchFocused = false;
      window.clearTimeout(this.searchBlurTimer);
      this.searchBlurTimer = window.setTimeout(() => {
        this.searchBlurTimer = null;
        if (this.__fffPickerDisposed || this.readOnly) return;
        const active = document.activeElement;
        const menu = this.$refs[menuRef];
        if (menu?.contains(active)) {
          return;
        }

        // Mobile sheet owns dismiss via backdrop / handle drag — clicking
        // empty sheet chrome blurs the search input but must not close.
        if (typeof this.shouldDismissGeocodingOnBlur === 'function') {
          if (!this.shouldDismissGeocodingOnBlur()) {
            return;
          }
        } else if (this.__fffOverlayMode === 'sheet' || menu?.classList?.contains?.('fff-teleported-menu--sheet') || menu?.classList?.contains?.('fff-overlay-sheet')) {
          return;
        }
        this.dismissGeocodingDropdown();
      }, 120);
    },
    onGeocodingComboboxKeydown(event) {
      if (this.__fffPickerDisposed || this.readOnly || event.isComposing || event.keyCode === 229) return;
      if (!this.searchOpen) {
        if (event.key === 'ArrowDown' || event.key === 'Enter') {
          this.searchOpen = true;
          this.refreshGeocodingDropdownResults();
        }
        return;
      }
      if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter', 'Escape'].includes(event.key)) {
        this.onGeocodingSearchKeydown(event);
        if (event.key === 'Escape') {
          this.dismissGeocodingDropdown();
          event.target?.blur?.();
        }
      }
    }
  };
}
