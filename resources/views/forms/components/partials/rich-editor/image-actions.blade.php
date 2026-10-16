                @if ($hasFileAttachments())
                    <div
                        x-ref="imageOverlay"
                        class="fff-rich-editor__image-overlay"
                        x-on:mousedown.stop
                    >
                        <div class="fff-rich-editor__image-overlay-actions">
                            <button
                                type="button"
                                class="fff-rich-editor__image-overlay-button"
                                aria-label="{{ __('filament-flex-fields::default.rich_editor.image.edit') }}"
                                x-on:mousedown.stop
                                x-on:click.stop="editSelectedImage()"
                            >
                                {!! \Filament\Support\generate_icon_html(\Bjanczak\FilamentFlexFields\Support\FlexIcon::PencilToSquare, size: \Filament\Support\Enums\IconSize::Small)?->toHtml() !!}
                            </button>

                            <button
                                type="button"
                                class="fff-rich-editor__image-overlay-button fff-rich-editor__image-overlay-button--danger"
                                aria-label="{{ __('filament-flex-fields::default.rich_editor.image.delete') }}"
                                x-on:mousedown.stop
                                x-on:click.stop="deleteSelectedImage()"
                            >
                                {!! \Filament\Support\generate_icon_html(\Bjanczak\FilamentFlexFields\Support\FlexIcon::TrashBin, size: \Filament\Support\Enums\IconSize::Small)?->toHtml() !!}
                            </button>
                        </div>
                    </div>
                @endif