import { createLabelRepository } from '../../async/label-repository.js'
import { optionLeaves } from '../../options/option-tree.js'

/** Selected records are pinned per field; unselected records obey the LRU budget. */
export function createUserSelectRepositoryMixin() {
    return {
        userRepository: Object.create(null),
        _userRecordStore: null,
        ensureUserRecordStore() {
            this._userRecordStore ??= createLabelRepository({ records: this.userRepository })
            this._userRecordStore.pin(this.comboboxSelectedValues)
            return this._userRecordStore
        },
        clearUserSelectRecords() {
            this._userRecordStore?.clear()
        },
        seedUserRepository() {
            for (const entry of Array.isArray(this.initialSelectedUserEntries) ? this.initialSelectedUserEntries : []) {
                this.storeUserInRepository(entry?.value, entry?.user)
            }
            this.seedUsersFromOptions(this.options)
        },
        seedUsersFromOptions(options) {
            for (const option of optionLeaves(options)) {
                if (option?.user) this.storeUserInRepository(option.value, option.user)
            }
        },
        storeUserInRepository(value, user) {
            if (this._selectDisposed || value == null || !user) return
            this.ensureUserRecordStore().put(String(value), { value, user })
        },
        resolveUserForValue(value) {
            if (this._selectDisposed) return null
            const cached = this.ensureUserRecordStore().get(String(value))
            if (cached?.user) return cached.user
            const entry = this.labelEntry?.(value) ?? this.optionRecord(value)
            if (!entry?.user) return null
            this.storeUserInRepository(value, entry.user)
            return entry.user
        },
    }
}
