---
title: "Tabs, modals & Livewire"
description: "How FLEX activates hidden controls, keeps nested dialogs ready and cleans up owned resources during Livewire updates."
icon: "refresh-cw"
---

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
| An instance closes or is removed | Its tracked listeners, timers, subscriptions and capture streams can be cleaned up |

Already evaluated JavaScript remains in the browser. Closing a dialog does not unload an imported module or remove a shared implementation that another field still uses.

For custom components, register side effects with the supported [runtime SDK](/docs/extensions/sdk). FLEX cannot discover arbitrary untracked listeners or streams created outside that lifecycle.

## Continue reading

[Asset Manager overview](/docs/asset-loading) · [Resource sharing](/docs/assets/sharing) · [Activation & cleanup](/docs/assets/lifecycle) · [Performance](/docs/assets/performance) · [Publish & diagnose](/docs/assets/deployment)
