export function createClassList() {
    const classes = new Set()

    return {
        add: (...names) => {
            for (const name of names) {
                if (name) {
                    classes.add(name)
                }
            }
        },
        remove: (...names) => {
            for (const name of names) {
                if (name) {
                    classes.delete(name)
                }
            }
        },
        contains: (name) => classes.has(name),
        toggle(name, force = !classes.has(name)) {
            if (force) classes.add(name)
            else classes.delete(name)
            return force
        },
    }
}

export function createLink({ href, rel = 'stylesheet', attributes = {} }) {
    const listeners = new Map()
    const classList = createClassList()

    const link = {
        rel,
        href,
        parentElement: null,
        get parentNode() { return this.parentElement },
        get isConnected() { return this.parentElement?.isConnected === true },
        dataset: {},
        classList,
        setAttribute(name, value) {
            attributes[name] = value
        },
        getAttribute(name) {
            return attributes[name] ?? null
        },
        hasAttribute(name) {
            return Object.hasOwn(attributes, name)
        },
        addEventListener(type, listener, options = {}) {
            listeners.set(type, { listener, options })
        },
        removeEventListener(type, listener) {
            if (listeners.get(type)?.listener === listener) listeners.delete(type)
        },
        remove() {
            if (link.parentElement?.children) {
                const index = link.parentElement.children.indexOf(link)

                if (index >= 0) {
                    link.parentElement.children.splice(index, 1)
                }
            }

            link.parentElement = null
        },
        dispatchEvent(type) {
            if (type === 'load' && link.rel === 'stylesheet') {
                link.sheet = {}
            }

            if (type === 'load' && link.rel === 'modulepreload') {
                link.loaded = true
            }

            const entry = listeners.get(type)

            if (! entry) {
                return
            }

            if (entry.options.once) listeners.delete(type)
            entry.listener()
        },
    }

    return link
}

export function createElement(tagName) {
    if (tagName === 'link') {
        return createLink({ href: '' })
    }

    const element = {
        tagName: tagName.toUpperCase(),
        nodeType: 1,
        get parentNode() { return this.parentElement },
        children: [],
        attributes: {},
        classList: createClassList(),
        parentElement: null,
        dataset: {},
        style: {},
        id: '',
        get isConnected() {
            return this.parentElement?.isConnected === true
        },
        appendChild(child) {
            child.parentElement = element
            element.children.push(child)

            return child
        },
        closest(selector) {
            let node = this
            const selectors = String(selector).split(',').map((part) => part.trim()).filter(Boolean)

            while (node) {
                for (const candidate of selectors) {
                    if (node.matches?.(candidate)) {
                        return node
                    }
                }

                node = node.parentElement
            }

            return null
        },
        querySelector(selector) {
            return this.querySelectorAll(selector)[0] ?? null
        },
        querySelectorAll(selector) {
            const matches = []

            const nodeMatches = (node) => {
                if (node.matches?.(selector)) {
                    return true
                }

                if (selector.includes('stylesheet') && node.rel === 'stylesheet' && node.href?.includes('filament-flex-fields')) {
                    return true
                }

                if (selector.includes('modulepreload') && node.rel === 'modulepreload' && node.href?.includes('filament-flex-fields')) {
                    return true
                }

                return false
            }

            const walk = (node) => {
                if (nodeMatches(node)) {
                    matches.push(node)
                }

                for (const child of node.children ?? []) {
                    walk(child)
                }
            }

            for (const child of this.children) walk(child)

            return matches
        },
        matches(selector) {
            if (selector === '[data-fff-asset-batch][data-fff-asset-consumer]') return this.hasAttribute('data-fff-asset-batch') && this.hasAttribute('data-fff-asset-consumer')
            if (selector === '[data-fff-asset-batch]') {
                return this.hasAttribute?.('data-fff-asset-batch') ?? false
            }

            if (selector === '[data-fff-asset-consumer]') {
                return Boolean(this.dataset?.fffAssetConsumer ?? this.attributes?.['data-fff-asset-consumer'])
            }

            if (selector === '[data-fff-lazy-alpine-mount]') {
                return this.hasAttribute?.('data-fff-lazy-alpine-mount') ?? false
            }

            if (selector.startsWith('.')) {
                for (const className of selector.split(/[\s,]+/).filter(Boolean)) {
                    if (! className.startsWith('.')) {
                        continue
                    }

                    if (! this.classList?.contains?.(className.slice(1))) {
                        return false
                    }
                }

                return selector.split(/[\s,]+/).some((token) => token.startsWith('.'))
            }

            if (selector === '[data-fff-segment-overflow]' || selector.startsWith('[data-fff-segment-overflow]:not(')) {
                if (! this.hasAttribute('data-fff-segment-overflow')) {
                    return false
                }

                if (selector.includes('data-ssr-scroll-positioned')) {
                    return this.dataset.ssrScrollPositioned !== 'true'
                }

                return true
            }

            if (selector === '[data-segment-selected="true"]') {
                return this.getAttribute('data-segment-selected') === 'true'
            }

            if (selector === '.fi-modal') {
                return this.classList.contains('fi-modal')
            }

            return false
        },
        setAttribute(name, value) {
            this.attributes[name] = value
        },
        removeAttribute(name) {
            delete this.attributes[name]
        },
        getAttribute(name) {
            return this.attributes?.[name] ?? null
        },
        hasAttribute(name) {
            return Object.hasOwn(this.attributes ?? {}, name)
        },
        remove() {
            if (this.parentElement?.children) {
                const index = this.parentElement.children.indexOf(this)

                if (index >= 0) {
                    this.parentElement.children.splice(index, 1)
                }
            }

            this.parentElement = null
        },
        addEventListener() {},
        removeEventListener() {},
        dispatchEvent() {},
        contains(node) {
            if (node === this) return true
            const walk = (parent) => {
                for (const child of parent.children ?? []) {
                    if (child === node) {
                        return true
                    }

                    if (walk(child)) {
                        return true
                    }
                }

                return false
            }

            return walk(this)
        },
    }

    return element
}

export function createAssetBatch(stylesheets, chunks = [], { consumerComponent = null, livewireKey = 'test.consumer' } = {}) {
    const batch = createElement('span')
    batch.attributes = {
        'data-fff-asset-batch': '',
        'data-fff-stylesheets': JSON.stringify(stylesheets),
        'data-fff-chunks': JSON.stringify(chunks),
        'data-fff-asset-sizes': JSON.stringify(Object.fromEntries([...stylesheets, ...chunks].map(url => [url, 1024]))),
    }

    const resolvedComponent = consumerComponent ?? (() => {
        for (const href of stylesheets) {
            const match = String(href).match(/flex-fields-([^./]+)\.css/)

            if (match?.[1]) {
                return match[1]
            }
        }

        for (const href of chunks) {
            const match = String(href).match(/flex-fields-([^.]+)\.js/)

            if (match?.[1]) {
                return match[1]
            }
        }

        return null
    })()

    const componentForCrg = resolvedComponent
        ?? ((stylesheets.length > 0 || chunks.length > 0) ? 'flex-asset-batch' : null)

    if (componentForCrg && livewireKey) {
        batch.attributes['data-fff-asset-consumer'] = componentForCrg
        batch.attributes['data-fff-asset-consumer-id'] = livewireKey
        batch.dataset.fffAssetConsumer = componentForCrg
        batch.dataset.fffAssetConsumerId = livewireKey
    }

    return batch
}

export function stylesheetHrefs(head) {
    return head.children
        .filter((child) => child.rel === 'stylesheet' && child.href?.includes('filament-flex-fields'))
        .map((child) => child.href)
}

export function chunkHrefs(head) {
    return head.children
        .filter((child) => child.rel === 'modulepreload' && child.href?.includes('filament-flex-fields'))
        .map((child) => child.href)
}

export async function flushAssetLoads(head) {
    // Resolve transport after async activation schedules its links and frames.
    for (let turn = 0; turn < 4; turn += 1) {
        await new Promise(resolve => globalThis.setTimeout(resolve, 0))
        for (const child of [...head.children]) {
            if (
                (child.rel === 'stylesheet' || child.rel === 'modulepreload')
                && typeof child.dispatchEvent === 'function'
            ) {
                child.dispatchEvent('load')
            }
        }
    }
}

/** @deprecated Prefer flushAssetLoads */
export async function flushStylesheetLoads(head) {
    return flushAssetLoads(head)
}

export function css(name) {
    return `/css/janczakb/filament-flex-fields/flex-fields-${name}.css`
}

export function chunk(name) {
    return `/js/janczakb/filament-flex-fields/components/flex-fields-${name}.js`
}

export function createDom() {
    const querySelectorAllInTree = (root, selector) => {
        const matches = []

        const walk = (node) => {
            if (selector.includes('stylesheet') && node.rel === 'stylesheet' && node.href?.includes('filament-flex-fields')) {
                matches.push(node)
            }

            if (selector.includes('modulepreload') && node.rel === 'modulepreload' && node.href?.includes('filament-flex-fields')) {
                matches.push(node)
            }

            if (selector === '[data-fff-asset-batch]' && node.hasAttribute?.('data-fff-asset-batch')) {
                matches.push(node)
            }

            for (const child of node.children ?? []) {
                walk(child)
            }
        }

        walk(root)

        return matches
    }

    const head = {
        children: [],
        isConnected: true,
        appendChild(child) {
            child.parentElement = head
            head.children.push(child)
        },
        querySelectorAll(selector) {
            return querySelectorAllInTree(head, selector)
        },
    }
    const body = {
        children: [],
        isConnected: true,
        appendChild(child) {
            child.parentElement = body
            body.children.push(child)
        },
        querySelectorAll(selector) {
            return querySelectorAllInTree(body, selector)
        },
    }

    const nodes = []
    const byId = new Map()

    const document = {
        contains(node) {
            const walk = root => root === node || (root.children ?? []).some(walk)
            return walk(head) || walk(body)
        },
        baseURI: 'https://panel.test/admin',
        readyState: 'complete',
        head,
        body,
        createElement(tagName) {
            const element = createElement(tagName)

            if (! element.children) {
                element.children = []
            }

            let currentId = ''

            Object.defineProperty(element, 'id', {
                get() {
                    return currentId
                },
                set(value) {
                    if (currentId) {
                        byId.delete(currentId)
                    }

                    currentId = value ?? ''

                    if (currentId) {
                        byId.set(currentId, element)
                    }
                },
                configurable: true,
                enumerable: true,
            })

            nodes.push(element)

            return element
        },
        querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null },
        querySelectorAll(selector) {
            const matches = []
            const seen = new Set()

            const pushMatch = (node) => {
                if (seen.has(node)) {
                    return
                }

                seen.add(node)
                matches.push(node)
            }

            const consider = (node) => {
                if (selector.includes('stylesheet') && node.rel === 'stylesheet' && node.href?.includes('filament-flex-fields')) {
                    pushMatch(node)
                }

                if (selector.includes('modulepreload') && node.rel === 'modulepreload' && node.href?.includes('filament-flex-fields')) {
                    pushMatch(node)
                }

                if (selector.includes('fff-flex-fields-assets-pending') && node.classList?.contains('fff-flex-fields-assets-pending')) {
                    if (selector.includes(':not(.fi-modal)')) {
                        if (! node.classList.contains('fi-modal')) {
                            pushMatch(node)
                        }
                    } else {
                        pushMatch(node)
                    }
                }

                if (selector === '.fi-modal.fff-flex-fields-assets-pending'
                    && node.classList?.contains('fi-modal')
                    && node.classList.contains('fff-flex-fields-assets-pending')) {
                    pushMatch(node)
                }

                if (selector === '.fi-modal.fi-modal-open'
                    && node.classList?.contains('fi-modal')
                    && node.classList.contains('fi-modal-open')) {
                    pushMatch(node)
                }

                if (selector === '.fi-modal:not(.fi-modal-open)'
                    && node.classList?.contains('fi-modal')
                    && ! node.classList.contains('fi-modal-open')) {
                    pushMatch(node)
                }

            if (selector === '[data-fff-asset-batch]' && node.hasAttribute?.('data-fff-asset-batch')) {
                pushMatch(node)
            }

            if (selector === '[data-fff-asset-consumer]' && node.dataset?.fffAssetConsumer) {
                pushMatch(node)
            }
            }

            const walk = (node) => {
                consider(node)

                for (const child of node.children ?? []) {
                    walk(child)
                }
            }

            walk(head)
            walk(body)

            return matches
        },
        getElementById(id) {
            const node = byId.get(id)
            return node?.isConnected ? node : null
        },
        addEventListener() {},
        removeEventListener() {},
    }

    const window = {
        Livewire: null,
        dispatchEvent() {},
        requestAnimationFrame(fn) { return globalThis.setTimeout(() => fn(performance.now()), 0) },
        cancelAnimationFrame(id) { globalThis.clearTimeout(id) },
        addEventListener() {},
        removeEventListener() {},
        setTimeout(fn, ms) {
            return globalThis.setTimeout(fn, ms)
        },
        clearTimeout(id) {
            return globalThis.clearTimeout(id)
        },
        location: {
            pathname: '/admin/flex-fields-playground/file-upload',
        },
        localStorage: {
            store: new Map(),
            getItem(key) {
                return this.store.get(key) ?? null
            },
            setItem(key, value) {
                this.store.set(key, String(value))
            },
            removeItem(key) {
                this.store.delete(key)
            },
        },
        performance: {
            getEntriesByName() {
                return []
            },
        },
    }

    return { document, window, nodes, head, body, byId }
}

export function registerModals(document, modalsById) {
    document.getElementById = (id) => modalsById[id] ?? null
}

export async function openModal(injector, head, modal, id) {
    modal.classList.add('fi-modal-open')
    const pending = injector.prepareModal({ detail: { id } })
    await flushAssetLoads(head)
    await pending
}

export async function closeModal(injector, modal, id) {
    modal.classList.remove('fi-modal-open')
    await injector.cleanupClosedModalPendingState({ detail: { id } })
    await new Promise(resolve => globalThis.setTimeout(resolve, 0))
}

export function headHas(head, snippet) {
    return [...stylesheetHrefs(head), ...chunkHrefs(head)].some((href) => href.includes(snippet))
}

export function headCount(head, snippet) {
    return [...stylesheetHrefs(head), ...chunkHrefs(head)].filter((href) => href.includes(snippet)).length
}
