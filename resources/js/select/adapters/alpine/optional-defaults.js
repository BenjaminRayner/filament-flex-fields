export const optionalMixinStubs = {
    searchPending: false,
    optionsLoading: false,
    loadingMore: false,
    searchError: null,
    optionsError: null,
    retryHeadlessRequest() {},
    initLivewireIntegration() {},
    initUserSelectIntegration() {},
    cancelRelationshipSearch() {},
    syncUserSelectTags() {},
    onHeadlessMenuOpenedForLivewire() {},
    observeHeadlessLoadMore() {},
    disconnectHeadlessLoadMoreObserver() {},
    // Used at engine create when hasDynamicSearchResults; real mixin overwrites.
    serverSideFilterFn() {
        return () => true
    },
    comboboxEntityMentionActive() {
        return false
    },
    onHeadlessTriggerMentionKeydown() {},
    userSelectTriggerHtml() {
        return this.placeholder ?? ''
    },
    labelEntry() {
        return null
    },
    optionRecord() {
        return null
    },
    storeLabelEntry() {},
    shouldShowHeadlessTriggerLoading() {
        return false
    },
    shouldShowHeadlessLoadMoreIndicator() {
        return false
    },
    shouldShowHeadlessLoadMoreSentinel() {
        return false
    },
    headlessLoadMoreLabel() {
        return this.loadingMoreMessage || this.loadingMessage || ''
    },
    teardownSelectedOptionLabelRefreshListener() {},
    teardownDynamicOptionsInvalidation() {},
    shouldShowHeadlessUserSelectSkeleton() {
        return false
    },
    shouldShowHeadlessUserSelectEmptyState() {
        return false
    },
    headlessUserSelectSkeletonAriaLabel() {
        return ''
    },
    headlessUserSelectSkeletonRows() {
        return []
    },
    headlessUserSelectEmptyIconHtml() {
        return ''
    },
    headlessUserSelectEmptyTitle() {
        return ''
    },
    headlessUserSelectEmptyHint() {
        return ''
    },
    isUserSelectField: false,
}

