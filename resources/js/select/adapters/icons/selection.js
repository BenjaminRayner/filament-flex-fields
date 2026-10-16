/** icons selection policy; descriptors are composed on the final field instance. */
export function createIconsSelectionMixin({}) {
  return {
    selectIconFromKeyboard(icon) {
      if (typeof icon === 'string') {
        this.selectIcon(icon);
        return;
      }
      if (icon?.name) {
        this.selectIcon(icon.name);
      }
    },
    selectIcon(icon) {
      if (this.readOnly || this.__fffPickerDisposed) {
        return;
      }
      if (typeof icon !== 'string' || !this.loadedIconItems.some(item => item.name === icon)) return;
      this.state = icon;
      this.selectedHtml = this.svgCache[icon] ?? '';
      if (this.closeOnSelect) {
        this.closePanel();
      }
    },
    clearSelection() {
      if (this.readOnly || this.__fffPickerDisposed) {
        return;
      }
      this.state = null;
      this.selectedHtml = '';
    }
  };
}
