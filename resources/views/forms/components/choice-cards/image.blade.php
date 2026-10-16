                <span class="fff-image-choice-cards__media">
                    @if (filled($option['image']))
                        <img
                            class="fff-image-choice-cards__image"
                            src="{{ $option['image'] }}"
                            alt="{{ $option['alt'] }}"
                            @if ($loop->index < 6)
                                loading="eager"
                                fetchpriority="{{ $loop->index < 2 ? 'high' : 'auto' }}"
                            @else
                                loading="lazy"
                            @endif
                            decoding="async"
                        />
                    @else
                        <span class="fff-image-choice-cards__placeholder">
                            <svg
                                class="fff-image-choice-cards__placeholder-icon"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"
                                />
                            </svg>
                        </span>
                    @endif
                </span>

                <span class="fff-image-choice-cards__footer">
                    <span class="fff-image-choice-cards__label">{{ $option['label'] }}</span>

                    @if ($indicator !== 'none')
                        <span class="fff-image-choice-cards__indicator" aria-hidden="true">
                            @if ($indicator === 'check')
                                <span class="fff-image-choice-cards__indicator-ring"></span>
                                <svg
                                    class="fff-image-choice-cards__indicator-icon"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            @elseif ($indicator === 'checkbox')
                                <svg
                                    class="fff-image-choice-cards__indicator-icon"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            @else
                                <span class="fff-image-choice-cards__indicator-dot"></span>
                            @endif
                        </span>
                    @endif
                </span>
