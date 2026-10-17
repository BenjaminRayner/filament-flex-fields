---
title: "Build forms with AI"
sidebarTitle: "Components & configuration"
description: "Use installed FLEX component metadata, PHP examples and actual method signatures to configure and verify forms with your assistant."
icon: "blocks"
---

Describe the interaction, stored value and save behavior you need. Your assistant
can combine FLEX controls with native Filament fields in an existing Resource.
Managed JSON field groups are an optional storage approach.

## Discover, inspect, configure

```bash
php artisan fff:components:describe --kind=field --json
php artisan fff:components:describe font-select --json
php artisan fff:components:describe font-select --example=1 --json
```

Follow `pagination.nextOffset` when listing more than one page. Example IDs come
from the selected component's report; an absent example is not permission to
invent one. The registry also contains supporting definitions, so its total is
not the marketing count of end-user controls.

The assistant then reads the component guide and installed PHP declarations for
the requested configuration, including inherited methods and closure signatures.
The [component library](/docs/component-library) links to the individual guides.

## Does this cover every configuration?

The discovery command lists declared capabilities, examples and child-schema
options. It does not enumerate every fluent PHP method, callback or valid
combination. Author guidance is optional and may be absent. A missing renderer
is reported as unknown rather than inferred from a component ID.

An assistant must check the installed API and exercise the chosen workflow.
Instructions and examples reduce guessing; they do not certify every model's
output or every component configuration.

## Example request

```text
Add a CountryField and FontSelect to our existing ProductResource.
Use only the documented installed APIs. Restrict fonts to latin-ext and keep the
selected label in the system font. Store the country code and stable font ID,
not their labels or preview URLs. Preserve our existing fields and policies.
Verify required validation, invalid submitted values, save/reload, editing and
the modal variant used by this application.
```

For large record lists, specify the authorized search scope, pagination and
selected-label lookup. For media, first choose between [an upload field](/docs/ai/file-upload)
and [a service without a form](/docs/ai/media-os).

## Verify the application's result

Check the configured interaction, invalid values, record authorization, storage
format and save/reopen behavior. A successful asset doctor verifies published
files; it does not prove the application saved its record correctly.

Use the [verification guide](/docs/verification) and [working recipes](/docs/recipes)
for application-level examples.
