/**
 * Early boot for x-fff-load — must load in SCRIPTS_BEFORE @filamentScripts
 * so the directive registers on alpine:init before Alpine.start().
 */
import { installStartupGate } from './startup/startup-gate.js'
import { observeStartupDemand } from './startup/demand-startup.js'

const script = document.currentScript
const startup = installStartupGate({ document, moduleUrl: script?.dataset.fffRuntimeSrc })
observeStartupDemand(document, startup)
