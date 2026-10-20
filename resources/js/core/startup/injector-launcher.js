// Compatibility tag only. Transport, ownership and readiness live in shared ESM.
const startup = window[Symbol.for('fff.core.startup.v1')]
const url = document.currentScript?.dataset.fffRuntimeSrc
// The synchronous bootstrap discovers consumers and guards native actions.
// An injector-only legacy layout retains its eager compatibility behavior.
const ready = startup ? null : import(url).then(module => {
    module.installCoreRuntime()
    return module
})
void ready?.catch(() => document.dispatchEvent(new CustomEvent('fff-core-runtime-error')))
