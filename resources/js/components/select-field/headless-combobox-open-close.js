import { mergeAlpineComponentData } from "../../support/merge-alpine-component-data.js";
import { createSurfaceOpeningMixin } from "../../select/adapters/surface/opening.js";
import { createSurfaceFocusMixin } from "../../select/adapters/surface/focus.js";
import { createSurfaceInputMixin } from "../../select/adapters/surface/input.js";
export function createHeadlessComboboxOpenCloseMixin() {
  return mergeAlpineComponentData({}, createSurfaceOpeningMixin({}), createSurfaceFocusMixin({}), createSurfaceInputMixin({}));
}
