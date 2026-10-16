/** Existing imports retain their contract while traversal and row layout stay separate. */
export {
    isHeadlessOptionGroup, headlessOptionIsDisabled, headlessOptionValue,
    headlessOptionLabelHtml, flattenHeadlessOptions, findHeadlessOptionRecord,
    filterHeadlessOptionTree, limitHeadlessOptionTree,
} from '../../select/options/option-tree.js'
export {
    HEADLESS_DROPDOWN_ROW_HEIGHTS, buildHeadlessDropdownRows,
    flattenHeadlessDropdownRowsForVirtualization, flattenHeadlessDropdownRowOptions,
} from '../../select/options/row-projection.js'
