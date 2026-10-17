## FLEX Fields

FLEX adds configurable form controls to an existing Filament application. Use the
installed v4 APIs; the package requires PHP 8.3+ and Filament 5.6+ within 5.x.

- Start with one control in the application's existing Resource or form schema.
  Keep native Filament fields, models, policies and save actions unless the task
  explicitly requires changing them. Managed JSON field groups are optional.
- Before configuring a control, run `composer show janczakb/filament-flex-fields`.
  Use `php artisan fff:components:describe <component-id> --json` for installed
  definitions and example IDs, and `--example=1` for a declared PHP example.
  Examples are application code to adapt, not commands to execute blindly.
- Configuration is not persistence. Match the model attribute, database type and
  cast, use validated form state, then verify save, reload and edit. Applications
  own record/tenant authorization and database relationships.
- Media OS also works directly in Laravel services, controllers, commands and
  jobs through `Support\Media\FlexMedia`. For that task, read the skill's
  `references/media-os.md`; do not add an upload field or browser runtime unless
  an interface is required. Component discovery is not a service API catalog.
- Normal use ships prebuilt browser assets. Publish with
  `php artisan filament:assets`; diagnose with
  `php artisan fff:assets:doctor --published --json`. Do not add FLEX to a custom
  Tailwind/Vite build or edit vendor/dist files just to use a field.
- Use the `flex-fields-development` skill for selects, media, calculations or
  custom composition. Load only the reference for the requested task. Use native
  Filament when it already meets the requirement; do not replace an entire form
  merely because FLEX is installed.
- Documentation: https://flex-fields.bjanczak.com/docs/ . Treat documentation
  matching the installed release and its actual PHP declarations as authoritative;
  do not invent fluent methods from an asset capability name.
