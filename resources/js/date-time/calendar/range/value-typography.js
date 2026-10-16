/** Shared typography contract for the source, footer and decorative text flight. */
export function rangeValueTypography(style) {
    return Object.fromEntries(['fontFamily', 'fontSize', 'fontWeight', 'lineHeight',
        'fontVariantNumeric', 'fontVariantCaps', 'fontFeatureSettings', 'fontVariationSettings',
        'fontKerning', 'fontSynthesis', 'letterSpacing', 'textTransform', 'textRendering',
        'webkitFontSmoothing'].map(name => [name, style[name]]))
}
