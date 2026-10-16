import { createResourceScope } from '../runtime/lifecycle/resource-scope.js'
import { createSegmentRuntime } from '../components/segments/runtime.js'
import { mountTextInputPresentation } from '../components/text-input/presentation.js'
import { buildPermalink, fromEditableSlug, needsServerTransliteration, normalizeSlug, slugify, toEditableSlug } from './value.js'
import { createPendingEdit } from './pending-edit.js'
import { callSlugMethod, readSlugState, watchSlugState, writeSlugState } from './wire.js'

/** One editor transaction per rendered consumer. Async results are revision-bound. */
export function createSlugRuntime(options = {}) {
    const effects = createResourceScope()
    let initialized = false
    const editorIdentity = Symbol('slug-editor')
    let pendingEdit = { set() {} }
    let generation = 0
    let transaction = 0
    let sourceTimer = null
    let feedbackTimer = null
    const invalidateGeneration = () => { generation++; clearTimeout(sourceTimer); sourceTimer = null }
    const normalize = value => normalizeSlug(value, options.separator ?? '-', options)
    const editable = value => toEditableSlug(value, options)
    const initial = normalize(options.initialSlug ?? options.state ?? '')

    return {
        ...options,
        slug: initial,
        draftSlug: editable(initial),
        mode: 'view',
        autoSyncDisabled: Boolean(options.initialAutoSyncDisabled),
        sourceValue: null,
        isFocused: false,
        isGenerating: false,
        isConfirming: false,
        uniqueError: null,
        copyFeedback: false,
        resolvedUrl: null,
        resolvedUrlSlug: null,
        get busy() { return this.isGenerating || this.isConfirming },
        get editableNow() { return this.canEdit() && (!this.inlineEditing || this.mode === 'edit') },

        init() {
            if (initialized || effects.disposed) return
            initialized = true
            pendingEdit = createPendingEdit(this, editorIdentity)
            this.sourceValue = readSlugState(this, this.sourcePath)
            mountTextInputPresentation(this.$el, this.$refs.slugInput, { size: options.size ?? 'md', focusOutline: options.focusOutline !== false })
            watchSlugState(this, effects, this.statePath, value => {
                const next = normalize(value)
                if (next === this.slug) return
                transaction++; invalidateGeneration(); this.isGenerating = this.isConfirming = false
                this.slug = next
                this.resolvedUrl = this.resolvedUrlSlug = null
                if (this.mode !== 'edit') this.draftSlug = editable(next)
            })
            watchSlugState(this, effects, this.sourcePath, value => {
                if (value === this.sourceValue) return
                this.sourceValue = value
                this.scheduleGeneration()
            })
            watchSlugState(this, effects, this.autoUpdateDisabledPath, value => {
                if (Boolean(value) === this.autoSyncDisabled) return
                this.autoSyncDisabled = Boolean(value)
                if (this.autoSyncDisabled) { invalidateGeneration(); this.isGenerating = false }
            })
            effects.defer(() => {
                invalidateGeneration(); transaction++
                pendingEdit.set(false)
                clearTimeout(feedbackTimer)
            })
        },
        destroy() { effects.dispose() },
        canEdit() { return !this.disabled && !this.slugReadOnly },
        actionGroup() {
            const owner = this
            return createSegmentRuntime({ property: 'command', commands: true, onCommand(value) { owner.runAction(value) } })
        },
        runAction(action) {
            if (action === 'cancel') return this.cancelEditing()
            if (this.busy || this.disabled) return
            const actions = { edit: 'startEditing', confirm: 'confirmEditing', reset: 'resetSlug', regenerate: 'resetSlug', copy: 'copyUrl', visit: 'openVisitUrl' }
            if (actions[action]) return this[actions[action]]()
        },
        startEditing() {
            if (!this.canEdit() || !this.inlineEditing || this.mode === 'edit') return
            invalidateGeneration(); transaction++; this.isGenerating = false
            this.draftSlug = editable(this.slug); this.mode = 'edit'; this.uniqueError = null
            pendingEdit.set(true)
            this.$nextTick(() => {
                if (effects.disposed || this.mode !== 'edit') return
                const input = this.$refs.slugInput
                input.focus({ preventScroll: true })
                input.setSelectionRange(input.value.length, input.value.length)
            })
        },
        cancelEditing() {
            if (this.mode !== 'edit') return
            transaction++; this.isConfirming = false; this.mode = 'view'; this.uniqueError = null
            this.draftSlug = editable(this.slug)
            pendingEdit.set(false)
        },
        onInput(event) {
            if (!this.editableNow || event.isComposing) return
            transaction++; this.isConfirming = false; this.uniqueError = null
            this.draftSlug = event.target.value
            if (!this.inlineEditing) {
                invalidateGeneration(); this.isGenerating = false
                this.commit(fromEditableSlug(this.draftSlug, this.separator, options), true, { preserveDraft: true })
            }
        },
        inputValue() { return this.editableNow ? this.draftSlug : editable(this.slug) },
        onBlur() { this.isFocused = false; if (!this.inlineEditing) this.draftSlug = editable(this.slug) },
        commit(value, customized, { preserveDraft = false, url = null } = {}) {
            this.slug = normalize(value)
            if (!preserveDraft) this.draftSlug = editable(this.slug)
            this.autoSyncDisabled = customized
            this.resolvedUrl = url; this.resolvedUrlSlug = url === null ? null : this.slug
            writeSlugState(this, this.autoUpdateDisabledPath, customized)
            writeSlugState(this, this.statePath, this.slug || null)
            this.$dispatch?.('fff-slug-committed', { value: this.slug, customized })
        },
        async confirmEditing() {
            if (!this.canEdit() || this.mode !== 'edit' || this.busy) return
            const revision = ++transaction
            let value = fromEditableSlug(this.draftSlug, this.separator, options)
            let url = null
            if (this.maxLength > 0 && value.length > this.maxLength) { this.uniqueError = this.labels.error; return }
            this.isConfirming = true; this.uniqueError = null
            try {
                if (this.validateOnConfirm) {
                    const result = await callSlugMethod(this, 'validateSlugDraft', { value })
                    if (effects.disposed || revision !== transaction || this.mode !== 'edit') return
                    if (result?.valid !== true || typeof result.slug !== 'string') {
                        this.uniqueError = result?.message || this.labels.error
                        return
                    }
                    value = result.slug; url = typeof result.url === 'string' ? result.url : null
                }
                if (effects.disposed || revision !== transaction || this.mode !== 'edit') return
                this.commit(value, true, { url }); this.mode = 'view'
                pendingEdit.set(false)
            } catch {
                if (!effects.disposed && revision === transaction) this.uniqueError = this.labels.error
            } finally {
                if (!effects.disposed && revision === transaction) this.isConfirming = false
            }
        },
        scheduleGeneration() {
            invalidateGeneration(); this.isGenerating = false
            if (!this.canEdit() || !this.autoGenerate || !this.sourceLive || this.autoSyncDisabled || this.mode === 'edit') return
            sourceTimer = setTimeout(() => { sourceTimer = null; this.generate(this.sourceValue) }, Math.max(0, Number(this.debounceMs) || 0))
        },
        async generate(source, { reset = false } = {}) {
            if (!this.canEdit() || effects.disposed) return
            const revision = ++generation
            const value = source == null ? '' : String(source)
            this.isGenerating = true; this.uniqueError = null
            try {
                const generated = this.serverGenerate || needsServerTransliteration(value)
                    ? await callSlugMethod(this, 'generateSlugPreview', { source: value, force: reset }) : slugify(value, this.separator, this.maxLength)
                if (effects.disposed || revision !== generation) return
                if (typeof generated !== 'string') throw new Error('Invalid slug preview response.')
                if (!reset && (this.autoSyncDisabled || this.mode === 'edit')) return
                this.commit(generated, false)
                if (reset) {
                    transaction++; this.mode = 'view'
                    pendingEdit.set(false)
                }
            } catch {
                // A model/custom generator must never silently fall back to another algorithm.
                if (!effects.disposed && revision === generation) this.uniqueError = this.labels.error
            } finally {
                if (!effects.disposed && revision === generation) this.isGenerating = false
            }
        },
        resetSlug() {
            if (!this.canEdit() || this.busy || !this.sourcePath) return
            transaction++; invalidateGeneration()
            return this.generate(readSlugState(this, this.sourcePath, this.sourceValue), { reset: true })
        },
        canRegenerate() { return this.canEdit() && this.showRegenerateButton && this.sourcePath && this.mode === 'view' && this.autoSyncDisabled && this.sourceValue != null },
        hasSecondaryActions() { return this.canRegenerate() || this.mode === 'view' && this.hasPermalink() && (this.showCopyButton || this.canVisitLink) },
        hasPermalink() { return Boolean(this.slug && (this.customVisitUrl || buildPermalink(this.slug, options))) },
        fullUrl() { return this.resolvedUrlSlug === this.slug ? this.resolvedUrl : buildPermalink(this.slug, options) },
        async permalink() {
            const slug = this.slug
            if (!this.hasPermalink()) return null
            if (!this.customVisitUrl || this.resolvedUrlSlug === slug) return this.fullUrl()
            const url = await callSlugMethod(this, 'resolveSlugPermalink', { slug })
            if (effects.disposed || this.slug !== slug) return null
            if (typeof url !== 'string' && url !== null) throw new Error('Invalid permalink response.')
            this.resolvedUrl = url; this.resolvedUrlSlug = slug
            return url
        },
        async copyUrl() {
            if (this.disabled || !this.showCopyButton) return
            try {
                const url = await this.permalink()
                if (!url || effects.disposed) return
                await navigator.clipboard.writeText(url)
                if (effects.disposed) return
                this.copyFeedback = true; clearTimeout(feedbackTimer)
                feedbackTimer = setTimeout(() => { if (!effects.disposed) this.copyFeedback = false }, 1600)
            } catch { if (!effects.disposed) this.uniqueError = this.labels.copyError }
        },
        async openVisitUrl() {
            if (this.disabled || !this.canVisitLink) return
            // Reserve during the user gesture so an async custom route isn't popup-blocked.
            const popup = window.open('about:blank', '_blank')
            if (popup) popup.opener = null
            try {
                const url = await this.permalink()
                if (!url || effects.disposed || !popup) { popup?.close(); return }
                const resolved = new URL(url, window.location.href)
                if (!['http:', 'https:'].includes(resolved.protocol)) throw new Error('Invalid permalink protocol.')
                popup.location.replace(resolved.href)
            } catch { popup?.close(); if (!effects.disposed) this.uniqueError = this.labels.error }
        },
        badgeLabel() { return this.autoSyncDisabled ? this.labels.custom : this.labels.auto },
        isChangedFromRecord() { return Boolean(this.recordSlug && this.slug !== this.recordSlug) },
    }
}
