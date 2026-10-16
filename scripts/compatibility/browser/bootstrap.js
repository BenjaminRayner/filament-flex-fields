import { Alpine } from '../../../vendor/livewire/livewire/dist/livewire.esm.js'
import filamentModal from '../../../vendor/filament/support/resources/js/components/modal.js'
import tabsSchemaComponent from '../../../vendor/filament/schemas/resources/js/components/tabs.js'
import '../../../vendor/filament/schemas/resources/js/index.js'
window.Livewire = { hook: () => () => {}, interceptMessage: () => () => {} }
window.Alpine = Alpine
Alpine.magic('wire', () => ({ $get: () => null, $set: () => {} }))
Alpine.directive('load', () => {})
Alpine.data('filamentModal', filamentModal)
Alpine.data('tabsSchemaComponent', tabsSchemaComponent)
Alpine.start()
setInterval(() => {
    const runtime = window.FffAssetInjector
    if (!runtime) return
    const graph = runtime.getConsumerGraph()
    const links = [...document.querySelectorAll('link[data-fff-managed-asset]')]
    const css = links.filter(link => link.rel === 'stylesheet').map(link => ({ file: new URL(link.href).pathname.split('/').pop(), refs: graph.getRefCount(link.href) }))
    document.querySelector('#audit-report').textContent = JSON.stringify({
        stack: runtime.getModalOpenStack(), css,
        duplicateLinks: links.length - new Set(links.map(link => `${link.rel}:${link.href}`)).size,
        initialized: [...document.querySelectorAll('[data-probe-initialized]')].map(el => el.parentElement.innerText.trim()),
        inflight: runtime.getInflightUrls().length,
    }, null, 2)
}, 100)
