export const clamp = (value, min = 0, max = 1) => Math.max(min, Math.min(max, Number.isFinite(Number(value)) ? Number(value) : min))
export function formatVideoTime(seconds) {
    const total = Math.floor(Math.max(0, Number.isFinite(seconds) ? seconds : 0))
    const hours = Math.floor(total / 3600), minutes = Math.floor(total / 60) % 60, rest = String(total % 60).padStart(2, '0')
    return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`
}
export const formatPlaybackRateLabel = rate => `${Number.isFinite(rate) && rate > 0 ? rate : 1}×`
export const resolveDefaultQualityKey = options => options?.find?.(option => option.default)?.key ?? options?.[0]?.key ?? null
export const resolveProgressHoverRatio = (x, left, width) => Number.isFinite(width) && width > 0 ? clamp((x - left) / width) : null
export const resolveScrubDuration = (duration, end) => Number.isFinite(duration) && duration > 0 ? duration : Number.isFinite(end) && end > 0 ? end : 0
export const resolveCoarsePointer = (query = null) => Boolean((query ?? globalThis.window?.matchMedia?.('(hover: none) and (pointer: coarse)'))?.matches)

export const VIDEO_FIELD_HOTKEYS = { togglePaused: [' ', 'k', 'K'], toggleMuted: ['m', 'M'], toggleFullscreen: ['f', 'F'], togglePictureInPicture: ['i', 'I'], seekBackShort: ['ArrowLeft'], seekForwardShort: ['ArrowRight'], volumeUp: ['ArrowUp'], volumeDown: ['ArrowDown'], escape: ['Escape'] }
export const isEditableTarget = target => Boolean(target && (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable))
export function resolveVideoHotkeyAction(event) {
    if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || isEditableTarget(event.target)) return null
    return Object.entries(VIDEO_FIELD_HOTKEYS).find(([, keys]) => keys.includes(event.key))?.[0] ?? null
}
export const TAP_THRESHOLD_MS = 250, DOUBLE_TAP_WINDOW_MS = 200
export function resolveGestureRegion(x, rect) { return !rect?.width ? 'center' : x < rect.left + rect.width / 3 ? 'left' : x > rect.left + rect.width * 2 / 3 ? 'right' : 'center' }
export const createTapGestureTracker = () => ({ lastTapAt: 0, lastTapRegion: null, pointerDownAt: 0, pointerDownX: 0, pointerDownY: 0, pointerId: null })
export function beginTapGesture(tracker, event) {
    Object.assign(tracker, { pointerDownAt: Date.now(), pointerDownX: event.clientX, pointerDownY: event.clientY ?? 0, pointerId: event.pointerId ?? null })
}
export function resolveTapGesture(tracker, event, rect) {
    if (Date.now() - tracker.pointerDownAt > TAP_THRESHOLD_MS || (event.pointerId != null && tracker.pointerId !== event.pointerId) || Math.hypot((event.clientX ?? tracker.pointerDownX) - tracker.pointerDownX, (event.clientY ?? tracker.pointerDownY) - tracker.pointerDownY) > 12) return null
    const region = resolveGestureRegion(event.clientX ?? tracker.pointerDownX, rect), now = Date.now()
    const isDoubleTap = tracker.lastTapAt > 0 && now - tracker.lastTapAt <= DOUBLE_TAP_WINDOW_MS && tracker.lastTapRegion === region
    tracker.lastTapAt = isDoubleTap ? 0 : now
    tracker.lastTapRegion = region
    return { region, isDoubleTap }
}
