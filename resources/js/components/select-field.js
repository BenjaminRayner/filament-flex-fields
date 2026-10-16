/**
 * SelectField Alpine entry — headless combobox (v3+ / FFART).
 */
export {
    findTriggerLabelInOptions,
    populateRepositoryWithTriggerLabels,
    resolveTriggerLabel,
} from './select-field/select-field-trigger-labels.js';

import { getAlpineLoadGate } from '../core/flex-alpine-load-gate.js';
import headlessComboboxAlpine from './select-field/headless-combobox-alpine.js';

const gate = getAlpineLoadGate();

export function markSelectFieldShellAttached(shell, attached) {
    if (! shell) {
        return;
    }

    shell.dataset.fffSelectAttached = attached ? 'true' : 'false';
}

export function fffHeadlessSelectField(config = {}) {
    return headlessComboboxAlpine(config);
}

export default fffHeadlessSelectField;

// Import-time registration and lazy preparation must identify the same source.
export const fffFactoryIdentity = Object.freeze({
    package: 'janczakb/filament-flex-fields', component: 'select-field', export: 'default', abi: 1,
});
gate.registerAlpineData('fffHeadlessSelectField', fffHeadlessSelectField, {
    ...fffFactoryIdentity, module: import.meta.url,
});

if (typeof window !== 'undefined') {
    window.__fffSelectFieldModuleLoaded = true;
}
