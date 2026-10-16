import { mergeAlpineComponentData } from "../../support/merge-alpine-component-data.js";
import { createLayoutWidthMixin } from "../../select/adapters/layout/width.js";
import { createLayoutPositionMixin } from "../../select/adapters/layout/position.js";
import { createLayoutObserversMixin } from "../../select/adapters/layout/observers.js";
/** Fixed desktop width for optionLayout('grid') theme-style pickers. */
export const GRID_DROPDOWN_WIDTH_PX = 400;
export function createHeadlessComboboxMenuPositionMixin({
  selectMenu
} = {}) {
  return mergeAlpineComponentData({}, createLayoutWidthMixin({
    GRID_DROPDOWN_WIDTH_PX
  }), createLayoutPositionMixin({
    selectMenu
  }), createLayoutObserversMixin({}));
}
