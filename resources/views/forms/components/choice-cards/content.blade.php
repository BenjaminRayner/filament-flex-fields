                <span @class([
                    'fff-choice-cards__content',
                    'fff-choice-cards__content--media' => $layout === 'media',
                    'fff-choice-cards__content--featured' => $layout === 'featured',
                ])>
                    @if ($layout === 'featured')
                        <span class="fff-choice-cards__leading">
                            @if (filled($option['icon']))
                                <span class="fff-choice-cards__icon-box">
                                    <x-filament::icon
                                        :icon="$option['icon']"
                                        class="fff-choice-cards__icon"
                                    />
                                </span>
                            @endif

                            <span class="fff-choice-cards__heading">
                                <span class="fff-choice-cards__label">{{ $option['label'] }}</span>

                                @if (filled($option['badge']))
                                    <span @class([
                                        'fff-choice-cards__badge',
                                        'fff-choice-cards__badge--'.$option['badge_color'],
                                    ])>{{ $option['badge'] }}</span>
                                @endif
                            </span>
                        </span>
                    @endif

                    @if ($layout !== 'featured')
                        <span class="fff-choice-cards__copy">
                            @if ($layout === 'media')
                                @if (filled($option['icon']))
                                    <span class="fff-choice-cards__media-icon" aria-hidden="true">
                                        <x-filament::icon
                                            :icon="$option['icon']"
                                            class="fff-choice-cards__icon"
                                        />
                                    </span>
                                @endif

                                <span class="fff-choice-cards__text">
                                    <span class="fff-choice-cards__label">{{ $option['label'] }}</span>

                                    @if (filled($option['description']))
                                        <span class="fff-choice-cards__description">{{ $option['description'] }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="fff-choice-cards__label">{{ $option['label'] }}</span>

                                @if (filled($option['description']))
                                    <span class="fff-choice-cards__description">{{ $option['description'] }}</span>
                                @endif
                            @endif

                            @if (filled($option['meta']))
                                <span class="fff-choice-cards__meta">{{ $option['meta'] }}</span>
                            @endif
                        </span>
                    @else
                        @if (filled($option['price']))
                            <span class="fff-choice-cards__price fff-choice-cards__price--featured">
                                <span class="fff-choice-cards__price-value">{{ $option['price'] }}</span>

                                @if (filled($option['price_suffix']))
                                    <span class="fff-choice-cards__price-suffix">{{ $option['price_suffix'] }}</span>
                                @endif
                            </span>
                        @endif

                        @if (filled($option['description']))
                            <span class="fff-choice-cards__description">{{ $option['description'] }}</span>
                        @endif
                    @endif

                    @if ($layout !== 'featured' && filled($option['price']))
                        <span class="fff-choice-cards__price">
                            <span class="fff-choice-cards__price-value">{{ $option['price'] }}</span>

                            @if (filled($option['price_suffix']))
                                <span class="fff-choice-cards__price-suffix">{{ $option['price_suffix'] }}</span>
                            @endif
                        </span>
                    @endif
                </span>
