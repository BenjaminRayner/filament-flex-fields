---
title: "Tabs, modals & Livewire"
description: "How FLEX activates hidden controls, keeps nested dialogs ready and cleans up owned resources during Livewire updates."
icon: "refresh-cw"
"canonical": "https://flex-fields.bjanczak.com/docs/assets/lifecycle"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/asset-manager-lifecycle.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "A real FLEX field remains usable after another resource owner is removed"
"twitter:card": "summary_large_image"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/asset-manager-lifecycle.webp"
---

<span id="recording-steps"></span>

## Watch the lifecycle examples

The [narrated Asset Manager walkthrough](/docs/asset-loading#watch-ownership-in-action)
shows the complete system. Jump to the relevant example on YouTube:

- [01:01 — CSS, module and initialization readiness](https://www.youtube.com/watch?v=KufHFWijeCc&t=61s).
- [01:39 — Close during module loading](https://www.youtube.com/watch?v=KufHFWijeCc&t=99s).
- [02:02 — Dispose registered effects](https://www.youtube.com/watch?v=KufHFWijeCc&t=122s).
- [02:29 — Ignore outdated initialization](https://www.youtube.com/watch?v=KufHFWijeCc&t=149s).
- [02:57 — Retain resources for nested parents](https://www.youtube.com/watch?v=KufHFWijeCc&t=177s).
- [03:26 — Recover from a controlled loading failure](https://www.youtube.com/watch?v=KufHFWijeCc&t=206s).
- [03:51 — Livewire navigation and new consumers](https://www.youtube.com/watch?v=KufHFWijeCc&t=231s).

## Hidden areas activate when used

| Where the field is rendered | Activation behavior |
| --- | --- |
| Active page or form section | Participates in the active workflow |
| Inactive tab | Activates with the tab |
| Future wizard step | Activates with the step |
| Collapsed section | Activates when expanded |
| Closed dialog | Activates with the dialog |
| Active field below the viewport | Remains active; scrolling does not deactivate it |

For example, a Select in the first tab can initialize independently of a camera scanner in a second tab. Opening that second tab activates the scanner's resources and initialization.

## Keep changing forms and dialogs ready

A prepared control needs its CSS, JavaScript and initialization to complete. Dialogs can wait for active controls and retain retry behavior after failed loading, instead of exposing unready content after an arbitrary delay.

| Situation | What the lifecycle preserves |
| --- | --- |
| A child dialog opens above a parent | The parent and underlying page retain their required resources |
| One of several fields is removed | Other fields keep their interest in shared resources |
| An earlier opening finishes after a replacement | The old completion cannot mark the new instance ready |
| Livewire replaces page content | Newly rendered controls acquire resources and removed owners are disposed |
| A surface closes or deactivates | Pending surface work and resource interests are released; closing does not necessarily destroy a mounted factory |
| A component is actually unmounted | Its registered listeners, timers, subscriptions and capture streams are disposed |

Already evaluated JavaScript remains in the browser. Closing a dialog does not unload an imported module or remove a shared implementation that another field still uses.

For custom components, register side effects with the supported [runtime SDK](/docs/extensions/sdk). FLEX cannot discover arbitrary untracked listeners or streams created outside that lifecycle.

## Continue reading

[Asset Manager overview](/docs/asset-loading) · [Resource sharing](/docs/assets/sharing) · [Activation & cleanup](/docs/assets/lifecycle) · [Performance](/docs/assets/performance) · [Publish & diagnose](/docs/assets/deployment)
