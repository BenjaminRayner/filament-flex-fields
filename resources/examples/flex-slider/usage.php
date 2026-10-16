<?php

use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexSlider;

FlexSlider::make('volume')
    ->fillColor('#8B5CF6')
    ->range(0, 100)
    ->step(1)
    ->default(64)
    ->trackLabel('Volume')
    ->suffix('%')
    ->fillTrack()
    ->showValue()
    ->valuePlacement('header')
    ->tooltips()
    ->elastic()
    ->iconButton('hugeicons-volume-high')
    ->iconButtonActiveIcon('hugeicons-volume-off')
    ->iconButtonLabel('Mute')
    ->iconButtonActiveLabel('Restore volume')
    ->endIcon('hugeicons-volume-high')
    ->marks([0, ['value' => 50, 'label' => '50%'], 100]);

FlexSlider::make('playback_speed')
    ->color('info')
    ->range(0.5, 2)
    ->step(0.1)
    ->default(1)
    ->trackLabel('Playback speed')
    ->suffix('×')
    ->fillTrack()
    ->showValue()
    ->valuePlacement('header')
    ->tooltips()
    ->thumbStyle('circle')
    ->elastic()
    ->marks([
        ['value' => 0.5, 'label' => '0.5×'],
        ['value' => 1, 'label' => '1×'],
        ['value' => 1.5, 'label' => '1.5×'],
        ['value' => 2, 'label' => '2×'],
    ]);

FlexSlider::make('interval')
    ->fillColor('#14B8A6')
    ->range(0, 100)
    ->step(1)
    ->default([45, 55])
    ->rangeHandles()
    ->thumbStyle('circle')
    ->fillTrack([false, true, false])
    ->showValue()
    ->valuePlacement('header')
    ->elastic()
    ->tooltips();

FlexSlider::make('volume_button_only')
    ->fillColor('#0EA5E9')
    ->range(0, 100)
    ->step(1)
    ->default(64)
    ->trackLabel('Single button')
    ->suffix('%')
    ->fillTrack()
    ->showValue()
    ->valuePlacement('header')
    ->thumbStyle('circle')
    ->elastic()
    ->tooltips()
    ->iconButton('hugeicons-volume-high')
    ->iconButtonActiveIcon('hugeicons-volume-off')
    ->iconButtonLabel('Mute')
    ->iconButtonActiveLabel('Restore volume');
