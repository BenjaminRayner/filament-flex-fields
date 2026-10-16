            @if ((! $isDisabled) && filled($toolbarButtons))
                <div
                    class="fff-rich-editor__toolbar"
                    role="toolbar"
                    aria-label="{{ $label }}"
                    aria-orientation="horizontal"
                    x-ref="toolbar"
                    x-on:click.capture="
                        const trigger = $event.target.closest('.fi-fo-rich-editor-dropdown-tool-trigger');
                        if (! trigger) return;
                        const active = trigger.closest('.fi-fo-rich-editor-dropdown-tool');
                        $el.querySelectorAll('.fi-fo-rich-editor-dropdown-tool').forEach((group) => {
                            if (group === active) return;
                            const state = Alpine.$data(group);
                            if (state) state.open = false;
                        });
                    "
                >
                    @foreach ($toolbarButtons as $buttonGroup)
                        @if (! $loop->first)
                            <div
                                class="fff-rich-editor__toolbar-separator"
                                role="separator"
                                aria-orientation="vertical"
                            ></div>
                        @endif

                            <div
                                class="fff-rich-editor__toolbar-group"
                            >
                            @foreach ($buttonGroup as $button)
                                @if (is_string($button))
                                    @if ($shouldEnableDistractionFree)
                                        <div
                                            x-bind:class="{
                                                'fff-rich-editor__toolbar-button--distraction-free-hidden': isDistractionFreeToolbarButton(@js($button)),
                                            }"
                                        >
                                            {{ $tools[$button] ?? throw new LogicException("Toolbar button [{$button}] cannot be found.") }}
                                        </div>
                                    @else
                                        {{ $tools[$button] ?? throw new LogicException("Toolbar button [{$button}] cannot be found.") }}
                                    @endif
                                @else
                                    {{ $button }}
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endif

            @foreach ($floatingToolbars as $nodeName => $buttons)
                <div
                    x-ref="floatingToolbar::{{ $nodeName }}"
                    class="fff-rich-editor__bubble fff-rich-editor__bubble-menu fff-rich-editor__bubble-menu--{{ $nodeName }} fi-not-prose"
                    role="toolbar"
                    aria-label="{{ $label }}"
                >
                    @foreach ($buttons as $button)
                        @if (is_string($button))
                            {{ $tools[$button] }}
                        @else
                            {{ $button }}
                        @endif
                    @endforeach
                </div>
            @endforeach

