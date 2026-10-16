/** Optional picker import and async open are owned by the requesting textarea. */
export function createTextareaEmoji(owner, scope, load = () => import('../core/shared-emoji-picker.js')) {
    let picker, pending = false, generation = 0
    const allowed = () => !scope.disposed && owner.options.editable && !owner.$refs.input.disabled && !owner.$refs.input.readOnly
    scope.defer(() => { generation++; picker?.disposeFor(owner) })
    return {
        async toggle() {
            if (!allowed()) return
            if (pending || picker?.isOpenFor(owner)) {
                generation++; pending = false; owner.emojiPickerOpen = false; picker?.disposeFor(owner); return
            }
            const token = ++generation
            const valid = () => token === generation && allowed()
            pending = true; owner.emojiPickerOpen = true
            try {
                const module = await load()
                if (!valid()) return
                picker = module.sharedEmojiPicker
                pending = false
                await picker.open({
                    context: owner, anchor: owner.$refs.emojiTrigger,
                    align: 'start', locale: owner.options.emojiPickerLocale,
                    stylesHref: owner.options.emojiShadowStylesUrl, inputStylesHref: owner.options.emojiInputStylesUrl,
                    retryLabel: owner.options.messages.retry,
                    onSelect: text => { if (valid()) owner.insertToolText(text) },
                    onClose: () => { if (token === generation) owner.emojiPickerOpen = false },
                    onError: () => { if (valid()) owner.showStatus(owner.options.messages.emoji_error) },
                })
            } catch {
                if (valid()) { owner.emojiPickerOpen = false; owner.showStatus(owner.options.messages.emoji_error) }
            } finally { if (token === generation) pending = false }
        },
    }
}
