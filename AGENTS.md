# FLEX Fields — instructions for AI coding agents

These rules apply to this package. Read applicable parent instructions as well.

`docs/` is reserved for official customer documentation published by Mintlify.
Keep architecture contracts, AI instructions, audits, implementation plans,
verification reports and generated engineering inventories in `.internal/`.
Do not create internal notes in `docs/` or link customer pages to internal reports.

## Required reading

Before changing component schemas, asset publication/loading, CSS ownership, Alpine
initialization, Livewire navigation, tables, tabs, wizards or modal behavior, read
[.internal/asset-runtime-ai-guide.md](.internal/asset-runtime-ai-guide.md) in full. It is the
maintained architecture contract, not a claim that every possible integration has
been tested. Update it in the same change when the contract changes.

For reproduction and current verification limits, read
[.internal/asset-lifecycle-audit.md](.internal/asset-lifecycle-audit.md).

## Rules that must survive future edits

- Every component declares a typed `ComponentDefinition`. Shared CSS has one
  owner; reuse it with `stylesFrom`. Use `uses` for full component composition.
  Optional capabilities belong to individual rendered instances.
- Emit an independent asset consumer boundary for every field/display/table cell.
  Never replace per-instance ownership with component-name or page-global flags.
- Load inactive tabs, wizard steps, collapsed sections and closed modals only upon
  activation. Scrolling below the fold does **not** deactivate a field.
- Keep suspended parent modals in the owner stack. Closing a child must not remove
  dependencies still used by its parents or the underlying page.
- Reconnect DOM observers after Livewire replaces `document.body`. Dispose event
  listeners, activation waits, timers, streams and subscriptions with their owner.
- Reveal a modal only after CSS/modules and active factory `init()` have completed.
  Never add an arbitrary timeout that reveals unready HTML. Preserve retry UI.
- Removing preload links does not unload evaluated JavaScript. Do not promise JS
  unloading or try to delete shared Alpine factories while other instances exist.
- Scope CSS to explicit FLEX classes. Do not style bare `nav`, `header`, `main`,
  buttons or headings inside a broad parent that also contains native Filament
  components. Do not override native Filament tab/modal/table layouts incidentally.
- Keep catalog/demo CSS, JS and PHP examples out of unrelated application pages.
  Generate page links through Filament. The canonical catalog route segment is
  `FlexFieldsPlaygroundCluster::SLUG` (`flex-fields`), not the internal asset/view ID.
- Build source files, regenerate version manifests, and publish assets after
  changes. Never hand-edit generated dist files or use cache-busting timestamps.
- Preserve unrelated work in this shared checkout. The current task prohibits
  changes to `tests/**`; use existing compatibility probes and live diagnostic
  pages, and report any blocked/stale suite honestly. Do not change test files
  unless the user explicitly authorizes that change.
