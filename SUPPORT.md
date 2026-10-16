# Support & compatibility policy

Filament Flex Fields is tested as a **premium Filament v5 plugin**. This document is the maintainer contract for upstream Filament changes.

## Supported versions

| Dependency | Supported range | CI coverage |
|------------|-----------------|-------------|
| PHP | `^8.3` | 8.3, 8.4, 8.5 |
| Filament | Composer permits `^5.0`; verified support starts at **5.9.0** | **5.9.0** (exact stable floor) and **^5.9** (latest admitted 5.x stable) |
| Filament canary | `5.x-dev` | **Daily** schedule / on push — **non-blocking** (`continue-on-error`) |

We check the **latest Filament 5.x stable** daily through the scheduled `filament-compat` job, canary and override signature guards. Dependabot checks Composer daily and waits at least **3 days** before proposing ordinary version updates. Dependabot security updates bypass that cooldown.

The older 5.7.8 fixture was rejected by Composer's security advisory admission.
Security checks remain enabled. The Composer constraint is retained for backwards
resolution compatibility; versions below the verified floor are not certified.
Workflow declarations are acceptance requirements, not evidence of a remote run.

## How we catch Filament breaks (class of [#61](https://github.com/janczakb/filament-flex-fields/issues/61))

1. **`composer.lock` is committed** for reproducible CI. It remains `export-ignore` in `.gitattributes` so Composer dist installs for customers stay unconstrained by our lock. Lock generation uses **`config.platform.php = 8.3.33`** so deps resolve for the minimum supported PHP (avoid Symfony 8.1+ which needs PHP ≥ 8.4).
2. **`FilamentOverrideCompatibilityTest`** — reflection LSP checks on critical overrides (`schema()`, Select, RichEditor, …).
3. **`filament-compat` matrix** — Pest compatibility suites on PHP × Filament axes.
4. **`filament-dev` canary** — installs `filament/filament:5.x-dev` to surface breaks before a tag.
5. **Dependabot** groups `filament/*` (**daily**; npm/Actions stay weekly).

## Release gate (required checks)

On `main` / release tags, require at least:

- `quality (PHP 8.3)`
- `Filament latest-5.x · PHP 8.3` and the exact `Filament 5.9.0 · PHP 8.3` floor

Optional but recommended: all `quality` PHP axes and both Filament stable matrix legs.

Configure under **Settings → Branches → Branch protection rules → Require status checks**.

## Local commands

```bash
composer install
composer test
composer test:filament-compat
# Optional one-shot Select stress (~8k Livewire cases) — NOT part of CI:
FFF_SELECT_ATOMICS_FULL=1 composer test:select-atomics
```

To simulate CI Filament switch:

```bash
composer require "filament/filament:^5.9" --no-update
composer update "filament/*" --with-all-dependencies
composer test:filament-compat
```

## Frontend security maintenance

Maintainer builds use Node 24 and the committed npm lockfile. `npm audit` is a
blocking check in the PHP 8.3 quality job, including development dependencies.
Tailwind CLI 4.3.3 pins Parcel watcher 2.5.1; a targeted override selects 2.6.0
to remove its vulnerable micromatch/braces chain. Keep the override until the
upstream CLI adopts a fixed watcher, and verify real watch-mode rebuilds when
changing it. The compiler version and its generated styling stay unchanged.

Optional AudioField transcription uses pinned @huggingface/transformers 4.3.1
and its matching ONNX runtime. The installer verifies all three browser files
with SHA-256 under a version-specific public directory. Run
`php artisan fff:whisper:install` after upgrading an application that enables
transcription; the normal asset publisher does not install the optional runtime.
