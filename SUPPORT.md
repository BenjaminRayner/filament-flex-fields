# Support & compatibility policy

Filament Flex Fields is tested as a **premium Filament v5 plugin**. This document is the maintainer contract for upstream Filament changes.

## Supported versions

| Dependency | Supported range | CI coverage |
|------------|-----------------|-------------|
| PHP | `^8.3` | 8.3, 8.4, 8.5 |
| Filament | `^5.6` (5.6.0 or newer within 5.x) | **5.6.0**, **5.7.8**, **5.8.0** on PHP 8.3; **5.9.0** and **^5.9** on PHP 8.3–8.5 |
| Filament canary | `5.x-dev` | **Daily** schedule / on push — **non-blocking** (`continue-on-error`) |

We check the **latest Filament 5.x stable** daily through the scheduled `filament-compat` job, canary and override signature guards. Dependabot checks Composer daily and waits at least **3 days** before proposing ordinary version updates. Dependabot security updates bypass that cooldown.

Use the latest patched Filament 5.x release in applications. Historical tags are
kept only in isolated API compatibility fixtures.
Those fixtures explicitly bypass Composer security advisory admission because
upstream historical releases contain known advisories. They are never deployed;
normal installation, current dependency audits and release quality gates retain
security checks. Compatibility does not make an old upstream release secure.
Workflow declarations are acceptance requirements, not evidence of a remote run.

## How we catch Filament breaks (class of [#61](https://github.com/janczakb/filament-flex-fields/issues/61))

1. **`composer.lock` is committed** for reproducible CI. It remains `export-ignore` in `.gitattributes` so Composer dist installs for customers stay unconstrained by our lock. Lock generation uses **`config.platform.php = 8.3.33`** so deps resolve for the minimum supported PHP (avoid Symfony 8.1+ which needs PHP ≥ 8.4).
2. **`FilamentOverrideCompatibilityTest`** — reflection LSP checks on critical overrides (`schema()`, Select, RichEditor, …).
3. **`filament-compat` matrix** — Pest compatibility suites on PHP × Filament axes.
4. **`filament-dev` canary** — installs `filament/filament:5.x-dev` to surface breaks before a tag.
5. **Dependabot** groups `filament/*` (**daily**; npm/Actions stay weekly).

## Release gate (required checks)

Require **Verify source-matched core acceptance** in branch protection. It waits
for all stable PHP/Filament, browser, integration and SQL jobs, then verifies the
complete automated acceptance evidence from that workflow run. The canary stays
advisory.

The gate checks all ten required phase/engine reports, their exact checks, commit,
source fingerprint, immutable asset release and log digests. Missing, duplicated,
failed, modified or stale evidence is rejected. PHP 8.3 regression results include
the complete PHP/JavaScript suites and static analysis; PHP 8.4/8.5 retain their
full PHP suite.

Reports and executable criteria live in public tooling. Private historical audit
notes are not build inputs. Passing this gate confirms the recorded automated
scope; physical-device checks, deployment approval and the complete historical
audit remain separate requirements. It does not publish a release or deployment.

Configure under **Settings → Branches → Branch protection rules → Require status checks**.
The workflow defines the check; repository administrators must require it in the
repository settings.

## Public API and release metadata

The core acceptance job compares the reviewed extension PHP SPI and Media OS
declarations with the ABI 1 fixture, and checks the compiled public JavaScript SDK
exports. Removed or changed declarations require explicit compatibility review;
additions are reported. This conservative check does not prove runtime behavior,
every form component method, SDK option-object semantics or stored-data migrations.
The fixture is a reviewed compatibility contract; routine CI never rewrites it.

Acceptance also verifies every sealed asset against its actual bytes and generates
a CycloneDX 1.6 inventory of the exact maintainer Composer/npm lockfiles. It includes
development and optional platform packages. Customer resolutions, optional CDN
resources and application overrides are outside that inventory.

The unsigned local provenance record binds source/commit identity, lockfiles, the
SBOM and all sealed asset hashes. The release gate verifies and retains these
attachments. Successful main/tag push runs then use GitHub's keyless attestation
service to sign the accepted metadata files. Local hashes are not signatures;
the separate Sigstore bundle provides signature verification. Pull requests do
not receive attestation write permissions through a running signing job.

The signed provenance record carries the asset hashes. Compare a downloaded
asset against its recorded hash after verifying the provenance file's attestation.
This is not a claim of a particular SLSA level or automatic package publication.

## Local commands

For a repeatable browser startup profile, run `npm run audit:core:startup --
--samples=10 --verify-dynamic=true --output=build/core-reports/startup-current.json`. This renders four
real Filament panel documents in separate Testbench boots and publishes matching
assets into a disposable directory. No host application routes, assets or data
are changed. Install the selected Playwright browser first.

The report separates native resources from FLEX, decoded payload from HTTP
transfer, and fresh contexts from primed HTTP-cache contexts. It also retains
individual observations, source/release identity and explicit measurement limits.
The optional dynamic check uses the real native Livewire Action endpoint with
an isolated file session and deliberately held runtime import. It checks that
the first modal request waits and its first FLEX field becomes usable.
Add `--cycles=100` to exercise native modal cancellation and Livewire navigation
round trips. This checks mounted field/binding disposal, real component-owned
status timers and released consumer/resource claims. It does not certify every
capture stream or browser heap. Each required browser acceptance phase runs this
scenario for Chromium, Firefox or WebKit, alongside its existing browser probes.
An existing report is never overwritten. This is a local startup profile, not a
production latency claim or an interaction-performance certificate.

For installation support, `php artisan fff:assets:doctor --published --share`
exports redacted JSON with specific repair advice. It excludes raw exception
messages, local paths, file URLs and extension inventories. Ordinary `--json`
retains local error details and adds the corresponding `diagnosis`. The command
does not repair assets or test queue/storage/media health.

For Media OS defaults, `php artisan fff:media:doctor --json` inspects media policy,
original/recovery disks, encryption, staging bounds, lock-store declarations and
real scheduler registrations. Add `--images` for bundled GD checks, or `--queued`
with the application's connection, queue and job timeout. `--custom-encoder`
selects an application encoder profile without resolving its factory. Reports
separate known configuration failures from operational facts still unverified;
exit 0 is not an end-to-end deployment certificate. No storage, queue or extension
operation is invoked. See the customer Media OS diagnostics guide.

For explicitly authorized active checks, `fff:media:probe --write` exercises a
named disk/cache store or selected bundled image formats with synthetic data.
Reports include the random diagnostic run ID, redacted findings, repair advice
and failed-cleanup status. Storage cleanup targets only that run's object;
cache cleanup never flushes the store or force-releases a foreign owner.
The process-local probe does not certify workers, sharing between hosts, Spatie
conversions or application authorization/save/reopen. See the active probes
section of the Media OS diagnostics guide before choosing production targets.

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
