<div class="fff-select-pagination-tail" x-show="hasPaginatedSearchResults && shouldShowHeadlessDropdownOptions()">
                            <div
                                x-ref="headlessLoadMoreSentinel"
                                x-show="shouldShowHeadlessLoadMoreSentinel()"
                                class="fff-select-load-more-sentinel"
                                aria-hidden="true"
                            ></div>

                            <div
                                x-show="shouldShowHeadlessLoadMoreIndicator()"
                                x-cloak
                                class="fff-select-load-more"
                                role="status"
                                aria-live="polite"
                            >
                                @if ($headlessLoadMoreView ?? null)
                                    @include($headlessLoadMoreView, $headlessRendererData ?? [])
                                @else
                                    <x-filament::loading-indicator class="fff-select-load-more__spinner" />
                                    <span class="fff-select-load-more__label" x-text="headlessLoadMoreLabel()"></span>
                                @endif
                            </div>

</div>
