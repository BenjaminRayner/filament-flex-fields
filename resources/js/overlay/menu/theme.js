import { resolveIsDark } from '../../core/theme-utils.js'
export function applyTeleportedMenuTheme(menu, { variant = 'default' } = {}) {
    if (! menu) {
        return
    }

    const isDark = resolveIsDark()
    const isSheet = menu.classList?.contains?.('fff-overlay-sheet')
        || menu.classList?.contains?.('fff-teleported-menu--sheet')
        || menu.dataset?.fffOverlaySheet === 'true'
        || menu.dataset?.fffOverlayPresentation === 'sheet'
    const themeKey = `${isDark ? 'd' : 'l'}:${variant}:${isSheet ? 's' : 'p'}`

    if (menu.__fffMenuThemeKey === themeKey) {
        return
    }

    menu.__fffMenuThemeKey = themeKey
    menu.dataset.fffColorScheme = isDark ? 'dark' : 'light'

    const blur = 'blur(16px) saturate(180%)'
    const lightMenuBackground = 'rgb(255 255 255 / 0.9)'
    const lightMenuShadow = '0 2px 8px 0 #0000000f, 0 -6px 12px 0 #00000008, 0 14px 28px 0 #00000014'
    const darkMenuBackground = variant === 'map'
        ? 'rgb(39 39 42 / 0.9)'
        : 'rgb(39 39 42 / 0.92)'
    const darkMenuShadow = '0 4px 6px -1px rgb(0 0 0 / 0.28), 0 12px 28px -6px rgb(0 0 0 / 0.5)'

    menu.classList.add('fff-teleported-menu')

    if (isDark) {
        menu.style.setProperty('--fff-select-menu-bg', isSheet ? '#27272a' : darkMenuBackground)
        menu.style.setProperty('--fff-select-menu-border', 'rgb(255 255 255 / 0.12)')
        menu.style.setProperty('--fff-select-menu-shadow', isSheet ? 'none' : darkMenuShadow)
        menu.style.setProperty('--fff-select-menu-hover', 'rgb(255 255 255 / 0.08)')
        menu.style.setProperty('--fff-select-menu-selected', 'transparent')
        menu.style.setProperty('--fff-select-search-bg', isSheet ? '#3f3f46' : 'rgb(39 39 42 / 0.55)')
        menu.style.setProperty('--fff-select-search-border', 'rgb(63 63 70)')
        menu.style.setProperty('--fff-phone-field-menu-text', 'rgb(250 250 250)')
        menu.style.setProperty('--fff-phone-field-menu-muted', 'rgb(212 212 216)')
    } else {
        menu.style.setProperty('--fff-select-menu-bg', isSheet ? '#ffffff' : lightMenuBackground)
        menu.style.setProperty('--fff-select-menu-border', 'rgb(228 228 231 / 0.65)')
        menu.style.setProperty('--fff-select-menu-shadow', isSheet ? 'none' : lightMenuShadow)
        menu.style.setProperty('--fff-select-menu-hover', '#ebebec')
        menu.style.setProperty('--fff-select-menu-selected', 'transparent')
        menu.style.setProperty('--fff-select-search-bg', isSheet ? '#f4f4f5' : 'rgb(255 255 255 / 0.55)')
        menu.style.setProperty('--fff-select-search-border', 'rgb(228 228 231)')
        menu.style.setProperty('--fff-phone-field-menu-text', 'rgb(24 24 27)')
        menu.style.setProperty('--fff-phone-field-menu-muted', 'rgb(113 113 122)')
    }

    // Sheets: opaque + no backdrop-filter (Apple safe-area sheet path).
    if (isSheet) {
        const sheetBg = isDark ? '#27272a' : '#ffffff'

        menu.style.setProperty('background', sheetBg, 'important')
        menu.style.setProperty('background-color', sheetBg, 'important')
        menu.style.setProperty('box-shadow', 'none', 'important')
        menu.style.setProperty('backdrop-filter', 'none', 'important')
        menu.style.setProperty('-webkit-backdrop-filter', 'none', 'important')
        menu.style.color = isDark ? 'rgb(250 250 250)' : 'rgb(24 24 27)'

        return
    }

    const menuBackground = isDark ? darkMenuBackground : lightMenuBackground
    const menuShadow = isDark ? darkMenuShadow : lightMenuShadow

    menu.style.setProperty('background', menuBackground, 'important')
    menu.style.setProperty('background-color', menuBackground, 'important')
    menu.style.setProperty('box-shadow', menuShadow, 'important')
    menu.style.setProperty('backdrop-filter', blur, 'important')
    menu.style.setProperty('-webkit-backdrop-filter', blur, 'important')
    menu.style.color = isDark ? 'rgb(250 250 250)' : 'rgb(24 24 27)'
}
