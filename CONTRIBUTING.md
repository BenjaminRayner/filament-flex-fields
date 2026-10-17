# Contributing to FLEX Fields

Thank you for helping improve FLEX Fields. Useful contributions include
reproducible bug reports, focused fixes, translations, documentation and
accessibility or performance improvements. Participation follows our
[code of conduct](CODE_OF_CONDUCT.md).

## Before you start

Search existing issues and pull requests first. For a new component, a public API
change or a substantial refactor, open an issue describing the problem and proposed
approach before investing in an implementation.

FLEX Fields v4 is available on Packagist. Check the installed version and branch
you are working with; the development branch may contain changes not yet released.
Include the package version or commit when discussing behavior. See [Support](SUPPORT.md) for the
compatibility policy.

FLEX Fields is source-available under its [own license](LICENSE), rather than an
unrestricted open-source license. Contributions are governed by **Section 4** of
that license: you retain ownership of your original contribution and grant the
rights described there. Submit only work you have the necessary rights to share,
and disclose any third-party terms. You can submit a patch privately or use a
GitHub-native contribution fork under GitHub's platform permissions. Keep existing
notices. A contribution fork is not a commercial license or permission for
independent redistribution outside those permissions.

## Report a bug or suggest an improvement

Use the [issue templates](https://github.com/janczakb/filament-flex-fields/issues/new/choose).
For bugs, include:

- The package, PHP, Laravel and Filament versions, plus the browser where relevant.
- Minimal reproduction steps and the smallest relevant field configuration.
- Expected and actual behavior, with screenshots or logs where helpful.
- For rendering or lifecycle problems, whether the issue occurs in a modal, tab,
  wizard, table, after navigation, or in RTL or dark mode.

Remove credentials, personal data and private application code from reproductions.
Report suspected vulnerabilities privately through [SECURITY.md](SECURITY.md),
rather than posting an exploit in a public issue or pull request.

For feature requests, describe the user problem, an example configuration and any
alternatives you considered.

## Set up a development checkout

Use PHP 8.3 or newer within the supported PHP range, Composer 2, Node.js 24 and npm.
From the package root, install the locked development dependencies:

```sh
composer install
npm ci --ignore-scripts --no-fund
```

Use `install` and `ci` for an existing checkout; dependency upgrades should be an
intentional, separate part of a change. Frontend tooling is needed for development
and rebuilding assets, not for applications consuming the prebuilt package.

PHP sources are in `src/`, templates and frontend sources in `resources/`,
translations in `lang/`, and tests in `tests/`. Official customer documentation is
in `docs/`. Keep engineering reports and working notes out of customer documentation.

## Prepare a change

Keep each pull request focused on one problem. Follow nearby code conventions,
preserve public behavior unless the change explicitly addresses it, and add or
adjust relevant tests. Explain breaking changes and migration steps. Update customer
documentation when configuration or behavior changes.

Write release notes only in the root `CHANGELOG.md`. The Mintlify changelog and
release archive are generated from it; do not edit those pages directly. Run
`npm run docs:changelog` and include the generated pages in your pull request.
Generation also runs before `npm run docs:dev`, `npm run docs:build` and
`npm run build`. CI checks that both pages match the source. Use
`## [4.0.0] - Unreleased` for a version still in development and replace
`Unreleased` with its actual `YYYY-MM-DD` publication date when releasing.

For component and frontend changes:

- Declare a typed `ComponentDefinition`; share styles through `stylesFrom` and
  compose complete components through `uses`.
- Preserve asset ownership for each rendered instance. Optional features must not
  activate on unrelated instances or inactive tabs and closed modals.
- Clean up listeners, timers, observers and other resources with their owner, while
  preserving dependencies still used by other instances or parent modals.
- Scope styles to explicit FLEX classes and keep playground assets out of unrelated
  application pages.
- Put user-facing messages in `lang/` and account for keyboard access, RTL,
  responsive layouts, light and dark themes, and Livewire navigation where relevant.
- Edit source files and regenerate assets and version manifests through the build.
  Do not hand-edit generated files in `resources/dist/` or add timestamp-based
  cache busting.

If you use an AI coding agent, follow the applicable `AGENTS.md` instructions,
including the permission rules for modifying tests.

## Verify your work

Run the checks relevant to the change from the package root:

```sh
composer analyse
composer test
composer test:filament-compat
npm run test:js
composer audit --locked
npm audit
```

During development, you can narrow Pest to an affected test file or test name.
Include regression coverage for fixes; do not suppress failures by increasing
static-analysis baseline counts or disabling checks.

When changing frontend assets, also run:

```sh
npm run build
npm run check:budgets
```

The normal build and public checks can run from a public checkout. Maintainers
also run separate release-acceptance checks. Report any check you cannot run;
do not disable a check or replace missing evidence with an assumed result.

For an integrated Laravel application, publish rebuilt assets with
`php artisan filament:assets` from the application root before checking the UI.
Verify actual interactions for affected states, especially modal opening and
closing, navigation, scrolling and asynchronous results. Describe any browser or
integration checks you could not run.

## Submit a pull request

Use a contribution fork or send a patch privately as permitted by the license.
Open the pull request against the relevant development branch and include:

- The problem, resulting behavior and a linked issue where available.
- Relevant configuration examples or screenshots for UI changes.
- Checks you ran, their results and any remaining limitations.
- Documentation changes, compatibility considerations and third-party notices.

Avoid unrelated formatting, generated-file churn or dependency updates. Do not
include secrets, personal data or local environment files. Maintainers may request
changes or decline a proposal based on compatibility, scope or maintenance cost.
