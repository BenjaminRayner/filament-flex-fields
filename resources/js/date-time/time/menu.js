import { createImperativeMenuSurface } from '../../overlay/menu/imperative-surface.js'
import { createResourceScope } from '../../runtime/lifecycle/resource-scope.js'
import { showClock } from '../domain/local-date-time.js'

/** A single owned clock list, shared by calendars and standalone inputs. */
export function createClockMenu({ root, trigger, config, get, set, valid, parent = null }) {
    const scope = createResourceScope()
    let active = null, disposed = false
    function popup(seconds = false) {
        if (active) { if (active.surface.menuOpen && !active.surface.__fffPanelClosing) active.surface.closeTeleportedMenu(); else { active.surface.cancelClosing(); active.surface.menuOpen = true } return }
        const list = document.createElement('div'); list.className = 'fff-teleported-menu fff-dt-calendar fff-dt-time-menu'; list.classList.toggle('is-dark', root.closest('.dark') !== null); list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', trigger.getAttribute('aria-label')); if (parent?.dataset.fffDropdownOwner) list.dataset.fffDropdownParent = parent.dataset.fffDropdownOwner
        const scroller = document.createElement('div'); scroller.className = 'fff-dt-time-menu__scroll'; list.append(scroller)
        const chosen = get(), current = chosen || config.defaultTime, step = seconds ? config.secondStep : config.minuteStep
        const options = []
        const allowedSet = config.allowedTimes == null ? null : new Set(config.allowedTimes)
        const precision = config.allowedTimes?.some(time => !time.endsWith(':00')) ? ':s' : ''
        const choices = !seconds && config.allowedTimes !== null && config.allowedTimes !== undefined ? config.allowedTimes : Array.from({ length: Math.ceil((seconds ? 60 : 1440) / step) }, (_, index) => {
            const value = index * step
            return seconds ? `${current.slice(0, 6)}${String(value).padStart(2, '0')}` : `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}:${current.slice(6)}`
        })
        for (const time of choices) {
            const option = document.createElement('button'); option.type = 'button'; option.className = 'fff-dt-time-menu__option'; option.setAttribute('role', 'option'); option.dataset.time = time
            option.textContent = seconds ? time.slice(6) : showClock(time, config.hourCycle === 12 ? `${config.forceLeadingZeros ? 'h' : 'g'}:i${precision} A` : `H:i${precision}`)
            option.disabled = !valid(time) || (allowedSet !== null && !allowedSet.has(time))
            const selected = chosen && (seconds ? time.slice(6) === current.slice(6) : time.slice(0, 5) === current.slice(0, 5))
            option.setAttribute('aria-selected', String(selected)); option.tabIndex = selected ? 0 : -1
            scroller.append(option); options.push(option)
        }
        if (!options.some(option => !option.disabled)) {
            const empty = document.createElement('p'); empty.className = 'fff-dt-time-menu__empty'; empty.textContent = config.noTimesMessage; scroller.append(empty)
        }
        const menuScope = createResourceScope()
        const destroy = () => { if (!active) return; const owned = active; active = null; menuScope.dispose(); owned.surface?.dispose(); list.remove(); release() }
        const release = scope.defer(destroy)
        const surface = createImperativeMenuSurface({ anchor: trigger.closest('.fff-flex-text-input__shell') || trigger, panel: list, ownershipAnchor: root, stablePlacement: true, width: seconds ? 100 : Math.max(180, trigger.closest('.fff-flex-text-input__shell')?.getBoundingClientRect().width || 180), align: 'end', presentation: 'panel', exclusive: !parent?.dataset.fffDropdownOwner,
            ownerIdPrefix: 'fff-date-clock', onClose: () => { const restoreFocus = list.contains(document.activeElement); trigger.setAttribute('aria-expanded', 'false'); trigger.removeAttribute('aria-controls'); destroy(); if (!disposed && restoreFocus) trigger.focus({ preventScroll: true }) } })
        list.id = `${surface.resolveMenuOverlayId()}-list`
        trigger.setAttribute('aria-controls', list.id)
        active = { trigger, list, surface }
        menuScope.defer(surface.$watch('menuReady', ready => { if (!ready) return; const selected = options.find(option => option.getAttribute('aria-selected') === 'true' && !option.disabled) || options.find(option => !option.disabled); if (selected) { for (const option of options) option.tabIndex = option === selected ? 0 : -1; scroller.scrollTop = Math.max(0, selected.offsetTop - scroller.clientHeight / 2 + selected.offsetHeight / 2); selected.focus({ preventScroll: true }) } }))
        menuScope.listen(list, 'click', event => { const option = event.target.closest('[data-time]'); if (option && !option.disabled && valid(option.dataset.time)) { set(option.dataset.time); surface.closeTeleportedMenu() } })
        menuScope.listen(list, 'keydown', event => {
            const enabled = options.filter(option => !option.disabled), currentIndex = enabled.indexOf(event.target), nextIndex = event.key === 'ArrowDown' ? (currentIndex + 1) % enabled.length : event.key === 'ArrowUp' ? (currentIndex - 1 + enabled.length) % enabled.length : event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : null
            if (nextIndex === null || !enabled.length) return
            event.preventDefault(); for (const option of options) option.tabIndex = -1; enabled[nextIndex].tabIndex = 0; enabled[nextIndex].focus({ preventScroll: true }); const target = enabled[nextIndex]; if (target.offsetTop < scroller.scrollTop) scroller.scrollTop = target.offsetTop; else if (target.offsetTop + target.offsetHeight > scroller.scrollTop + scroller.clientHeight) scroller.scrollTop = target.offsetTop + target.offsetHeight - scroller.clientHeight
        })
        trigger.setAttribute('aria-expanded', 'true'); surface.menuOpen = true
    }
    return {
        toggle(seconds = false) { if (!disposed && !trigger.disabled && !config.disabled && !config.readOnly) popup(seconds) },
        contains(target) { return Boolean(active?.list.contains(target)) },
        close() { active?.surface.closeTeleportedMenuImmediate() },
        dispose() { if (disposed) return; disposed = true; scope.dispose() },
    }
}
