@php
    $menus = [];
    if ($hasQualityControl) $menus['quality'] = ['label' => __('filament-flex-fields::default.video.quality'), 'icon' => $field->getQualityIcon(), 'hint' => 'selectedQualityLabel'];
    if ($hasPlaybackRateControl) $menus['speed'] = ['label' => __('filament-flex-fields::default.video.speed'), 'icon' => $field->getSpeedIcon(), 'hint' => 'playbackRateLabel'];
    $small = \Filament\Support\Enums\IconSize::ExtraSmall;
@endphp
<div class="fff-video-field__settings" x-on:click.outside="onSettingsMenuClickOutside($event)">
    <div class="fff-video-field__settings-menu" x-ref="settingsMenu" data-side="top" hidden x-on:wheel.stop aria-label="{{ __('filament-flex-fields::default.video.settings') }}">
        <div class="fff-video-field__settings-viewport" data-menu-viewport>
            <div class="fff-video-field__settings-panel" x-ref="settingsPanelRoot" data-menu-root-view data-menu-view role="menu" aria-label="{{ __('filament-flex-fields::default.video.settings') }}">
                <div class="fff-video-field__settings-group">
                    <span class="fff-video-field__settings-highlight" data-menu-highlight aria-hidden="true"></span>
                    @foreach ($menus as $key => $menu)
                        <button type="button" class="fff-video-field__settings-item fff-video-field__settings-item--submenu" data-menu-target="{{ $key }}" x-on:click="openSettingsView(@js($key))" role="menuitem" aria-haspopup="menu" x-bind:aria-expanded="settingsView === @js($key) ? 'true' : 'false'">
                            <span class="fff-video-field__settings-item-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($menu['icon'], size: $small) }}</span>
                            <span class="fff-video-field__settings-item-label">{{ $menu['label'] }}</span>
                            <span class="fff-video-field__settings-item-hint">
                                <span class="fff-video-field__settings-item-hint-label" x-text="{{ $menu['hint'] }}"></span>
                                <span class="fff-video-field__settings-item-chevron" aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Bjanczak\FilamentFlexFields\Support\Video\VideoIcon::Chevron, size: $small) }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
            @foreach ($menus as $key => $menu)
                <div class="fff-video-field__settings-panel" x-ref="{{ $key === 'quality' ? 'settingsPanelQuality' : 'settingsPanelSpeed' }}" data-submenu data-menu-view hidden inert role="menu" aria-label="{{ $menu['label'] }}">
                    <button type="button" class="fff-video-field__settings-back" x-on:click="openSettingsView('root')" role="menuitem">
                        <span class="fff-video-field__settings-item-chevron fff-video-field__settings-item-chevron--back" aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Bjanczak\FilamentFlexFields\Support\Video\VideoIcon::Chevron, size: $small) }}</span>
                        <span>{{ $menu['label'] }}</span>
                    </button>
                    <div class="fff-video-field__settings-separator" aria-hidden="true"></div>
                    <div class="fff-video-field__settings-group">
                    <span class="fff-video-field__settings-highlight" data-menu-highlight aria-hidden="true"></span>
                        @foreach ($key === 'quality' ? $options['qualityOptions'] : $options['playbackRates'] as $choice)
                            @php
                                $value = $key === 'quality' ? $choice['key'] : $choice;
                                $choiceLabel = $key === 'quality' ? $choice['label'] : $choice.'×';
                                $checked = $key === 'quality' ? 'selectedQualityKey === '.\Illuminate\Support\Js::from($value) : 'playbackRate === '.\Illuminate\Support\Js::from($value);
                            @endphp
                            <button type="button" class="fff-video-field__settings-item" role="menuitemradio" x-bind:aria-checked="{{ $checked }} ? 'true' : 'false'" x-on:click="{{ $key === 'quality' ? 'selectQuality({ key: '.\Illuminate\Support\Js::from($value).' })' : 'setPlaybackRate('.\Illuminate\Support\Js::from($value).')' }}">
                                <span>{{ $choiceLabel }}</span>
                                <span class="fff-video-field__settings-item-indicator" x-show="{{ $checked }}" aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Bjanczak\FilamentFlexFields\Support\Video\VideoIcon::Check, size: $small) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <x-filament-flex-fields::video-field-control-tip :label="__('filament-flex-fields::default.video.settings')" :enabled="$showTooltips">
        <button type="button" class="fff-video-field__glass-btn" x-ref="settingsTrigger" x-on:click="toggleSettingsMenu()" aria-label="{{ __('filament-flex-fields::default.video.settings') }}" x-bind:aria-expanded="settingsOpen ? 'true' : 'false'" aria-haspopup="menu" x-bind:class="{ 'is-active': settingsOpen }" @disabled($options['readOnly'])>
            {{ \Filament\Support\generate_icon_html($field->getSettingsIcon(), size: $toolbarIconSize) }}
        </button>
    </x-filament-flex-fields::video-field-control-tip>
</div>
