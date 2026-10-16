/**
 * Early boot for x-fff-load — must load in SCRIPTS_BEFORE @filamentScripts
 * so the directive registers on alpine:init before Alpine.start().
 */
import { installStartupGate } from './startup/startup-gate.js'

const script = document.currentScript
const startup = installStartupGate({ document, moduleUrl: script?.dataset.fffRuntimeSrc })
void startup.start().catch(() => {
    document.dispatchEvent(new CustomEvent('fff-core-runtime-error'))
})
