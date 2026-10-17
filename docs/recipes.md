---
title: "Start with one working workflow"
sidebarTitle: "Application recipes"
description: "Add one field, an authorized picker or a relational gallery to your existing Filament Resource without adopting a new form platform."
icon: "book-open-check"
---

Use one field. Keep your existing Resource, native controls, models and policies.
Start with a workflow you can save and reopen before adding more features.

<CardGroup cols={3}>
  <Card title="Use a field" icon="pen-line" href="/docs/first-component">A signature stored in an ordinary text column. No media disk, worker or managed JSON group.</Card>
  <Card title="Browse a large catalog" icon="users-round" href="/docs/scoped-select-recipe">Paginated user queries, restored labels and submitted-ID validation share one access scope.</Card>
  <Card title="Connect media" icon="images" href="/docs/file-upload-gallery-recipe">Post and Gallery models, two collections, custom filenames, conversions and explicit SQL persistence.</Card>
</CardGroup>

## Know what you are adopting

| Workflow | FLEX supplies | Your application owns | Stored value |
| --- | --- | --- | --- |
| Signature | Drawing, editing and signature validation | Column, assignment policy and normal Resource save | SVG text |
| Assignee | Shared dropdown, virtual rows and page-request handling | Authorized query, foreign key and validation scope | User ID |
| Gallery | Upload/editor/preview interface and media lifecycle | Models, relationship mapping, record transaction, disk and access policy | Related SQL rows with source paths |

The gallery and picker helpers ship as **copyable application examples** in
`resources/recipes` inside the Composer package. FLEX does not register their
migrations, models, routes or seeders. Review the files and adapt their names to
your existing application. Do not overwrite an existing model or migration.
The documentation's marked code blocks are generated from those same maintained
files; CI checks for drift. The package tests execute the helpers against real
Eloquent tables and real form fields.

For media in an existing Resource, use the
[native create/edit integration](/docs/file-upload-resource-recipe) with the same
gallery mapper. The custom-page variant is an alternative, not a prerequisite.

Managed JSON custom fields are an optional, separate way to model configurable
attributes. They are not a prerequisite for any of these recipes.

## A useful completion check

1. Reject an empty or unauthorized submitted value.
2. Save through the existing validated form flow.
3. Reload the saved record in a fresh form.
4. Edit it and save again.
5. Check a failed save and a second instance where the workflow needs one.

For media, distinguish temporary upload, physical storage, SQL reference and
acceptance. A database rollback does not undo an object-storage write. Select
your removal and retention policy before allowing destructive file cleanup.

## Work with your assistant

Use [AI-assisted development](/docs/ai-assisted-development) to inspect installed
APIs and retrieve declared examples. Include your existing save flow and policies
in the request. Ask for save/reopen verification, not only a working screenshot.

If a native Filament control already meets your needs, keep it. The
[decision guide](/docs/choosing-components) explains what to compare.

## Build an extension only when needed

When configuration cannot express the requirement, follow
[extension authoring](/docs/extensions). Reuse real child controls and their
resource ownership. You do not need to learn the extension SDK to use one field.

See [verification and compatibility](/docs/verification) for the scope of the
checks and how to reproduce them. Passing package tests does not verify your
application's own policies, schema or deployment.
