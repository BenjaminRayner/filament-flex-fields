/** Keyed nodes survive label refresh and repeated Alpine watchers during pointer input. */
export function createUserSelectChipsMixin() {
    const nodes = new Map()
    let renderedContainer = null
    let releaseClick = null
    const createTag = (document, key) => {
        const tag = document.createElement('span')
        tag.className = 'fff-user-select__selected-tag fff-tags-field__tag'
        tag.dataset.value = key
        const content = document.createElement('span')
        content.className = 'fff-user-select__selected-tag-content'
        const remove = document.createElement('button')
        remove.type = 'button'
        remove.className = 'fff-tags-field__tag-remove fff-user-select__selected-tag-remove'
        remove.tabIndex = 0
        tag.append(content, remove)
        return { tag, content, remove, html: null, icon: null }
    }
    return {
        _userTagContainer: null,
        _userTagClickListener: null,
        resolveUserSelectTagsContainer() {
            return (this._pickerHostElement ?? this.$el)?.closest?.('.fff-select-field-wrapper')
                ?.querySelector('[data-fff-user-select-tags]') ?? null
        },
        bindUserSelectTagRemoveHandlers() {
            const container = this.resolveUserSelectTagsContainer()
            if (!container || this._selectDisposed || container === this._userTagContainer) return
            this.releaseUserSelectTagListener()
            this._userTagContainer = container
            this._userTagClickListener = (event) => {
                const button = event.target?.closest?.('.fff-user-select__selected-tag-remove')
                if (!button || !container.contains(button) || this.disabled || this._selectDisposed) return
                const value = button.closest('.fff-user-select__selected-tag[data-value]')?.dataset.value
                if (value === undefined) return
                event.preventDefault()
                event.stopPropagation()
                const focused = container.ownerDocument.activeElement === button
                this.removeUserSelectTag(value)
                if (focused) this.$nextTick(() => {
                    const doc = container.ownerDocument
                    if (!this._selectDisposed && !this.disabled && !button.isConnected
                        && (!doc.activeElement || doc.activeElement === doc.body)) this.$refs?.headlessTrigger?.focus({ preventScroll: true })
                })
            }
            releaseClick = this.ensurePickerResourceScope().listen(container, 'click', this._userTagClickListener)
        },
        releaseUserSelectTagListener() {
            releaseClick?.()
            releaseClick = null
            this._userTagContainer = null
            this._userTagClickListener = null
        },
        clearUserSelectChipProjection() { nodes.clear(); renderedContainer = null },
        syncUserSelectTags() {
            if (this._selectDisposed || !this.isUserSelectField || !this.multiple) return
            const container = this.resolveUserSelectTagsContainer()
            if (!container) return
            this.bindUserSelectTagRemoveHandlers()
            if (renderedContainer !== container) {
                nodes.clear()
                renderedContainer = container
                container.replaceChildren()
            }
            const entries = this.selectedUserEntries()
            const visible = entries.length >= 2
            container.hidden = !visible
            const keys = new Set(visible ? entries.map((entry) => String(entry.value)) : [])
            for (const [key, node] of nodes) {
                if (!keys.has(key)) { node.tag.remove(); nodes.delete(key) }
            }
            if (!visible) return
            let position = 0
            for (const entry of entries) {
                const key = String(entry.value)
                let node = nodes.get(key)
                if (!node) { node = createTag(container.ownerDocument, key); nodes.set(key, node) }
                const html = this.renderUserOptionHtml(entry.user, 'tag')
                if (node.html !== html) { node.content.innerHTML = html; node.html = html }
                if (node.icon !== this.tagRemoveIconHtml) { node.remove.innerHTML = this.tagRemoveIconHtml; node.icon = this.tagRemoveIconHtml }
                node.remove.disabled = Boolean(this.disabled)
                node.remove.setAttribute('aria-label', `Remove ${entry.user?.name ?? entry.value}`)
                const existing = container.children[position++]
                if (existing !== node.tag) container.insertBefore(node.tag, existing ?? null)
            }
        },
    }
}
