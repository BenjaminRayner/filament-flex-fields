<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Support\Assets;

/** Generated compatibility constants. Edit resources/components, never this file. */
final class BuiltInComponentIndex
{
    public const LAZY_COMPONENT_STYLESHEETS = array (
  0 => 'address-autocomplete',
  1 => 'asset-diagnostics',
  2 => 'audio-field',
  3 => 'barcode-scanner-field',
  4 => 'bubble-choice',
  5 => 'calculator-field',
  6 => 'calculator-panel',
  7 => 'camera-surface',
  8 => 'checklist-indicator',
  9 => 'choice-cards',
  10 => 'color-swatch',
  11 => 'country-map-field',
  12 => 'cover-card',
  13 => 'credit-card',
  14 => 'currency-field',
  15 => 'date-time-calendar',
  16 => 'date-time-clock',
  17 => 'date-value-presentation',
  18 => 'dual-listbox',
  19 => 'emoji-picker',
  20 => 'field-intelligence',
  21 => 'file-upload-image-editor',
  22 => 'file-upload-viewer',
  23 => 'flex-catalog',
  24 => 'flex-catalog-recording',
  25 => 'flex-checklist',
  26 => 'flex-code-preview',
  27 => 'flex-color-picker',
  28 => 'flex-date-time-field',
  29 => 'flex-file-upload',
  30 => 'flex-license-guide',
  31 => 'flex-slider',
  32 => 'flex-textarea',
  33 => 'flex-time-picker',
  34 => 'flex-time-segments',
  35 => 'flex-verification-code',
  36 => 'font-select',
  37 => 'hold-confirm-action',
  38 => 'icon-column',
  39 => 'icon-picker-field',
  40 => 'image-choice-cards',
  41 => 'item-card',
  42 => 'link-preview-field',
  43 => 'location-controls',
  44 => 'map-picker-dropdown',
  45 => 'map-picker',
  46 => 'map-pin-column',
  47 => 'matrix-choice-field',
  48 => 'nps-field',
  49 => 'number-stepper',
  50 => 'overlay-runtime',
  51 => 'phone-field',
  52 => 'price-range',
  53 => 'progress-bar',
  54 => 'progress-circle',
  55 => 'progress-column',
  56 => 'rating-column',
  57 => 'rating-field',
  58 => 'rich-editor-field',
  59 => 'schedule-display',
  60 => 'schedule-field',
  61 => 'segment-control',
  62 => 'segment-tabs',
  63 => 'select-field',
  64 => 'selection-indicator',
  65 => 'signature-field',
  66 => 'signature-preview-column',
  67 => 'slug-field',
  68 => 'social-links-field',
  69 => 'status-chip-column',
  70 => 'switch',
  71 => 'tag-chips',
  72 => 'tags-field',
  73 => 'teleported-menu',
  74 => 'flex-text-input',
  75 => 'timezone-field',
  76 => 'todo-list-field',
  77 => 'track-slider',
  78 => 'traffic-split',
  79 => 'user-column',
  80 => 'user-display',
  81 => 'user-select',
  82 => 'video-field',
  83 => 'voice-note-recorder-field',
);

    public const STYLESHEET_DEPENDENCIES = array (
  'address-autocomplete' => 
  array (
    0 => 'flex-text-input',
    1 => 'location-select',
  ),
  'admin-columns' => 
  array (
    0 => 'progress-column',
  ),
  'asset-diagnostics' => 
  array (
  ),
  'audio-field' => 
  array (
  ),
  'barcode-scanner-field' => 
  array (
    0 => 'text-input-presentation',
    1 => 'camera-surface',
  ),
  'bubble-choice' => 
  array (
  ),
  'calculator-field' => 
  array (
    0 => 'flex-text-input',
    1 => 'calculator-panel',
  ),
  'calculator-panel' => 
  array (
    0 => 'overlay-runtime',
    1 => 'teleported-menu',
  ),
  'camera-surface' => 
  array (
  ),
  'checklist-indicator' => 
  array (
  ),
  'choice-cards' => 
  array (
    0 => 'selection-indicator',
  ),
  'choice-checkbox-cards' => 
  array (
    0 => 'choice-cards',
  ),
  'color-swatch' => 
  array (
  ),
  'composite-field' => 
  array (
  ),
  'country-field' => 
  array (
    0 => 'select-field',
    1 => 'country-flags',
  ),
  'country-flags' => 
  array (
  ),
  'country-map-field' => 
  array (
    0 => 'selection-indicator',
    1 => 'location-controls',
    2 => 'select-field',
  ),
  'cover-card' => 
  array (
  ),
  'credit-card' => 
  array (
    0 => 'text-input-presentation',
  ),
  'currency-field' => 
  array (
    0 => 'flex-text-input',
    1 => 'select-field',
  ),
  'date-periods' => 
  array (
    0 => 'flex-date-picker',
    1 => 'flex-duration',
  ),
  'date-picker' => 
  array (
    0 => 'flex-date-picker',
    1 => 'flex-text-input',
  ),
  'date-range-picker' => 
  array (
    0 => 'flex-date-picker',
  ),
  'date-time-calendar' => 
  array (
    0 => 'teleported-menu',
    1 => 'date-value-presentation',
  ),
  'date-time-clock' => 
  array (
    0 => 'text-input-presentation',
    1 => 'teleported-menu',
  ),
  'date-time-fields' => 
  array (
    0 => 'flex-date-picker',
    1 => 'flex-time-picker',
    2 => 'flex-duration',
    3 => 'timezone-field',
    4 => 'schedule-field',
  ),
  'date-value-presentation' => 
  array (
  ),
  'dual-listbox' => 
  array (
    0 => 'text-input-presentation',
    1 => 'checklist-indicator',
  ),
  'emoji-picker' => 
  array (
    0 => 'teleported-menu',
    1 => 'text-input-presentation',
  ),
  'field-intelligence' => 
  array (
  ),
  'file-upload-editor-transport' => 
  array (
  ),
  'file-upload-transport' => 
  array (
  ),
  'file-upload-viewer' => 
  array (
    0 => 'flex-file-upload',
    1 => 'media-os',
  ),
  'file-upload' => 
  array (
    0 => 'flex-file-upload',
  ),
  'flex-catalog-detail' => 
  array (
    0 => 'flex-catalog-layout',
    1 => 'teleported-menu',
  ),
  'flex-catalog-layout' => 
  array (
  ),
  'flex-catalog-recording' => 
  array (
  ),
  'flex-catalog' => 
  array (
    0 => 'flex-catalog-layout',
    1 => 'flex-text-input',
    2 => 'select-field',
  ),
  'flex-checklist' => 
  array (
    0 => 'checklist-indicator',
  ),
  'flex-code-preview' => 
  array (
    0 => 'flex-catalog-layout',
  ),
  'flex-color-picker' => 
  array (
    0 => 'flex-text-input',
    1 => 'teleported-menu',
  ),
  'flex-date-picker' => 
  array (
    0 => 'text-input-presentation',
    1 => 'date-value-presentation',
  ),
  'flex-date-time-field' => 
  array (
    0 => 'flex-text-input',
    1 => 'teleported-menu',
  ),
  'flex-duration' => 
  array (
    0 => 'flex-text-input',
  ),
  'flex-file-upload' => 
  array (
    0 => 'camera-surface',
    1 => 'flex-text-input',
    2 => 'media-os',
  ),
  'flex-license-guide' => 
  array (
    0 => 'flex-catalog-layout',
    1 => 'choice-checkbox-cards',
  ),
  'flex-matrix-table' => 
  array (
    0 => 'matrix-choice-field',
  ),
  'flex-radiolist' => 
  array (
    0 => 'flex-checklist',
  ),
  'flex-rich-editor' => 
  array (
    0 => 'rich-editor-field',
  ),
  'flex-slider' => 
  array (
  ),
  'flex-text-input' => 
  array (
    0 => 'text-input-presentation',
  ),
  'flex-textarea' => 
  array (
  ),
  'flex-time-picker' => 
  array (
    0 => 'text-input-presentation',
    1 => 'date-value-presentation',
  ),
  'flex-time-segments' => 
  array (
    0 => 'flex-text-input',
    1 => 'teleported-menu',
  ),
  'flex-verification-code' => 
  array (
  ),
  'font-select' => 
  array (
    0 => 'select-field',
  ),
  'hold-confirm-action' => 
  array (
  ),
  'hold-confirm' => 
  array (
    0 => 'hold-confirm-action',
  ),
  'icon-column' => 
  array (
  ),
  'icon-picker-field' => 
  array (
    0 => 'select-field',
  ),
  'image-choice-cards' => 
  array (
  ),
  'item-card-group' => 
  array (
    0 => 'item-card',
  ),
  'item-card-interaction' => 
  array (
  ),
  'item-card' => 
  array (
  ),
  'link-preview-field' => 
  array (
    0 => 'text-input-presentation',
  ),
  'location-controls' => 
  array (
  ),
  'location-geocoding' => 
  array (
  ),
  'location-select' => 
  array (
    0 => 'flex-text-input',
    1 => 'location-controls',
    2 => 'select-field',
    3 => 'location-geocoding',
  ),
  'map-coordinates' => 
  array (
    0 => 'map-picker',
  ),
  'map-display' => 
  array (
    0 => 'map-picker',
  ),
  'map-editor' => 
  array (
    0 => 'map-picker',
  ),
  'map-markers' => 
  array (
    0 => 'map-picker',
  ),
  'map-picker-dropdown' => 
  array (
    0 => 'teleported-menu',
    1 => 'select-field',
  ),
  'map-picker' => 
  array (
    0 => 'flex-text-input',
    1 => 'location-controls',
    2 => 'location-geocoding',
  ),
  'map-pin-column' => 
  array (
  ),
  'map-zones' => 
  array (
    0 => 'map-picker',
  ),
  'matrix-choice-field' => 
  array (
  ),
  'matrix-choice' => 
  array (
    0 => 'matrix-choice-field',
  ),
  'media-image-column' => 
  array (
  ),
  'media-image-entry' => 
  array (
  ),
  'media-os' => 
  array (
  ),
  'nps-field' => 
  array (
    0 => 'segment-control',
  ),
  'number-stepper' => 
  array (
  ),
  'overlay-runtime' => 
  array (
  ),
  'phone-field' => 
  array (
    0 => 'text-input-presentation',
    1 => 'select-field',
    2 => 'country-flags',
  ),
  'playground-assets' => 
  array (
  ),
  'price-range' => 
  array (
  ),
  'progress-bar' => 
  array (
  ),
  'progress-circle-breakdown' => 
  array (
  ),
  'progress-circle' => 
  array (
  ),
  'progress-column' => 
  array (
    0 => 'progress-bar',
  ),
  'rating-column' => 
  array (
    0 => 'rating-visuals',
  ),
  'rating-field' => 
  array (
    0 => 'rating-visuals',
  ),
  'rating-visuals' => 
  array (
  ),
  'rating' => 
  array (
    0 => 'rating-field',
  ),
  'rich-editor-field' => 
  array (
    0 => 'media-os',
  ),
  'schedule-column' => 
  array (
    0 => 'schedule-display',
  ),
  'schedule-display' => 
  array (
  ),
  'schedule-entry' => 
  array (
    0 => 'schedule-display',
  ),
  'schedule-field' => 
  array (
    0 => 'text-input-presentation',
    1 => 'timezone-field',
    2 => 'select-field',
    3 => 'switch',
  ),
  'segment-control' => 
  array (
  ),
  'segment-tabs' => 
  array (
    0 => 'segment-control',
  ),
  'select-field' => 
  array (
    0 => 'teleported-menu',
  ),
  'selection-indicator' => 
  array (
  ),
  'signature-field' => 
  array (
    0 => 'media-os',
  ),
  'signature-preview-column' => 
  array (
  ),
  'slug-field' => 
  array (
    0 => 'flex-text-input',
    1 => 'segment-control',
  ),
  'social-links-field' => 
  array (
    0 => 'text-input-presentation',
    1 => 'select-field',
  ),
  'spatie-media-image-column' => 
  array (
  ),
  'spatie-media-image-entry' => 
  array (
  ),
  'status-chip-column' => 
  array (
  ),
  'switch' => 
  array (
  ),
  'tag-chips' => 
  array (
  ),
  'tags-field' => 
  array (
    0 => 'flex-text-input',
    1 => 'tag-chips',
    2 => 'select-field',
  ),
  'teleported-menu' => 
  array (
    0 => 'overlay-runtime',
  ),
  'text-input-presentation' => 
  array (
  ),
  'text-input-suggestions' => 
  array (
    0 => 'select-field',
  ),
  'time-picker' => 
  array (
    0 => 'flex-time-picker',
  ),
  'timezone-field' => 
  array (
    0 => 'select-field',
  ),
  'timezone-picker-presentation' => 
  array (
    0 => 'text-input-presentation',
    1 => 'teleported-menu',
  ),
  'todo-list-field' => 
  array (
    0 => 'text-input-presentation',
  ),
  'track-slider' => 
  array (
  ),
  'traffic-split' => 
  array (
  ),
  'translatable-fields' => 
  array (
    0 => 'segment-tabs',
  ),
  'user-column' => 
  array (
    0 => 'user-display',
  ),
  'user-display' => 
  array (
  ),
  'user-select' => 
  array (
    0 => 'tag-chips',
    1 => 'user-display',
    2 => 'select-field',
  ),
  'verification-code' => 
  array (
    0 => 'flex-verification-code',
  ),
  'video-field' => 
  array (
  ),
  'voice-note-recorder-field' => 
  array (
    0 => 'audio-field',
    1 => 'media-os',
  ),
);

    public const ALPINE_ENTRY_ALIASES = array (
  'rich-editor-field' => 'flex-rich-editor',
);

    public const PLAYGROUND_STYLESHEET_ALIASES = array (
  'choice-checkbox-cards' => 'choice-cards',
  'country-field' => 'select-field',
  'date-periods' => 'flex-date-picker',
  'date-picker' => 'flex-date-picker',
  'date-range-picker' => 'flex-date-picker',
  'date-time-fields' => 'flex-date-picker',
  'file-upload' => 'flex-file-upload',
  'flex-rich-editor' => 'rich-editor-field',
  'hold-confirm' => 'hold-confirm-action',
  'item-card-group' => 'item-card',
  'map-coordinates' => 'map-picker',
  'map-display' => 'map-picker',
  'map-editor' => 'map-picker',
  'map-markers' => 'map-picker',
  'map-zones' => 'map-picker',
  'matrix-choice' => 'matrix-choice-field',
  'rating' => 'rating-field',
  'time-picker' => 'flex-time-picker',
  'timezone-field' => 'select-field',
  'translatable-fields' => 'segment-tabs',
  'verification-code' => 'flex-verification-code',
);

    public const PLAYGROUND_EXTRA_STYLESHEETS = array (
  'country-field' => 
  array (
    0 => 'country-flags',
  ),
  'date-periods' => 
  array (
    0 => 'flex-duration',
  ),
  'date-picker' => 
  array (
    0 => 'flex-text-input',
  ),
  'date-time-fields' => 
  array (
    0 => 'flex-time-picker',
    1 => 'flex-duration',
    2 => 'timezone-field',
    3 => 'schedule-field',
  ),
);

    public const STYLESHEET_SSR_SCRIPTS = array (
  'segment-control' => 
  array (
    0 => 'flex-fields-segment-overflow-ssr',
  ),
  'segment-tabs' => 
  array (
    0 => 'flex-fields-segment-overflow-ssr',
  ),
);

}
