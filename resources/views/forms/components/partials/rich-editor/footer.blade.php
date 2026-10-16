            @if ($shouldShowFooter)
                <div class="fff-rich-editor__footer">
                    <div class="fff-rich-editor__footer-start">
                        @if ($shouldShowJsonBadge)
                            <span class="fff-rich-editor__footer-badge">{{ $jsonBadgeLabel }}</span>
                        @endif

                        <span
                            x-show="autosaveStatus !== 'idle'"
                            x-cloak
                            class="fff-rich-editor__footer-autosave"
                            x-bind:class="{
                                'fff-rich-editor__footer-autosave--saving': autosaveStatus === 'saving',
                                'fff-rich-editor__footer-autosave--saved': autosaveStatus === 'saved',
                            }"
                            role="status"
                            aria-live="polite"
                            x-bind:aria-busy="autosaveStatus === 'saving'"
                        >
                            <span
                                x-show="autosaveStatus === 'saving'"
                                x-cloak
                                class="fff-rich-editor__footer-autosave-indicator"
                                aria-hidden="true"
                            >
                                {{ \Filament\Support\generate_loading_indicator_html(size: \Filament\Support\Enums\IconSize::Small) }}
                            </span>

                            <span
                                x-show="autosaveStatus === 'saved'"
                                x-cloak
                                class="fff-rich-editor__footer-autosave-indicator fff-rich-editor__footer-autosave-indicator--saved"
                                aria-hidden="true"
                            >
                                <x-filament::icon
                                    icon="hugeicons-checkmark-circle-02"
                                    class="fi-icon fi-size-sm"
                                />
                            </span>

                            <span x-text="autosaveStatus === 'saving'
                                ? (footerConfig.labels?.autosaveSaving ?? '')
                                : (footerConfig.labels?.autosaveSaved ?? '')"></span>
                        </span>
                    </div>

                    <div class="fff-rich-editor__footer-end">
                        <span
                            x-show="footerAltMissingCount > 0"
                            x-cloak
                            x-text="`${footerAltMissingCount} {{ __('filament-flex-fields::default.rich_editor.alt_text.missing_suffix') }}`"
                            class="fff-rich-editor__footer-alt-warning"
                            role="status"
                            aria-live="polite"
                        ></span>

                        <span
                            class="fff-rich-editor__footer-stats"
                            x-bind:class="{
                                'fff-rich-editor__footer-stats--warning': footerLimitStatus === 'warning',
                                'fff-rich-editor__footer-stats--danger': footerLimitStatus === 'danger',
                            }"
                            x-text="footerStats"
                            role="status"
                            aria-live="polite"
                            x-bind:aria-label="footerLimitStatus === 'danger'
                                ? (footerConfig.labels?.limitDanger ?? footerStats)
                                : (footerLimitStatus === 'warning'
                                    ? (footerConfig.labels?.limitWarning ?? footerStats)
                                    : footerStats)"
                        ></span>
                    </div>
                </div>
            @endif
