---
title: "Install FLEX Fields v4 in a Filament panel"
sidebarTitle: "Installation"
description: "Install FLEX Fields v4 with Composer, register it in your Filament 5 panel, publish prebuilt assets and verify your setup."
icon: "package-plus"
---

Start with a working Laravel application and a Filament 5 panel. This guide installs **FLEX Fields v4** in that existing panel; it does not replace your resource models or form schemas.

## Before you begin

| Requirement | What to check |
| --- | --- |
| PHP | The package requires `^8.3` |
| Filament | The package requires `^5.6` (Filament 5.6.0 or newer within 5.x) |
| Laravel and Livewire | Use versions compatible with your installed Filament release |
| Browser assets | Publish the prebuilt files after installing the package |

See [Requirements & integrations](/docs/requirements) for optional packages, browser features and infrastructure.

<Note>No FLEX Node.js build or custom Tailwind compilation is required for normal use. The distributed package includes its browser files.</Note>

## Install in your panel

<Steps>
  <Step title="Install the v4 package">

```bash
composer require janczakb/filament-flex-fields:"^4.0"
```

The version constraint selects the v4 release line. Composer resolves the required dependencies, and Laravel discovers the service provider automatically when package discovery is enabled.

If Composer cannot resolve this constraint, check the v4 release availability in the repository your application uses and the dependency conflict it reports. A local path checkout must also advertise a compatible package version; changing documentation does not change its Composer version.

  </Step>
  <Step title="Enable FLEX in the panel provider">

Add the import and plugin call to the provider for the panel that uses FLEX:

```php app/Providers/Filament/AdminPanelProvider.php
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // Keep your existing panel configuration here.
        ->plugin(FilamentFlexFieldsPlugin::make());
}
```

Keep your existing settings and plugins. If several panels use FLEX, register it in each corresponding panel provider.

  </Step>
  <Step title="Publish the included browser files">

```bash
php artisan filament:assets
```

This publishes the files included in the package. You do not need to rebuild FLEX in your application.

  </Step>
  <Step title="Check the installed package and publication">

```bash
composer show janczakb/filament-flex-fields
php artisan fff:assets:doctor --published --json
```

Confirm that Composer reports the intended v4 version and the diagnostic report does not identify missing or mismatched published files. Then open a page containing a FLEX field and try an interaction. Package installation and publication checks alone do not prove that the browser can reach the files.

  </Step>
</Steps>

<Check>Next, follow [Your first component](/docs/first-component) to save and restore a signature in an existing resource.</Check>

## Database setup

Individual fields use your application's attributes. Choose the right column and cast for each value: text for a default signature, JSON for a weekly schedule, or a date column for a date picker. [Saving & validation](/docs/data-and-validation) includes these examples.

The FLEX service provider also registers migrations for managed field groups and schema history. **Your application's `php artisan migrate` runs pending package migrations too**, even if the field-group management resource is disabled. Composer installation does not itself run migrations.

Review pending migrations through your normal application process. If you need database-backed field-group management, use the [Flex Field Groups guide](/docs/flex-field-groups) for its storage and permission setup.

## Optional defaults and translations

Publish configuration only when you need application-wide overrides:

```bash
php artisan vendor:publish --tag=filament-flex-fields-config
```

Edit `config/filament-flex-fields.php`. [Configuration & styling](/docs/configuration) explains which settings belong to the application, panel and individual field.

Publish interface translations when you want to customize their text:

```bash
php artisan vendor:publish --tag=filament-flex-fields-translations
```

Keep configuration and translations your application already owns. Do not routinely publish over them with `--force`.

<Accordion title="My application disables package discovery">
  Register `Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider` using your Laravel version's provider registration. For an application using `bootstrap/providers.php`, add it to the existing list:

```php bootstrap/providers.php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    Bjanczak\FilamentFlexFields\FilamentFlexFieldsServiceProvider::class,
];
```

  Keep the other providers your application already registers. Manual registration is unnecessary when discovery is enabled.
</Accordion>

## Integrations and production deployment

A default signature pad needs no Spatie package, media disk, queue worker or speech runtime. Install optional integrations only for the features you enable; see [Requirements & integrations](/docs/requirements).

For Composer hooks, deployments using `--no-scripts`, cache rebuilding and long-running workers, follow [Updating & deployment](/docs/updating). Check [Licensing](/docs/licensing) for your intended application use.

<CardGroup cols={2}>
  <Card title="Your first component" icon="rocket" href="/docs/first-component">Add a field, validate it, save it and reopen it.</Card>
  <Card title="Resolve a setup problem" icon="life-buoy" href="/docs/troubleshooting">Diagnose publication, panel setup and browser access.</Card>
</CardGroup>
