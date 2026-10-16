<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Location\Presentation;

/** Both visual variants render the same button partial and commands. */
final class MapControlGroups
{
    /**
     * @param  array<string, mixed>  $configuration
     * @return list<list<array<string, mixed>>>
     */
    public static function for(array $configuration, bool $editable, bool $geometry): array
    {
        $compact = $configuration['compactControls'];
        $button = static fn (string $command, string $click, string $label, ?string $disabled = null): array => ['command' => $command, 'click' => $click, 'label' => $label, 'disabled' => $disabled];
        $groups = [];
        if ($configuration['experience']['playback']['controls'] ?? false) {
            $groups[] = [$button('playback', 'toggleTrackPlayback()', 'track_playback')];
        }
        if ($configuration['experience']['followCamera'] ?? false) {
            $groups[] = [$button('follow', 'resumeTrackFollowing()', 'track_follow')];
        }
        if ($configuration['geolocation'] && $editable) {
            $groups[] = [$button('locate', 'locateUser()', 'locate', 'locating')];
        }
        $main = [];
        if ($configuration['fitControl']) {
            $main[] = $button('fit', 'fitMapToFeatures()', 'fit');
        }
        if ($configuration['zoomControls']) {
            $main[] = $button($compact ? 'plus' : 'zoom-in', 'zoomMap(1)', 'zoom_in');
            $main[] = $button($compact ? 'minus' : 'zoom-out', 'zoomMap(-1)', 'zoom_out');
        }
        if ($compact && $main !== []) {
            $groups[] = $main;
            $main = [];
        }
        if ($configuration['compass']) {
            $main[] = $button($compact ? 'north' : 'bearing', 'resetMapBearing()', 'compass');
        }
        if ($compact && $main !== []) {
            $groups[] = $main;
            $main = [];
        }
        if ($configuration['fullscreen']) {
            $main[] = $button('fullscreen', 'toggleMapFullscreen()', 'fullscreen');
        }
        if ($configuration['clearable'] && $editable && (! $geometry || $configuration['deletableFeatures'])) {
            $main[] = $button('clear', 'clearLocation()', 'clear');
        }
        if ($main !== []) {
            $groups[] = $main;
        }

        return $groups;
    }
}
