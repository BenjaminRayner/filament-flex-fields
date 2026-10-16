import { seedHeadlessKnownSelected } from '../../../components/select-field/headless-select-selection-ux.js'

/** Mutable state is never shared across Alpine fields or repeaters. */
export function createSelectInstanceState() {
    return {
        /** Bumped when trigger label sources change so Alpine `x-html` re-evaluates. */
        triggerLabelEpoch: 0,
        _knownLabelEntries: new Map(),
        comboboxOpen: false,
        inlineSearchFocused: false,
        comboboxQuery: '',
        comboboxHighlightedIndex: -1,
        comboboxSelectedValues: [],
        menuReady: false,
        displayReady: false,
        requestRetryRemaining: 0,
        knownSelectedChecks: seedHeadlessKnownSelected([]),
        checkExitCancel: null,
        /** @type {Record<string, true>} */
        checkExitKeys: {},
        checkExitTick: 0,
        menuTriggerResizeObserver: null,
        menuOptionsResizeObserver: null,
        virtualScrollTick: 0,
        virtualRowWindowStart: 0,
        _userHasMutatedSelection: false,


        _engine: null,
        _virtualFlatRows: [],
        _virtualPrefixSums: [],
        _virtualScrollTop: 0,
        _virtualScrollRevision: 0,
        _virtualViewportHeight: 280,
        _selectDisposed: false,
        _selectInitialized: false,
        // Every delayed write must already belong to this field. Alpine's
        // merged scope otherwise assigns absent properties into a parent.
        _engineOptionsFingerprint: null,
        _userIntegrationInitialized: false,
        _programmaticSelectionWrite: false,
        _inlineSearchBlurTimer: null,
        _selectLayoutChecks: false,
        _selectLayoutQueued: false,
        maxItemsMessageVisible: false,
        _dynamicOptionsIdlePrefetchTimer: null,
        _pendingSelectedLabelRefresh: false,
        _searchPageCursors: new Set(),
        _virtualContentOffset: 0,
        _virtualWindowCache: null,
        __fffHeadlessMenuPositionRaf: null,
        // Disabled optional capabilities must mask ancestors' live resources too.
        _selectSearchRequests: null,
        _selectDataSource: null,
        _selectOptionsRequests: null,
        _selectLabelRequests: null,
        _relationshipAdapter: null,
        _labelRepositoryStore: null,
        _refreshOptionLabelListener: null,
        _loadMoreObserver: null,
        _dynamicOptionsCommitHook: null,
        _dynamicOptionsRefreshTimer: null,
        labelRepository: Object.create(null),
    }
}
