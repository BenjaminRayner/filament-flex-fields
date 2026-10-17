# One field and persistence

Keep the existing Resource schema and add a field to the model attribute that
already exists, or propose the necessary migration explicitly. Do not assume an
SVG, path, ID and array share the same column/cast.

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\SignatureField;

SignatureField::make('signature')
    ->label('Signature')
    ->minStrokes(1)
    ->maxSizeKb(48)
    ->undoable()
    ->required();
```

Default signature mode stores SVG text: use a nullable text column and the
application's assignment policy, not an array cast. An empty required signature
must reject. Save and reopen through the existing Resource. A signature drawing
does not establish identity, consent or application authorization.

For a custom Livewire page, initialize its existing form with `fill()` and persist
the validated value returned by `getState()`. Do not write the raw public form
state directly. Set the form's model/record where its fields need one. Media-backed
fields have additional record-completion responsibilities; read the media reference.

Prefer the existing registered Filament panel when the task is a Resource change.
Do not replace it with a standalone test shell and assume that native/FLEX layout
hooks are present. A genuinely standalone form follows the public layout phases:
https://flex-fields.bjanczak.com/docs/extensions/standalone . Publish native and
FLEX assets from that application's own installed vendor set. Borrowing another
application's public files can mix framework versions even when URLs look valid.
The FLEX asset doctor does not verify the contents of native Filament scripts.

Common machine values: Country uses an ISO country code such as `PL`; FontSelect
uses a provider-prefixed catalog ID such as `google:inter`. Inspect the specific
Phone, schedule and upload docs for their formats rather than guessing from the UI.

Guides:
- https://flex-fields.bjanczak.com/docs/first-component
- https://flex-fields.bjanczak.com/docs/data-and-validation
- https://flex-fields.bjanczak.com/docs/signaturefield
