import { createSelectionMotion, createGridSelectionMotion } from '../selection-motion.js'
import { springTransition } from '../spring.js'
import { createRangeSurfaceMotion } from './surface-motion.js'
import { resolveOverlayViewport } from '../../../overlay/geometry/viewport.js'
import { rangeValueTypography } from './value-typography.js'
import { startOfMonth, parseDate } from '@internationalized/date'
import { createResourceScope } from '../../../runtime/lifecycle/resource-scope.js'
import { createImperativeMenuSurface } from '../../../overlay/menu/imperative-surface.js'
import { dateValidation } from '../../domain/civil-date.js'
import { fitTime } from '../../domain/local-date-time.js'
import { selectionValidation, showSelection } from '../../domain/selection.js'
import { canonicalDate, createCalendarModel } from '../model.js'
import { createCalendarAvailability } from '../availability.js'
import { createCalendarMotion } from '../motion.js'

function element(tag, className, text) {
    const node = document.createElement(tag)
    if (className) node.className = className
    if (text !== undefined) node.textContent = text
    return node
}
const ordered = (a, b) => a <= b ? { start: a, end: b } : { start: b, end: a }

export function createDateRangeCalendar({ root, anchor, config, value, temporalValue, clockFactory, request, select, closed }) {
    const scope = createResourceScope(), model = createCalendarModel(config, value()?.start)
    const panel = root.querySelector('[data-fff-calendar-template]').content.firstElementChild.cloneNode(true)
    const viewport = panel.querySelector('[data-range-viewport]'), content = panel.querySelector('[data-range-content]')
    const dummy = element('span'); dummy.hidden = true
    const motion = createCalendarMotion(viewport, dummy, { animateHeight: false })
    const summary = panel.querySelector('[data-range-summary]'), count = panel.querySelector('[data-range-count]'), announcement = panel.querySelector('[data-calendar-announcement]')
    const sourceInput = anchor.querySelector('input'), rollingValue = root.querySelector('[data-fff-date-value]')
    const sourceValue = rollingValue?.getClientRects().length ? rollingValue : sourceInput
    const sourceStyle = sourceValue ? getComputedStyle(sourceValue) : null
    const valueColor = sourceStyle?.color, placeholderColor = sourceInput ? getComputedStyle(sourceInput, '::placeholder').color : valueColor
    if (sourceStyle) Object.assign(summary.style, rangeValueTypography(sourceStyle))
    const apply = panel.querySelector('[data-command="apply"]'), status = panel.querySelector('[data-calendar-status]'), retry = panel.querySelector('[data-command="retry"]')
    const highlightAnimations = new Map(), highlightPaths = new WeakMap(), endpoints = new Map(); let presetMotion = null
    const hasPresets = config.rangePresets === null || config.rangePresets.length > 0
    const wideWidth = hasPresets ? 712 : 554
    const compactLayout = () => config.calendarMonths === 1 || window.innerWidth < wideWidth + 32
    let month = model.initialMonth, draft = value(), start = null, hover = null, open = false, disposed = false, focus = value()?.start || model.today, windows = [], compact = compactLayout()
    const blocked = date => config.disabled || config.readOnly || dateValidation(date, config) || availability.pending || availability.unavailable(date)
    const availability = createCalendarAvailability(request, config.hasDateUnavailable, () => { if (open) updateAvailability() })
    let clock = null, clockTimes = [temporalValue?.()?.start.slice(11) || config.defaultTime, temporalValue?.()?.end.slice(11) || config.defaultTime]
    let renderedMonth = null, renderedCompact = null, opensAbove = null
    const surface = config.inline ? null : createImperativeMenuSurface({ anchor, panel, presentation: 'panel', stablePlacement: true, width: compact ? 352 : wideWidth, align: config.direction === 'rtl' ? 'end' : 'start', ownershipAnchor: root, containsMenuInteraction: event => clock?.contains(event.target), ownerIdPrefix: 'fff-date-range',
        resolveFloatingPosition: (rect = anchor.getBoundingClientRect()) => {
            const view = window.visualViewport, bounds = resolveOverlayViewport(anchor, window, 16)
            const top = Math.max(bounds.top, (view?.offsetTop ?? 0) + 16)
            const bottom = Math.min(bounds.bottom, (view?.offsetTop ?? 0) + (view?.height ?? window.innerHeight) - 16)
            const height = panel.offsetHeight
            if (opensAbove === null) opensAbove = rect.top + height > bottom && rect.bottom - top > bottom - rect.top
            return { left: config.direction === 'rtl' ? rect.right - panel.offsetWidth : rect.left,
                top: Math.max(top, Math.min(opensAbove ? rect.bottom - height : rect.top, bottom - height)) }
        },
        onClose: () => { open = false; start = hover = null; availability.suspend(); clock?.close(); surfaceMotion?.settle(); closed(panel.contains(document.activeElement)) } })
    const surfaceMotion = config.inline ? null : createRangeSurfaceMotion(panel, anchor)
    if (config.inline) { panel.classList.remove('fff-teleported-menu'); panel.classList.add('fff-dt-calendar--inline'); root.querySelector('[data-fff-calendar-inline]').append(panel) }
    function sizePanel() {
        compact = compactLayout()
        panel.classList.toggle('is-compact', compact)
        const width = Math.min(352, Math.max(0, window.innerWidth - 32))
        panel.style.setProperty('--fff-range-cell', `${compact ? Math.max(20, (width - 26) / 7) : 36}px`)
        if (surface) surface.resolveMenuMinWidth = () => compact ? 352 : wideWidth
        surfaceMotion?.setWidth(compact ? 352 : wideWidth)
    }
    const shown = () => start ? ordered(start, hover || start) : draft
    const composed = range => !range || !config.storesTime ? range : { start: `${range.start}T${fitTime(range.start, clockTimes[0], config)}`, end: `${range.end}T${fitTime(range.end, clockTimes[1], config)}` }
    function updateAvailability() {
        for (const day of content.querySelectorAll('[data-date]')) day.disabled = Boolean(blocked(day.dataset.date))
        status.hidden = !availability.pending
        status.textContent = availability.error ? config.labels.availability_error : config.labels.loading
        retry.hidden = !availability.error
        content.setAttribute('aria-busy', String(availability.pending && !availability.error))
        paint(); tabStop()
    }
    function tabStop() {
        const days = [...content.querySelectorAll('[data-date]:not(:disabled)')]
        const active = days.find(day => day.dataset.date === focus) || days[0]
        for (const day of content.querySelectorAll('[data-date]')) day.tabIndex = day === active ? 0 : -1
    }
    function animateBar(bar, next) {
        const visible = !bar.hidden, current = highlightAnimations.get(bar), track = highlightPaths.get(bar)
        const progress = current?.effect.getComputedTiming().progress ?? 1
        const previous = track ? { left: track.from.left + (track.to.left - track.from.left) * progress,
            width: track.from.width + (track.to.width - track.from.width) * progress } : next
        highlightAnimations.get(bar)?.cancel()
        const frame = value => ({ left: `${value.left}%`, width: `${value.width}%` })
        Object.assign(bar.style, frame(next)); bar.hidden = next.width === 0
        highlightPaths.set(bar, { from: previous, to: next })
        if (!visible || bar.hidden || matchMedia('(prefers-reduced-motion: reduce)').matches || !bar.animate) return
        if (Math.abs(previous.width - next.width) < .01 && Math.abs(previous.left - next.left) < .01) return
        const animation = bar.animate([frame(previous), frame(next)], springTransition(.3, .04))
        highlightAnimations.set(bar, animation)
        animation.finished.catch(() => {}).then(() => { if (highlightAnimations.get(bar) === animation) highlightAnimations.delete(bar) })
    }
    function paint() {
        const range = shown()
        const timed = Boolean(range && compact && config.storesTime)
        summary.parentElement.classList.toggle('is-timed', timed)
        if (timed) {
            const selection = composed(range), endpointConfig = { ...config, mode: 'date', compactRangeLabel: false }
            summary.replaceChildren(...[selection.start, selection.end].map(value => element('span', 'fff-dt-range__summary-endpoint', showSelection(value, endpointConfig))))
        } else summary.textContent = range ? showSelection(composed(range), config)
            : (sourceInput?.value || sourceInput?.placeholder || config.placeholder || '')
        const color = range || sourceInput?.value ? valueColor : placeholderColor
        if (color && summary.style.color !== color) summary.style.color = color
        count.textContent = range ? start && hover === start ? config.labels.pick_end : `${Math.round((Date.parse(`${range.end}T00:00:00Z`) - Date.parse(`${range.start}T00:00:00Z`)) / 86400000) + 1} ${config.labels.days}` : ''
        apply.disabled = !range || Boolean(blocked(range.start) || blocked(range.end) || selectionValidation(composed(range), config))
        if (range && config.storesTime) { clockTimes = [fitTime(range.start, clockTimes[0], config), fitTime(range.end, clockTimes[1], config)]; clock?.render() }
        for (const [weeks, markers] of endpoints) {
            markers[0].set(range ? weeks.querySelector(`[data-date="${range.start}"]`) : null)
            markers[1].set(range && range.end !== range.start ? weeks.querySelector(`[data-date="${range.end}"]`) : null)
        }
        for (const preset of presets.querySelectorAll('button')) preset.setAttribute('aria-pressed', String(Boolean(range && !start && preset.dataset.presetStart === range.start && preset.dataset.presetEnd === range.end)))
        presetMotion?.set(presets.querySelector('button[aria-pressed="true"]'))
        for (const row of content.querySelectorAll('[data-range-week]')) {
            const days = [...row.querySelectorAll('[data-date]')]
            const inRange = days.filter(day => range && day.dataset.date >= range.start && day.dataset.date <= range.end)
            for (const day of days) {
                const edge = range && (day.dataset.date === range.start || day.dataset.date === range.end)
                day.toggleAttribute('data-edge', Boolean(edge)); day.setAttribute('aria-selected', String(inRange.includes(day)))
            }
            const first = inRange[0], last = inRange.at(-1), bar = row.querySelector('[data-range-bar]')
            const left = first ? Math.min(Number(first.dataset.column), Number(last.dataset.column)) : 0
            animateBar(bar, { left: left * 100 / 7, width: first ? inRange.length * 100 / 7 : 0 })
        }
    }
    function monthPane(date) {
        const info = model.weeks(date); windows.push(info)
        const pane = element('section', 'fff-dt-range__month')
        pane.append(element('div', 'fff-dt-range__month-title', `${model.monthLabel(date)} ${model.yearLabel(date)}`))
        const grid = element('div'); grid.setAttribute('role', 'grid'); grid.setAttribute('aria-label', model.dateLabel(date))
        const weekdays = element('div', 'fff-dt-range__weekdays'); weekdays.setAttribute('role', 'row')
        for (const name of info.weekdays) { const day = element('span', '', name.slice(0, 2)); day.setAttribute('role', 'columnheader'); weekdays.append(day) }
        grid.append(weekdays)
        const weeks = element('div', 'fff-dt-range__weeks')
        const markers = Array.from({ length: 2 }, () => { const marker = element('span', 'fff-dt-range__thumb'); marker.setAttribute('aria-hidden', 'true'); weeks.append(marker); return createGridSelectionMotion(marker) })
        endpoints.set(weeks, markers)
        for (const [rowIndex, week] of info.weeks.entries()) {
            const row = element('div', 'fff-dt-range__week'); row.dataset.rangeWeek = ''; row.setAttribute('role', 'row')
            const bar = element('span', 'fff-dt-range__bar'); bar.dataset.rangeBar = ''; bar.setAttribute('aria-hidden', 'true'); bar.hidden = true; row.append(bar)
            for (const [columnIndex, item] of week.entries()) {
                if (item.outside) { const blank = element('span'); blank.setAttribute('role', 'gridcell'); row.append(blank); continue }
                const day = element('button', 'fff-dt-range__day'); day.type = 'button'; day.dataset.date = item.value; day.setAttribute('role', 'gridcell'); day.setAttribute('aria-label', model.dateLabel(item.date))
                day.dataset.column = String(config.direction === 'rtl' ? 6 - columnIndex : columnIndex); day.dataset.row = String(rowIndex)
                day.append(element('span', '', model.dayLabel(item.date)))
                if (config.highlightToday && item.value === model.today) day.setAttribute('aria-current', 'date')
                row.append(day)
            }
            weeks.append(row)
        }
        grid.append(weeks)
        pane.append(grid); return pane
    }
    function render(direction = 0) {
        if (!direction && renderedMonth === month.toString() && renderedCompact === compact) {
            availability.load(windows[0].start, windows.at(-1).end)
            updateAvailability()
            return
        }
        renderedMonth = month.toString(); renderedCompact = compact
        for (const animation of highlightAnimations.values()) animation.cancel()
        highlightAnimations.clear(); for (const markers of endpoints.values()) for (const marker of markers) marker.dispose(); endpoints.clear(); windows = []
        const next = element('div', 'fff-dt-range__months')
        for (let index = 0; index < (compact ? 1 : 2); index++) next.append(monthPane(month.add({ months: index })))
        motion.replace(content, next, config.direction === 'rtl' ? -direction : direction)
        availability.load(windows[0].start, windows.at(-1).end)
        updateAvailability()
        for (const [command, sign] of [['previous', -1], ['next', 1]]) panel.querySelector(`[data-command="${command}"]`).disabled = !model.monthAllowed(month.add({ months: sign }))
        if (surface?.menuReady) { surface.updateMenuPosition({ reveal: false }) }
    }
    function move(next, focusDay = false) {
        if (!model.monthAllowed(next)) return
        const direction = next.compare(month) >= 0 ? 1 : -1
        month = startOfMonth(next); render(direction)
        if (focusDay) content.querySelector(`[data-date="${focus}"]`)?.focus({ preventScroll: true })
    }
    function choose(date) {
        if (blocked(date)) return
        focus = date
        if (!start) { start = hover = date }
        else { draft = ordered(start, date); start = hover = null }
        paint(); tabStop()
        announcement.textContent = `${summary.textContent}, ${count.textContent}`
    }
    const presets = panel.querySelector('[data-range-presets]')
    const presetMarker = element('span', 'fff-dt-range__preset-marker'); presetMarker.setAttribute('aria-hidden', 'true'); presets.append(presetMarker); presetMotion = createSelectionMotion(presetMarker, presets)
    const today = parseDate(model.today)
    const builtins = [
        ['today', today, today], ['yesterday', today.subtract({ days: 1 }), today.subtract({ days: 1 })],
        ['last_7_days', today.subtract({ days: 6 }), today], ['last_30_days', today.subtract({ days: 29 }), today],
        ['this_month', startOfMonth(today), today], ['last_month', startOfMonth(today.subtract({ months: 1 })), startOfMonth(today).subtract({ days: 1 })],
        ['this_quarter', today.set({ month: Math.floor((today.month - 1) / 3) * 3 + 1, day: 1 }), today], ['year_to_date', today.set({ month: 1, day: 1 }), today],
    ]
    const choices = config.rangePresets ?? builtins.map(([key, first, last]) => ({ label: config.labels[key], start: canonicalDate(first), end: canonicalDate(last) }))
    presets.hidden = !hasPresets
    for (const choice of choices) {
        const button = element('button', 'fff-dt-range__preset', choice.label); button.type = 'button'
        button.dataset.presetStart = choice.start; button.dataset.presetEnd = choice.end
        button.disabled = Boolean(dateValidation(choice.start, config) || dateValidation(choice.end, config) || config.disabled || config.readOnly)
        presets.append(button)
    }
    scope.listen(panel, 'click', event => {
        const button = event.target.closest('button'); if (!button || button.disabled) return
        if (button.dataset.date) return choose(button.dataset.date)
        if (button.dataset.presetStart) {
            const range = { start: button.dataset.presetStart, end: button.dataset.presetEnd }
            if (dateValidation(range.start, config) || dateValidation(range.end, config)) return
            draft = range; start = hover = null; focus = range.start
            move(model.convert(range.start)); paint(); announcement.textContent = `${summary.textContent}, ${count.textContent}`; return
        }
        if (button.dataset.command === 'apply') { const range = shown(); if (range && !apply.disabled) { select(composed(range)); if (!config.inline) surface.closeTeleportedMenu() } }
        else if (button.dataset.command === 'clear' && config.clearable && !config.disabled && !config.readOnly) { select(null); draft = start = hover = null; paint(); if (!config.inline) surface.closeTeleportedMenu() }
        else if (button.dataset.command === 'cancel') { if (config.inline) { draft = value(); start = hover = null; paint() } else surface.closeTeleportedMenu() }
        else if (button.dataset.command === 'next' || button.dataset.command === 'previous') move(month.add({ months: button.dataset.command === 'next' ? 1 : -1 }))
        else if (button.dataset.command === 'retry') availability.load(windows[0].start, windows.at(-1).end, true)
    })
    scope.listen(presets, 'keydown', event => { const buttons = [...presets.querySelectorAll('button:not(:disabled)')], at = buttons.indexOf(event.target), next = compact ? { ArrowLeft: config.direction === 'rtl' ? 1 : -1, ArrowRight: config.direction === 'rtl' ? -1 : 1 }[event.key] : { ArrowUp: -1, ArrowDown: 1 }[event.key]; if (next !== undefined && at >= 0 && buttons.length) { event.preventDefault(); buttons[(at + next + buttons.length) % buttons.length].focus({ preventScroll: true }) } })
    scope.listen(content, 'pointerover', event => { const day = event.target.closest('[data-date]'); if (start && day && !day.disabled && hover !== day.dataset.date) { hover = day.dataset.date; paint() } })
    scope.listen(content, 'pointerleave', () => { if (start) { hover = start; paint() } })
    scope.listen(content, 'keydown', event => {
        const day = event.target.closest('[data-date]'); if (!day || event.altKey || event.metaKey || event.ctrlKey) return
        const date = model.convert(day.dataset.date)
        const moves = { ArrowLeft: { days: config.direction === 'rtl' ? 1 : -1 }, ArrowRight: { days: config.direction === 'rtl' ? -1 : 1 }, ArrowUp: { days: -7 }, ArrowDown: { days: 7 }, PageUp: event.shiftKey ? { years: -1 } : { months: -1 }, PageDown: event.shiftKey ? { years: 1 } : { months: 1 } }
        let next = moves[event.key] ? date.add(moves[event.key]) : null
        if (event.key === 'Home' || event.key === 'End') { const days = [...day.parentElement.querySelectorAll('[data-date]')]; next = model.convert(days[event.key === 'Home' ? 0 : days.length - 1].dataset.date) }
        if (!next) return
        event.preventDefault(); const key = canonicalDate(next)
        if (dateValidation(key, config)) return
        focus = key
        if (start) { hover = key; paint() }
        if (!content.querySelector(`[data-date="${key}"]`)) move(next, true)
        else { tabStop(); content.querySelector(`[data-date="${key}"]:not(:disabled)`)?.focus({ preventScroll: true }) }
    })
    scope.listen(content, 'focusin', event => { if (event.target.dataset.date) { focus = event.target.dataset.date; tabStop(); if (start) { hover = focus; paint() } } })
    if (config.withTime && clockFactory) clock = clockFactory(root, panel, config, {
        get: index => clockTimes[index],
        set: (index, time) => { clockTimes[index] = time; paint() },
        valid: (index, time) => { const range = shown(); return !range || fitTime(index === 0 ? range.start : range.end, time, config) === time },
    })
    if (surface) scope.defer(surface.$watch('menuReady', ready => { if (ready) {
        content.querySelector('[tabindex="0"]')?.focus({ preventScroll: true })
        surfaceMotion.enter()
    } }))
    scope.listen(window, 'resize', () => {
        const previous = compact
        opensAbove = null
        sizePanel()
        if (open) { if (previous !== compact) render(); else { paint(); surface?.updateMenuPosition({ reveal: false }) } }
    })
    scope.defer(() => { clock?.dispose(); presetMotion?.dispose(); availability.dispose(); motion.dispose(); surfaceMotion?.dispose(); for (const animation of highlightAnimations.values()) animation.cancel(); highlightAnimations.clear(); for (const markers of endpoints.values()) for (const marker of markers) marker.dispose(); endpoints.clear(); surface?.dispose(); panel.remove() })
    return {
        open() { if (disposed) return; open = true; opensAbove = null; clockTimes = [temporalValue?.()?.start.slice(11) || config.defaultTime, temporalValue?.()?.end.slice(11) || config.defaultTime]; clock?.render(); draft = value(); start = hover = null; sizePanel(); panel.classList.toggle('is-dark', root.closest('.dark') !== null); month = draft ? startOfMonth(model.convert(draft.start)) : model.initialMonth; focus = draft?.start || model.today; render(); if (surface) { const resumed = surface.cancelClosing(); surface.menuOpen = true; if (resumed) surfaceMotion.enter() } },
        get closing() { return Boolean(surface?.__fffPanelClosing || surface?.__fffSheetClosing) },
        close() { if (!config.inline) surface?.closeTeleportedMenu() },
        setValue(range) { if (!disposed && open) { clock?.close(); clockTimes = [temporalValue?.()?.start.slice(11) || config.defaultTime, temporalValue?.()?.end.slice(11) || config.defaultTime]; clock?.render(); draft = range; start = hover = null; paint() } },
        dispose() { if (disposed) return; disposed = true; open = false; scope.dispose() },
    }
}
