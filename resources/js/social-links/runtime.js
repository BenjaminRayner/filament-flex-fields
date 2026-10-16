import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { waitForAssetActivation } from '../runtime/lifecycle/asset-activation.js'
import { watchOwnedAlpineValue } from '../runtime/alpine/owned-watch.js'
import { createSocialPresentation } from './presentation.js'
import { availablePlatforms, collectSocialLinkRowErrors, formatSocialLinkUrl, normalizeSocialLinksState } from './validation.js'

export function createSocialLinksRuntime(binding, dependencies = {}) {
    let root, scope, config, definitionMap, presentation, initialization, disposed = false
    const signature = links => JSON.stringify(links)
    const allowed = vm => !disposed && !vm.disabled && !vm.readOnly
    const errors = vm => vm.showValidationErrors ? collectSocialLinkRowErrors(vm.links, definitionMap, (code, platform) => vm.validationMessage(code, platform)) : {}
    const paint = vm => presentation?.render(vm.links, errors(vm), !allowed(vm), vm.canAddMore)
    const publish = vm => {
        const next = vm.links.map(link => ({ platform: link.platform, url: link.url }))
        if (signature(vm.state) !== signature(next)) vm.state = next
        if (!vm.platformChoices.some(p => p.value === vm.selectedPlatform)) vm.selectedPlatform = vm.platformChoices[0]?.value ?? ''
        paint(vm)
    }
    return {
        state: binding.state, links: [], platforms: [], disabled: false, readOnly: false, maxLinks: null, selectedPlatform: '', showValidationErrors: false,
        init() {
            if (initialization || disposed) return initialization
            root = this.$el
            config = { ...JSON.parse(root.dataset.socialConfig ?? '{}'), ...binding }
            definitionMap = new Map((config.platforms ?? []).map(platform => [platform.value, platform]))
            Object.assign(this, { platforms: config.platforms ?? [], disabled: !!config.disabled, readOnly: !!config.readOnly,
                maxLinks: config.maxLinks ?? null, showValidationErrors: !!config.showValidationErrors })
            this.links = normalizeSocialLinksState(this.state)
            this.selectedPlatform = this.platformChoices[0]?.value ?? ''
            scope = createResourceScope()
            initialization = (async () => {
                if (!await (dependencies.activate ?? waitForAssetActivation)(root, { signal: scope.signal }) || disposed) return
                presentation = (dependencies.presentation ?? createSocialPresentation)(root, config)
                scope.defer(() => presentation.destroy())
                paint(this)
                const alpine = dependencies.alpine ?? globalThis.Alpine
                watchOwnedAlpineValue(scope, () => this.state, value => {
                    const next = normalizeSocialLinksState(value)
                    if (signature(next) === signature(this.links)) return
                    this.links = next
                    if (!this.platformChoices.some(p => p.value === this.selectedPlatform)) this.selectedPlatform = this.platformChoices[0]?.value ?? ''
                    paint(this)
                }, alpine)
                watchOwnedAlpineValue(scope, () => [this.disabled, this.readOnly], () => paint(this), alpine)
                scope.listen(root, 'input', event => {
                    if (!event.target.matches('[data-social-url]') || !allowed(this)) return
                    const platform = event.target.closest('[data-social-row]')?.dataset.platform
                    this.links = this.links.map(link => link.platform === platform ? { ...link, url: event.target.value } : link)
                    publish(this)
                })
                scope.listen(root, 'focusout', event => {
                    if (!event.target.matches('[data-social-url]') || !allowed(this)) return
                    const platform = event.target.closest('[data-social-row]')?.dataset.platform
                    this.links = this.links.map(link => link.platform === platform ? { ...link, url: config.autoFormatUrls ? formatSocialLinkUrl(link.url) : link.url.trim() } : link)
                    this.showValidationErrors = true
                    publish(this)
                })
                scope.listen(root, 'click', event => {
                    const button = event.target.closest('button')
                    if (!button || !root.contains(button) || button.disabled || !allowed(this)) return
                    if (button.hasAttribute('data-social-add')) { this.confirmAddPlatform(); return }
                    const action = button.dataset.socialAction
                    if (!action) return
                    const platform = button.closest('[data-social-row]')?.dataset.platform
                    const index = this.links.findIndex(link => link.platform === platform)
                    if (index < 0) return
                    if (action === 'remove') this.links = this.links.filter((_, i) => i !== index)
                    else if (config.reorderable) {
                        const to = index + (action === 'up' ? -1 : 1)
                        if (to < 0 || to >= this.links.length) return
                        const next = this.links.slice(); [next[index], next[to]] = [next[to], next[index]]; this.links = next
                    }
                    publish(this)
                })
                const form = root.closest('form')
                if (form) scope.listen(form, 'submit', event => {
                    if (!allowed(this)) return
                    this.showValidationErrors = true; paint(this)
                    const failures = errors(this)
                    const first = Object.keys(failures)[0]
                    if (first === undefined) return
                    event.preventDefault(); event.stopImmediatePropagation()
                    presentation.focus(this.links[Number(first)].platform)
                }, true)
                root.setAttribute('data-social-ready', '')
            })().catch(error => { scope.dispose(); initialization = null; throw error })
            return initialization
        },
        get platformChoices() { return availablePlatforms(this.platforms, this.links, this.maxLinks).map(p => ({ value: p.value, label: p.label })) },
        get canAddMore() { return allowed(this) && this.platformChoices.length > 0 },
        selectPlatform(value) { if (allowed(this) && this.platformChoices.some(p => p.value === value)) this.selectedPlatform = value },
        confirmAddPlatform() {
            if (!this.canAddMore || !this.platformChoices.some(p => p.value === this.selectedPlatform)) return
            const platform = this.selectedPlatform
            this.links = [...this.links, { platform, url: '' }]
            publish(this)
            presentation.focus(platform)
        },
        validationMessage(code, platform) {
            const labels = config.labels, label = this.platforms.find(p => p.value === platform)?.label ?? platform
            return ({ required: labels.required, unknown_platform: labels.unknownPlatform, invalid_url: labels.invalidUrl,
                platform_not_allowed: labels.platformNotAllowed, platform_mismatch: labels.platformMismatch?.replace(':platform', label) })[code] ?? labels.invalidUrl
        },
        destroy() { if (disposed) return; disposed = true; scope?.dispose(); root = config = definitionMap = presentation = null },
    }
}
