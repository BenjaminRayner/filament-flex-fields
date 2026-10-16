import { createSelectionCommandsMixin } from '../../select/adapters/alpine/selection-commands.js'
import { createSelectionPresentationMixin } from '../../select/adapters/alpine/selection-presentation.js'
import { createSelectionChecksMixin } from '../../select/adapters/alpine/selection-checks.js'

/** Compatibility surface; command, rendering and animation policies are separate. */
export function createHeadlessComboboxSelectionMixin() {
    return {...createSelectionCommandsMixin(), ...createSelectionPresentationMixin(), ...createSelectionChecksMixin()}
}
