---
title: "Save and validate FLEX Fields values"
sidebarTitle: "Saving & validation"
description: "Save FLEX Fields v4 values through Filament forms. Match database columns and casts, validate schedules and dates, and implement a custom save action."
icon: "database-zap"
---

A field's appearance, submitted value and saved representation are different parts of a workflow. Match the database column to the component's storage mode, then save the **validated form state**.

## Match the value to your model

| Control or mode | Saved value | Model setup |
| --- | --- | --- |
| Select, single | Selected option identifier | Compatible scalar attribute or configured relationship |
| Tags, plain input | Array of labels | JSON column and array cast |
| Tags, Spatie mode | Related Tag records | Spatie model and relationship setup |
| Signature, default | SVG string | Text column; no array cast |
| Currency, default | Integer minor units | Compatible integer column; check currency/storage options |
| Weekly schedule | Days, enabled state, periods and optional timezone | JSON column and array cast |
| Single disk upload | Storage path | String attribute |
| Multiple disk uploads | Array of paths | JSON column and array cast |
| Disk upload information | Companion editorial document | Separate JSON attribute and array cast |
| Translated content | Locale-to-value map | JSON/array cast or configured Spatie Translatable model |

Changing a storage mode may change the value contract. Use the component's reference before replacing an existing field or reusing an existing column.

## A simple text value

For the [signature tutorial](/docs/first-component), the column and field share the name `signature`:

<CodeGroup>
```php Column
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inside your migration's up() method:
Schema::table('users', function (Blueprint $table): void {
    $table->text('signature')->nullable();
});
```
```php Field
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;

SignatureField::make('signature')
    ->required()
    ->minStrokes(1)
    ->maxSizeKb(48);
```
</CodeGroup>

Add `signature` to the model's existing assignment policy. The standard resource create/edit actions then save the field to that attribute. A blank signature fails validation; a valid drawing saves as SVG text and restores on edit.

## A structured JSON value

This example adds opening hours to an **existing** `App\Models\Location` model and `locations` table. Use your application's model and table names.

<Steps>
  <Step title="Prepare the JSON column">

```php Migration body
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

public function up(): void
{
    Schema::table('locations', function (Blueprint $table): void {
        $table->json('opening_hours')->nullable();
    });
}

public function down(): void
{
    Schema::table('locations', function (Blueprint $table): void {
        $table->dropColumn('opening_hours');
    });
}
```

Run the migration through your normal application process.

  </Step>
  <Step title="Add the model cast">

Merge these entries into the model's existing settings:

```php app/Models/Location.php
protected $fillable = [
    'name',
    'opening_hours',
];

protected function casts(): array
{
    return [
        'opening_hours' => 'array',
    ];
}
```

Keep existing fillable attributes and casts. The array cast makes the saved JSON available as a PHP array when the edit form is filled.

  </Step>
  <Step title="Add the validated schedule">

Append the field to the Location resource's existing form:

```php Resource field
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\ScheduleField;

ScheduleField::make('opening_hours')
    ->timezone('Europe/Warsaw')
    ->timeStep(15)
    ->minSlots(1)
    ->maxSlots(3)
    ->required();
```

The standard resource save validates the submitted schedule before persisting the attribute. Reopen the location to verify that the periods and timezone return.

  </Step>
</Steps>

One day in the saved structure looks like this; the full value also contains the other configured days:

```json
{
  "timezone": "Europe/Warsaw",
  "days": {
    "mon": {
      "enabled": true,
      "slots": [
        { "from": "09:00", "to": "17:00", "type": "slot" }
      ]
    }
  }
}
```

| Submitted schedule | Result |
| --- | --- |
| Enabled day with a valid `09:00`–`17:00` period | Accepted when the remaining configured days are valid |
| Enabled day with no period | Rejected with the configured minimum requirement |
| Period containing `25:00` | Rejected as an invalid time |
| Overlapping periods on the same day | Rejected |
| More than three periods for an enabled day | Rejected by the configured maximum |

[ScheduleField](/docs/schedule-field#validation-when-saving) explains breaks, overnight periods and protected day states. A weekly schedule describes hours; it does not reserve appointments or prevent concurrent bookings.

## Date, datetime and time columns

Keep display formatting separate from storage formatting:

```php Resource fields
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDatePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexDateTimePicker;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexTimeField;

$fields = [
    FlexDatePicker::make('publish_date')
        ->displayFormat('M j, Y')
        ->storageFormat('Y-m-d')
        ->minDate('2026-01-01')
        ->maxDate('2026-12-31'),

    FlexDateTimePicker::make('starts_at')
        ->hourCycle(24)
        ->timeZone('UTC')
        ->storageFormat('Y-m-d H:i:s'),

    FlexTimeField::make('opens_at')
        ->storageFormat('H:i:s')
        ->allowedTimes(['09:00', '10:30', '14:00']),
];
```

Prepare matching columns in a migration:

```php Migration body
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::table('locations', function (Blueprint $table): void {
    $table->date('publish_date')->nullable();
    $table->dateTime('starts_at')->nullable();
    $table->time('opens_at')->nullable();
});
```

Add the attributes to the existing assignment policy and merge these casts:

```php Model casts
protected function casts(): array
{
    return [
        'opening_hours' => 'array',
        'publish_date' => 'date',
        'starts_at' => 'datetime',
    ];
}
```

Keep `opens_at` as the time string; Eloquent has no standard `time` cast. For this example, use UTC consistently as the application's datetime storage convention.

| Selection | Persisted representation |
| --- | --- |
| A date displayed as `Oct 15, 2026` | `2026-10-15` |
| Date/time selected as October 15 at 09:30 | `2026-10-15 09:30:00` |
| Allowed opening time 10:30 | `10:30:00` |
| Date outside 2026 or opening time 11:00 | Rejected by the corresponding field constraint |

<Note>
  `timeZone()` supplies calendar Today and the timezone label. It does **not** convert stored wall time into another timezone. If users enter local times and your application stores UTC, implement that conversion deliberately in the application's save/read workflow. A date-only value is a calendar day, not a timestamp.
</Note>

A date range normally stores `start` and `end` together; use JSON or explicitly map the endpoints to separate columns. See [date/time configuration](/docs/date-time-configuration) for precision, storage formats and unavailable dates.

## Save from your own Livewire component

This example edits the signed-in user's SVG signature inside an already configured Filament panel. It assumes your existing `UserPolicy::update()` authorizes the operation and the `signature` column and assignment policy from the tutorial are ready.

```php app/Livewire/EditSignature.php
<?php

namespace App\Livewire;

use App\Models\User;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class EditSignature extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'signature' => $this->editableUser()->signature,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SignatureField::make('signature')
                    ->required()
                    ->minStrokes(1)
                    ->maxSizeKb(48),
            ])
            ->statePath('data')
            ->model($this->editableUser());
    }

    public function save(): void
    {
        $user = $this->editableUser();
        $state = $this->form->getState();

        $user->update(['signature' => $state['signature']]);
        $this->form->fill(['signature' => $user->signature]);
    }

    private function editableUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('update', $user);

        return $user;
    }

    public function render(): View
    {
        return view('livewire.edit-signature');
    }
}
```

Render the form in the component view, then place `@livewire('edit-signature')` in your authenticated panel page:

```blade resources/views/livewire/edit-signature.blade.php
<div>
    <form wire:submit="save">
        {{ $this->form }}

        <button type="submit" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Save signature</span>
            <span wire:loading wire:target="save">Saving…</span>
        </button>
    </form>
</div>
```

`fill()` prepares restored data for the field. `getState()` validates and produces the value to persist. Authorization is checked on submission too. This example uses the default text signature mode; media-backed fields need their [record completion hooks](/docs/file-upload-events-and-recovery) as well.

## Apply business rules and permissions

A valid option identifier is not proof that the user may assign that record. Limit both the visible options and the server rule. For example, an existing `App\Models\Project` model has a `user_id` owner column:

```php
use App\Models\Project;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SelectField;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

SelectField::make('project_id')
    ->options(fn (): array => Project::query()
        ->where('user_id', auth()->id())
        ->pluck('name', 'id')
        ->all())
    ->required()
    ->rules([
        Rule::exists('projects', 'id')->where(
            fn (Builder $query): Builder => $query
                ->where('user_id', auth()->id()),
        ),
    ]);
```

Use this in an authenticated form and also authorize the record being edited. Here `project_id` is a scalar foreign-key attribute; relationship-saving configurations have their own setup. For large option sets, replace the full `pluck()` with the [paginated Select provider](/docs/selectfield).

## Control whether a field is saved

| Setting | What to understand |
| --- | --- |
| `disabled()` | Stops interaction; ordinary disabled fields are excluded from saved state by default |
| `readOnly()` | Restricts editing on components that support it; do not treat it as an authorization rule |
| `hidden()` | Changes visibility; saving and relationship behavior depend on the field configuration |
| `dehydrated(false)` | Excludes an ordinary attribute from the form's persisted state |

Use explicit persistence settings when displaying information that must not update a column. Relationship fields can have separate save behavior. [Filament's field lifecycle documentation](https://filamentphp.com/docs/5.x/forms/overview#field-dehydration) explains the framework behavior these fields participate in.

## Files have a record lifecycle too

Temporary upload, permanent file storage, record acceptance and background conversions are separate milestones. Database transactions do not also transact object storage.

Configure file types, size and dimensions on the upload, then use [Upload events & recovery](/docs/file-upload-events-and-recovery) for staging and completion in a custom save workflow. Metadata in disk mode uses a separate companion attribute. Native and Spatie conversions retain the stored original.

## Validate other write paths

An API endpoint, import or queued job can bypass this form. Apply the relevant validation, permissions and tenant restrictions there too. A control's browser checks do not validate arbitrary Eloquent writes.

For extension values, use the public [submission contract](/docs/extensions/submission) and [validation guide](/docs/extensions/validation).

<CardGroup cols={2}>
  <Card title="Upload storage & validation" icon="files" href="/docs/file-upload-storage-and-validation">Configure shared rules for file, URL and camera sources.</Card>
  <Card title="Troubleshoot saved values" icon="life-buoy" href="/docs/troubleshooting#the-value-does-not-save">Check columns, casts and your save action.</Card>
</CardGroup>
