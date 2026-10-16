// Compatibility tag only. Transport, ownership and readiness live in shared ESM.
const startup = window[Symbol.for('fff.core.startup.v1')]
const url = document.currentScript?.dataset.fffRuntimeSrc
const ready = startup ? startup.start() : import(url).then(module => {
    module.installCoreRuntime()
    return module
})
void ready.catch(() => document.dispatchEvent(new CustomEvent('fff-core-runtime-error')))
