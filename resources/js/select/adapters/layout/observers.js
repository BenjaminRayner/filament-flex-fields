import { dropdownOptionsScroller, syncDropdownScrollbarInset } from "../../../components/select-field/headless-combobox-scroll-virt.js";

/** layout observers policy; descriptors are composed on the final field instance. */
export function createLayoutObserversMixin({}) {
  return {
    bindHeadlessMenuPositionObservers() {
      this.unbindHeadlessMenuPositionObservers();
      if (typeof ResizeObserver === 'undefined') {
        return;
      }
      const trigger = this.$refs.headlessTrigger;
      const anchor = this.resolveMenuTriggerElement();
      if (!trigger && !anchor) {
        return;
      }
      this.menuTriggerResizeObserver = new ResizeObserver(() => {
        this.scheduleMenuPositionAfterLayout();
      });
      if (anchor) {
        this.menuTriggerResizeObserver.observe(anchor);
      }
      if (trigger && trigger !== anchor) {
        this.menuTriggerResizeObserver.observe(trigger);
      }
    },
    unbindHeadlessMenuPositionObservers() {
      if (this.__fffHeadlessMenuPositionRaf) {
        cancelAnimationFrame(this.__fffHeadlessMenuPositionRaf);
        this.__fffHeadlessMenuPositionRaf = 0;
      }
      this.menuTriggerResizeObserver?.disconnect();
      this.menuTriggerResizeObserver = null;
    },
    syncDropdownOverflowChrome() {
      syncDropdownScrollbarInset(this.resolveMenuElement());
    },
    bindDropdownScrollFadeObserver() {
      this.unbindDropdownScrollFadeObserver();
      syncDropdownScrollbarInset(this.resolveMenuElement());
      const list = this.$refs.headlessOptionsList ?? dropdownOptionsScroller(this.resolveMenuElement());
      if (!list || typeof ResizeObserver === 'undefined') {
        return;
      }
      this.menuOptionsResizeObserver = new ResizeObserver(() => {
        syncDropdownScrollbarInset(this.resolveMenuElement());

        // Sheet peek ↔ expanded (and keyboard inset) change list clientHeight
        // without a scroll event — remount the TanStack window immediately.
        if (typeof this.shouldVirtualizeDropdown === 'function' && this.shouldVirtualizeDropdown()) {
          const nextHeight = list.clientHeight || 0;
          if (nextHeight > 0 && nextHeight !== this._virtualViewportHeight) {
            this._virtualViewportHeight = nextHeight;
            this._virtualWindowCache = null;
            this.virtualScrollTick = (this.virtualScrollTick ?? 0) + 1;
          }
          this.$nextTick?.(() => this.bindVirtualRowMeasurements?.());
        }
      });
      this.menuOptionsResizeObserver.observe(list);
      if (list.firstElementChild) {
        this.menuOptionsResizeObserver.observe(list.firstElementChild);
      }
    },
    unbindDropdownScrollFadeObserver() {
      this.menuOptionsResizeObserver?.disconnect();
      this.menuOptionsResizeObserver = null;
    }
  };
}
