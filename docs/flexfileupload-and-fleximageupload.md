---
title: "FLEX File Upload"
description: "Add file lists, image grids, avatars, URL imports and camera capture to your forms."
icon: "upload"
"canonical": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/file-upload-list.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FLEX File Upload in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/file-upload-list-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/file-upload-list-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/file-upload-list.webp"
"twitter:image:alt": "FLEX File Upload in the FLEX Fields playground"
---

{/* flex-component-media:start */}
<script type="application/ld+json">
{JSON.stringify({
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://flex-fields.bjanczak.com/#organization",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "logo": "https://flex-fields.bjanczak.com/art/docs-logo-light.svg",
      "sameAs": [
        "https://github.com/janczakb/filament-flex-fields"
      ]
    },
    {
      "@type": "WebSite",
      "@id": "https://flex-fields.bjanczak.com/#website",
      "name": "FLEX Fields",
      "url": "https://flex-fields.bjanczak.com/",
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "inLanguage": "en"
    },
    {
      "@type": "WebPage",
      "@id": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload",
      "name": "FLEX File Upload",
      "description": "Add file lists, image grids, avatars, URL imports and camera capture to your forms.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload",
      "headline": "FLEX File Upload",
      "description": "Add file lists, image grids, avatars, URL imports and camera capture to your forms.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/file-upload-list-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/file-upload-list-motion.mp4#video",
      "name": "FLEX File Upload — FLEX Fields demonstration",
      "description": "A recorded demonstration of FLEX File Upload in the FLEX Fields playground for Filament. Add file lists, image grids, avatars, URL imports and camera capture to your forms.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/file-upload-list.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.117S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/file-upload-list-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexfileupload-and-fleximageupload#article"
      }
    }
  ]
}).replace(/</g, '\u003c')}
</script>

<video
  autoPlay
  muted
  loop
  playsInline
  controls
  preload="metadata"
  className="w-full aspect-video rounded-xl"
  src="/art/previews/file-upload-list-motion.mp4"
  poster="/art/previews/file-upload-list.webp"
  width="1920"
  height="1080"
  aria-label="FLEX File Upload — FLEX Fields demonstration"
>
  <a href="/art/previews/file-upload-list-motion.mp4">Watch the FLEX File Upload demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

FLEX File Upload provides a responsive file picker, list/grid layouts and circular avatars. Configure storage, validation, image processing and optional metadata editing for each field. Local, URL and camera files share the same permanent Media OS storage lifecycle.

For database persistence, custom gallery tables and existing files, use [models, galleries and migration recipes](/docs/file-upload-models-and-migration).

For an end-to-end example with `Post` and `Gallery` models, migrations, create/edit persistence, custom filenames and optional conversion rows, see [A complete Post gallery](/docs/file-upload-gallery-recipe).

To reuse saved images in a website gallery or API, see [Gallery images in your website and API](/docs/file-upload-gallery-api). It includes authorized routes, original-image fallbacks, a Blade view and the Spatie alternative without an extra conversion table.

## Choose a field

| Class | Storage / purpose |
| --- | --- |
| `FlexFileUpload` | Laravel disk paths; documents or your chosen formats |
| `FlexImageUpload` | Image-oriented disk field; its recommended preset selects images |
| `FlexSpatieMediaLibraryFileUpload` | Optional Spatie relationship; owned Media UUIDs |

Imports:

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexFileUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexImageUpload;
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\Spatie\FlexSpatieMediaLibraryFileUpload;
```

## One document

```php
FlexFileUpload::make('contract')
    ->label('Contract')
    ->withRecommendedDefaults()
    ->acceptedFileTypes(['application/pdf'])
    ->disk('local')
    ->directory('contracts')
    ->required();
```

`withRecommendedDefaults()` configures a 5 MiB per-file limit, create-form path authorization, removal/replacement cleanup, open/download actions and focus outline. For `FlexFileUpload` it selects document formats; for `FlexImageUpload` it selects images. Override individual settings afterward.

## Image gallery

```php
FlexImageUpload::make('photos')
    ->withRecommendedDefaults()
    ->multiple()
    ->uploadLayout('grid')
    ->gridColumns(4)
    ->maxFiles(12)
    ->reorderable();
```

Grid tiles default to 116 × 116 px, 24 px corners and a 2 px border. Images show previews; other formats show a file-type icon and filename. Actual transfer progress uses a matching percentage and rounded border. After completion, the percentage transitions to the delete action. Upload failures show a red border and retry action.

## File list

```php
FlexFileUpload::make('attachments')
    ->withRecommendedDefaults()
    ->multiple()
    ->uploadLayout('list')
    ->maxFiles(5)
    ->uploadSummary()
    ->remainingSlotsLabel();
```

## Grid sizes and action menu

```php
FlexImageUpload::make('photos')->multiple()->uploadLayout('grid')
    ->gridSize('sm')->gridActions('menu')->fileClickPreview();
```

Choose 116px `md` or 96px `sm` tiles. Secondary actions can stay below, animate inside on hover/focus, or appear in the ellipsis menu. Menu items follow enabled features; preview can open by clicking an image. [All grid and viewer options](/docs/file-upload-api#grid-sizes-and-actions).

## Compact list

```php
FlexFileUpload::make('attachments')->multiple()->uploadLayout('compact');
```

`compactList()` is also available for the compact list presentation.

## Avatar

```php
FlexImageUpload::make('avatar')
    ->withRecommendedDefaults()
    ->avatar()
    ->avatarSize('md')
    ->imageEditor()
    ->circleCropper()
    ->disk('public')
    ->directory('avatars');
```

The empty avatar shows a centered image icon. File actions depend on its loading, transfer, success, failure and editing state. `avatar()` takes precedence over an explicitly configured file layout.

| Avatar size | Diameter |
| --- | --- |
| `sm` | 96 px |
| `md` | 128 px |
| `lg` | 160 px |
| Integer | 64–512 px |

```php
use Bjanczak\FilamentFlexFields\Support\FileUpload\Presentation\AvatarSize;

FlexImageUpload::make('avatar')->imagesOnly()->avatar()->avatarSize(AvatarSize::Large);
```

## Image editor

Enable editing for avatar, grid or list images with `imageEditor()`. The edit action
appears only for supported images that are ready to edit. It is hidden during
loading, transfer, pause, failure and removal, and when the field is disabled.

```php
FlexImageUpload::make('photos')
    ->multiple()
    ->uploadLayout('grid')
    ->imageEditor()
    ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1'])
    ->imageEditorViewportWidth('1920')
    ->imageEditorViewportHeight('1080');
```

Use `circleCropper()` for a circular avatar crop. Other images support free cropping,
configured aspect ratios, quarter-turn rotation and horizontal/vertical flipping.
The editor includes brightness, warmth, contrast, exposure, highlights, shadows,
fade, saturation, vibrance, clarity, sharpness and vignette. Reset restores the
original crop and adjustments. Save shows a busy indicator while exporting.

A stored image opens from its original file, even when its attachment preview uses
a small conversion. Interactive adjustments use a smaller preview for responsiveness;
Save renders the original resolution before applying your crop and output resizing.
On touch screens, use the crop handles, drag the image, pinch to zoom and move the
adjustment slider. Keyboard input also works on the slider.

With `instantUpload(false)`, the edited image waits for your upload action. The
previous server association remains until that new transfer succeeds. With automatic
transfer, it starts immediately. Configured file metadata follows the replacement.
Media OS creates the configured conversions from the edited bytes when the form
saves; queued conversions show the original until the worker finishes. Spatie
uses the conversions registered on your model and the same configured queue.

Raster editing exports a still frame for animated images. It does not preserve all
EXIF metadata. Cross-origin storage must allow the browser to fetch the authorized
original through its CORS policy. Editor pixel and memory limits follow your Media
OS image limits; extremely large images can be rejected before adjustment/export.

## Automatic or manual transfer

```php
FlexFileUpload::make('attachments')->multiple()->instantUpload(false);
```

Selection uploads immediately by default. With `instantUpload(false)`, files wait for Upload or the enclosing form submission. Pending files are transferred before that form continues. Without an enclosing form, use Upload explicitly. URL import and camera confirmation process their files immediately.

Transfer completion means the file reached temporary storage. Permanent storage and record acceptance occur in the save lifecycle. Thumbnail jobs may finish afterward.

## Parallel transfers

```php
FlexFileUpload::make('attachments')->multiple()->maxParallelUploads(3);
```

This permits up to three simultaneous transfers in that field. It is independent of `maxFiles()` and worker-job concurrency.

## Pause and resume

```php
FlexFileUpload::make('attachments')
    ->multiple()->uploadLayout('grid')->pausable();
```

Lists and grids support individual pause/resume and a field-level queue control. Pausing the queue also holds newly added files. Paused files must be resumed before submitting the form. Avatar mode does not expose this pause toolbar.

**Resume currently restarts a whole-file request. It does not continue from the last uploaded byte.** Use this option when request restart is acceptable; chunk/offset resumable transport is not included.

## Drag to reorder

```php
FlexFileUpload::make('attachments')
    ->multiple()->uploadLayout('list')->reorderable();
```

Both grid tiles and list rows support animated drag sorting, including existing attachments. Keyboard sorting is available on handles/focusable tiles; Escape cancels a drag. Reduced-motion preferences remove transitions. Disk fields save the ordered path array; Spatie fields integrate with native media ordering.

## Presentation options

```php
FlexFileUpload::make('attachments')
    ->variant('secondary')
    ->dropzoneLabel('Drop your project files here')
    ->emptyStateHint('Use the signed final documents.')
    ->showFileIcon()
    ->uploadSummary()
    ->requireReplaceConfirmation();
```

Variants: `primary`, `secondary`, `flat`. The built-in description displays declared formats and limits. Use `emptyStateHint()` for rules that cannot be inferred, such as organization-specific content requirements.

Custom tile size:

```php
FlexImageUpload::make('photos')
    ->imagesOnly()->multiple()->uploadLayout('grid')
    ->gridTileSize(160)->gridColumns(3);
```

Tile sizes are 80–320 px; columns are 1–8. Tiles wrap to fit narrow containers without stretching.

## Model state

A single disk field stores a relative path or null. A multiple disk field stores an ordered array of paths; use an array cast for the model attribute:

```php
protected function casts(): array
{
    return ['photos' => 'array'];
}
```

Configure your model's normal fillable/save policy. Spatie fields manage a media relationship and do not require an equivalent path-array column.

## Continue configuring

- [Validation, disks, filenames and cleanup](/docs/file-upload-storage-and-validation)
- [Image conversions, avatars, URL and camera](/docs/file-upload-images-and-sources)
- [Optional information editor and translations](/docs/file-upload-metadata)
- [PHP events, save hooks and recovery](/docs/file-upload-events-and-recovery)
- [Complete field configuration reference](/docs/file-upload-api)
- [Media OS storage service](/docs/media-capture-os)

## Continue configuring your field

<CardGroup cols={2}>
  <Card title="Storage and validation" icon="shield-check" href="/docs/file-upload-storage-and-validation">Choose accepted formats, limits, naming, disks and cleanup.</Card>
  <Card title="Images and sources" icon="images" href="/docs/file-upload-images-and-sources">Configure conversions, default avatars, URL import and capture.</Card>
  <Card title="Metadata editor" icon="file-pen-line" href="/docs/file-upload-metadata">Choose exactly which fields and languages your users can edit.</Card>
  <Card title="Events and recovery" icon="workflow" href="/docs/file-upload-events-and-recovery">React to storage, acceptance, errors and interrupted operations.</Card>
</CardGroup>


### Camera controls and upload feedback

With `->allowWebcamUpload()`, the camera opens in a dedicated view with a shutter,
mirror mode and a composition grid. Available devices can switch cameras; torch
appears only when the selected camera supports it. Review the photo before using
it, or retake it. Closing the camera, hiding the page or reviewing a capture stops
the live stream. Device permission is requested only when opening the camera.

The capture frame is 4:5 on phones and 16:9 on desktop. The saved photo uses the
same centered crop as the preview, retaining pixel density within your configured
Media OS limits. The sensor's native photo is used when supported; otherwise the
current video frame is captured. Mirror mode applies to the saved photo too.

Controls appear only when the selected camera and browser expose support:

- Zoom presets, a zoom slider and two-finger zoom.
- Torch for continuous illumination, separately from photo flash modes.
- Exposure modes, compensation, exposure time and ISO.
- White balance modes and color temperature.
- Focus modes and focus distance.
- Hardware brightness, contrast, saturation, sharpness, frame rate, pan/tilt and
  background blur where available.
- Native photo resolution, flash and red-eye reduction where supported.

Switching cameras refreshes the available settings. A rejected setting shows an
error while keeping the live preview usable. There is no standard browser night-mode
switch; available exposure and ISO controls can be used instead. Browser support
varies, including on iOS.

Camera files obey your byte, pixel and working-memory limits. If a default sensor
photo exceeds the pixel limit with flash off, capture can use the admitted video
frame. Explicit oversized resolution or flash requests show an error rather than
quietly changing those choices. A sensor photo needing no crop or mirror retains
its original encoded bytes; processed JPEG/WebP uses maximum encoder quality.

Native Filament submit buttons show the upload message and remain disabled while
any field is transferring files. Customize the message with
`->uploadingMessage('Uploading photos…')`. Manual queues upload through the shared
submission barrier; paused files must be resumed first. Background conversions are
separate from transfer and do not hold the submit button after files are uploaded.
A custom HTML button needs its own loading presentation.

The editor keeps original pixel dimensions unless cropping or a configured resize
changes them. Interactive adjustment uses a small preview; full-resolution output
is generated on Save. JPEG/WebP use maximum browser encoder quality and are still
reencoded. PNG encoding preserves raster pixels; canvas editing does not preserve
every EXIF or color-profile attribute. Large images remain subject to configured
Media OS pixel and working-memory limits.


## Compact picker header and file clicks

Use `compactHeader()` with either `uploadLayout('list')` or `uploadLayout('grid')` to hide the large file artwork while keeping requirements and Select files. `maxFiles()` also disables adding once a multiple field reaches its active slot limit. `fileClickAction()` runs a trusted PHP callback when a completed file is clicked; `fileClickUrl()` resolves a custom destination. See [configuration and complete examples](/docs/file-upload-api#click-an-uploaded-file).
