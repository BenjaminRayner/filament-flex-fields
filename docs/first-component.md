---
title: "Add a signature field to a Filament form"
sidebarTitle: "Your first component"
description: "Build your first FLEX Fields v4 workflow: add a signature column, configure SignatureField, validate a drawing and restore it when editing."
icon: "rocket"
---

Add a required signature pad to an **existing Filament resource**. This example uses `App\Models\User` and the `users` table. Use your own resource model and table if the signature belongs elsewhere.

By the end, an empty signature will fail validation, a drawn signature will save as SVG text, and the drawing will return when you edit the record.

<Note>Complete [Installation](/docs/installation) first. The default signature mode needs no Spatie integration, file disk, queue worker or managed field group.</Note>

## Build the workflow

<Steps>
  <Step title="Add the text column">

Create a migration in your development application:

```bash
php artisan make:migration add_signature_to_users_table --table=users
```

Use this body in the generated migration. Keep its generated filename:

```php Migration
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('signature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('signature');
        });
    }
};
```

Run your migration:

```bash
php artisan migrate
```

This also runs other pending migrations registered in your application, including package migrations. Review [Database setup](/docs/installation#database-setup) before deployment.

  </Step>
  <Step title="Allow the attribute on your model">

Add `signature` to the model's existing fillable list:

```php app/Models/User.php
protected $fillable = [
    'name',
    'email',
    'password',
    'signature',
];
```

Retain the attributes your model already uses. This mode stores a **string**, so do not add an `array` cast to `signature`.

  </Step>
  <Step title="Add SignatureField to the resource">

Import the field and append it to your resource's existing schema:

```php Resource form
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Filament\Schemas\Schema;

public static function form(Schema $schema): Schema
{
    return $schema->components([
        // Keep the resource's existing fields.
        SignatureField::make('signature')
            ->label('Signature')
            ->penColor('#18181b')
            ->penWidth(4)
            ->undoable()
            ->minStrokes(1)
            ->maxSizeKb(48)
            ->required(),
    ]);
}
```

If your resource delegates its form to a generated schema class, append the field in that class instead. The required field name is `signature` in both cases because it matches the model attribute.

  </Step>
  <Step title="Save and reopen a record">

Use the resource's normal create and edit actions. Complete its other required fields before testing the signature.

| Try | Expected result |
| --- | --- |
| Submit with an empty pad | Signature validation prevents saving |
| Draw at least one stroke and submit | The SVG string saves in `users.signature` |
| Open the saved record in edit mode | The drawing is restored |
| Change the drawing and save again | Reopening shows the updated signature |
| Submit a signature exceeding 48 KB | The configured size limit prevents saving |

  </Step>
</Steps>

## Why these settings?

| Setting | Purpose |
| --- | --- |
| `required()` | An empty value cannot be submitted |
| `minStrokes(1)` | Require drawing rather than an untouched pad |
| `maxSizeKb(48)` | Limit the stored signature size |
| `penColor()` and `penWidth()` | Set the drawing appearance |
| `undoable()` | Allow the user to correct strokes |

The field validates its signature representation; it does not establish a person's identity or replace your application's permissions and approval rules.

## Read the saved value

After saving, `$user->signature` is SVG text, not a storage path or a JSON document. Use the [SignatureField guide](/docs/signaturefield) before switching to a media-backed storage mode or presenting the result outside the form.

If the drawing appears in the form but does not persist, check the column name, model assignment policy and save path using [Saving & validation](/docs/data-and-validation).

## Use your own save action

A custom Livewire page should initialize the form with `fill()` and save the validated value returned by `getState()`. Do not write raw public form state directly to your model. The [custom save example](/docs/data-and-validation#save-from-your-own-livewire-component) shows the complete pattern.

<CardGroup cols={2}>
  <Card title="Configure the form" icon="sliders-horizontal" href="/docs/configuration">Choose sizes, variants and optional features.</Card>
  <Card title="Explore SignatureField" icon="pen-tool" href="/docs/signaturefield">Choose drawing controls and other storage modes.</Card>
</CardGroup>
