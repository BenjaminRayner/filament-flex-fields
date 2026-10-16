/** Currency is an amount editor. Its picker is a composed Select instance. */
export { default } from '../currency/runtime/controller.js'
export { getLocaleSeparators, groupWholeDigits } from '../currency/domain/format.js'
export { editStateFromMinor, minorFromEditState, normalizeEditForBlur } from '../currency/domain/state.js'
export { hasDecimalSection, getLogicalLength, getNavigableCursorPositions, snapCursor, moveCursorByDelta, cursorPosFromClientX } from '../currency/domain/cursor.js'
export { applyTypeDigit, animatedDigitKeyForWholeInsert, applyTypeDigitAtCursor, deleteBeforeCursor, deleteAtCursor } from '../currency/domain/editing.js'
export { buildDisplaySegments, buildDisplayItems } from '../currency/domain/digits.js'
export { normalizeLocale } from '../core/number-format.js'
