function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}


/** Trusted templates are separate from escaped user names/emails/fallback identities. */
export function createUserSelectPresentationMixin() {
    return {
        renderUserAvatarHtml(user, layout = 'list') {
            if (! user) {
                return ''
            }

            const sizeClass = {
                trigger: 'fff-user-select__avatar--trigger',
                tag: 'fff-user-select__avatar--tag',
                list: 'fff-user-select__avatar--list',
            }[layout] ?? 'fff-user-select__avatar--list'

            const avatarUrl = user.avatarUrl ?? user.image ?? null
            const initials = user.initials ?? ''
            const verified = Boolean(user.verified)

            let html = `<span class="fff-user-select__avatar ${sizeClass}" aria-hidden="true">`
            html += '<span class="fff-user-select__avatar-surface">'

            if (avatarUrl) {
                html += `<img src="${escapeHtml(avatarUrl)}" alt="" class="fff-user-select__avatar-image" width="32" height="32" loading="lazy" decoding="async" />`
            } else {
                html += `<span class="fff-user-select__avatar-initials">${escapeHtml(initials)}</span>`
            }

            html += '</span>'

            if (verified && this.verifiedIconHtml) {
                html += `<span class="fff-user-select__verified-badge" title="Verified" aria-hidden="true">${this.verifiedIconHtml}</span>`
            }

            html += '</span>'

            return html
        },

        renderUserOptionHtml(user, layout = 'list') {
            if (! user) {
                return ''
            }

            const name = user.name ?? ''
            const email = user.email ?? user.description ?? null
            const layoutClass = {
                trigger: 'fff-user-select-option--trigger',
                tag: 'fff-user-select-option--tag',
                list: 'fff-user-select-option--list',
            }[layout] ?? 'fff-user-select-option--list'

            let html = `<span class="fff-user-select-option ${layoutClass}">`
            html += this.renderUserAvatarHtml(user, layout)

            if (layout === 'tag') {
                html += `<span class="fff-user-select-option__name">${escapeHtml(name)}</span>`
            } else {
                html += '<span class="fff-user-select-option__content">'
                html += `<span class="fff-user-select-option__name">${escapeHtml(name)}</span>`

                if (email && layout !== 'tag') {
                    html += `<span class="fff-user-select-option__email">${escapeHtml(email)}</span>`
                }

                html += '</span>'
            }

            html += '</span>'

            return html
        },

        userOptionLabelHtml(value, context = 'dropdown') {
            const user = this.resolveUserForValue(value)

            if (! user) {
                return escapeHtml(value)
            }

            return this.renderUserOptionHtml(user, context === 'trigger' ? 'trigger' : 'list')
        },

        selectedUserEntries() {
            return this.comboboxSelectedValues
                .map((value) => ({
                    value,
                    user: this.resolveUserForValue(value),
                }))
                .filter((entry) => entry.user)
        },

        shouldShowUserSelectTags() {
            return this.isUserSelectField
                && this.multiple
                && this.selectedUserEntries().length >= 2
        },

        userSelectTriggerHtml() {
            void this.triggerLabelEpoch

            const entries = this.selectedUserEntries()

            if (entries.length === 0) {
                if (! Array.isArray(this.comboboxSelectedValues) || this.comboboxSelectedValues.length === 0) {
                    return this.placeholder
                }

                // Selection exists but the user repo has not resolved yet — prefer
                // stored option/label HTML over flashing the empty placeholder.
                if (this.comboboxSelectedValues.length === 1) {
                    const value = this.comboboxSelectedValues[0]
                    const option = this.optionRecord?.(value) ?? this.labelEntry?.(value)

                    if (option?.user) {
                        this.storeUserInRepository(value, option.user)

                        return this.renderUserOptionHtml(option.user, 'trigger')
                    }

                    if (option?.label) {
                        return option.label
                    }
                }

                return this.placeholder
            }

            if (entries.length === 1) {
                return this.renderUserOptionHtml(entries[0].user, 'trigger')
            }

            const names = entries
                .map((entry) => escapeHtml(entry.user?.name ?? entry.value))
                .join(', ')

            return `<span class="fff-user-select__trigger-names">${names}</span>`
        },

    }
}
