---
title: "VideoField"
description: Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.
icon: "video"
"canonical": "https://flex-fields.bjanczak.com/docs/videofield"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/video-field.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "VideoField in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/video-field-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/video-field-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/video-field.webp"
"twitter:image:alt": "VideoField in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/videofield#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/videofield",
      "name": "VideoField",
      "description": "Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/videofield#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/videofield#article",
      "url": "https://flex-fields.bjanczak.com/docs/videofield",
      "headline": "VideoField",
      "description": "Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/videofield#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/video-field-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/video-field-motion.mp4#video",
      "name": "VideoField — FLEX Fields demonstration",
      "description": "A recorded demonstration of VideoField in the FLEX Fields playground for Filament. Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/video-field.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT6.717S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/video-field-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/videofield#article"
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
  src="/art/previews/video-field-motion.mp4"
  poster="/art/previews/video-field.webp"
  width="1920"
  height="1080"
  aria-label="VideoField — FLEX Fields demonstration"
>
  <a href="/art/previews/video-field-motion.mp4">Watch the VideoField demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField` |
| **State type** | `string\|null` — video URL or YouTube/Vimeo link |
| **Model cast** | `'video_url' => 'string'` |
| **FieldType** | `video` |
| **Playground** | `video-field` slug in Flex Fields playground |

---

### Basic usage

#### HTML5 video with poster

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;

VideoField::make('tutorial')
    ->src('https://cdn.example.com/tutorial.mp4')
    ->poster('/images/tutorial-poster.jpg')
    ->title('Getting Started')
    ->subtitle('Learn the basics in 5 minutes')
    ->skipSeconds(15)
    ->pictureInPictureable()
    ->compactControls();
```

#### YouTube embed from state

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\VideoField;

VideoField::make('promo_video')
    ->label('Promo Video')
    ->default('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    ->ratio('16:9')
    ->youtubeNoCookie()
    ->autoHideControls();
```

---

### State & validation

#### Stored value

State string = direct video URL or YouTube/Vimeo URL.

```php
$record->video_url; // string|null — e.g. "https://www.youtube.com/watch?v=..."
```

#### Validation rules

Built-in validation rejects malformed values, unsafe schemes, credentials in URLs and protocol-relative URLs. It checks the URL format; it does not verify that a remote file exists or that the browser can decode it.

| Rule | Detail |
|------|--------|
| `nullable` | State may be empty |
| `string` | State must be string when present |
| Safe video URL | HTTP(S) URLs without credentials or root-relative paths beginning with one `/` |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `src(string\|Closure\|null $src)` | Setup | `null` | Static video source (overrides state) |
| `placeholder(string\|Closure\|null $poster)` | Setup | `null` | Alias for `poster()`, including the empty-state image |
| `poster(string\|Closure\|null $poster)` | Setup | `null` | Video poster image URL |
| `title(string\|Closure\|null $title)` | Setup | `null` | Overlay title |
| `subtitle(string\|Closure\|null $subtitle)` | Setup | `null` | Overlay subtitle |
| `ratio(string\|Closure\|null $ratio)` | Setup | `'16:9'` | Aspect ratio (e.g. `16:9`, `4:3`, `1:1`) |
| `fullWidth(bool\|Closure $condition = true)` | Setup | `false` | Expand player to fill container width |
| `controls(bool\|Closure $condition = true)` | Setup | `true` | Show playback controls |
| `nativeControls(bool\|Closure $condition = true)` | Setup | `false` | Use browser native controls |
| `autoplay(bool\|Closure $condition = true)` | Setup | `false` | Start playback automatically |
| `loop(bool\|Closure $condition = true)` | Setup | `false` | Restart video when finished |
| `muted(bool\|Closure $condition = true)` | Setup | `false` | Start playback muted |
| `playsInline(bool\|Closure $condition = true)` | Setup | `true` | Play inline on mobile devices |
| `skipSeconds(int\|Closure $seconds)` | Setup | `10` | Seconds to skip forward/backward |
| `fullscreenable(bool\|Closure $condition = true)` | Setup | `true` | Enable fullscreen mode |
| `autoHideControls(bool\|Closure $condition = true)` | Setup | `true` | Hide controls when inactive |
| `pictureInPictureable(bool\|Closure $condition = true)` | Setup | `false` | Enable Picture-in-Picture mode |
| `volumeControl(bool\|Closure $condition = true)` | Setup | `true` | Show volume slider |
| `allowYoutube(bool\|Closure $condition = true)` | Setup | `true` | Enable YouTube support |
| `allowVimeo(bool\|Closure $condition = true)` | Setup | `true` | Enable Vimeo support |
| `youtubeNoCookie(bool\|Closure $condition = true)` | Setup | `true` | Use youtube-nocookie.com |
| `controlsLayout(string\|Closure $layout)` | Setup | `'default'` | `default` or `compact` |
| `playIcon(...)` | Setup | `flexvideojs-play` | Custom play icon |
| `pauseIcon(...)` | Setup | `flexvideojs-pause` | Custom pause icon |
| `volumeIcon(...)` | Setup | `flexvideojs-volume-high` | Custom volume icon |
| `muteIcon(...)` | Setup | `flexvideojs-volume-off` | Custom mute icon |
| `fullscreenIcon(...)` | Setup | `flexvideojs-fullscreen-enter` | Custom fullscreen icon |
| `placeholderIcon(...)` | Setup | `Video` | Custom placeholder icon |

#### `ratio()`

Supports standard aspect ratios. Invalid strings throw an `InvalidArgumentException`.

```php
VideoField::make('video')->ratio('4:3');
VideoField::make('video')->ratio('1:1');
```

#### `controlsLayout()`

Use `compact` for the Video.js-style control layout. Players at least 512px wide show one bottom bar; narrower players place playback, volume, seeking and settings in a bottom bar, with PiP/fullscreen and remote playback in a separate upper corner group. Below 672px the inset is 8px; larger players use 12px. Time labels hide when their section becomes narrower than 256px. Click the right time value to switch between remaining time and total duration:

```php
VideoField::make('video')->compactControls();
```

On a direct HTML5 video, move the pointer along the seek bar to preview a frame
from that time without changing playback. The bar keeps its height while you
hover or drag; the thumb and preview animate independently. Frame previews load
on demand, and their first appearance may wait for the video host to deliver
the requested part of the file. YouTube and Vimeo embeds retain their provider's
preview capabilities.


---

### Real-world examples

#### Background video (Hero)

```php
VideoField::make('hero_video')
    ->src('/videos/hero.mp4')
    ->autoplay()
    ->loop()
    ->muted()
    ->controls(false)
    ->fullWidth();
```

#### YouTube training video

```php
VideoField::make('training')
    ->label('Internal Training')
    ->allowYoutube()
    ->youtubeNoCookie()
    ->autoHideControls()
    ->pictureInPictureable();
```

---

### Playground

`/admin/flex-fields/video-field`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [AudioField](/docs/audiofield) | For audio-only playback |
| [FlexFileUpload](/docs/flexfileupload-and-fleximageupload) | To allow users to upload video files |
| [LinkPreviewField](/docs/link-preview-field) | For simple link previews with thumbnails |

---

### Player appearance

| Class | Role |
|-------|------|
| `fff-video-field-field` | Root wrapper |
| `fff-video-field-field--{sm\|md\|lg}` | Size modifier |
| `fff-video-field__frame` | Video area |
| `fff-video-field__toolbar` | Control bar |
| `is-controls-compact` | Compact layout modifier |
| `fff-video-field__meta` | Title/subtitle overlay |

---

### Performance

| Mechanism | What it does |
|-----------|--------------|
| **Lazy Loading** | Video CSS and modules load when the field’s tab, section or modal becomes active; scrolling below the fold does not deactivate the player |
| **YouTube Facade** | Shows a thumbnail/poster until the user clicks play to save bandwidth |
| **Safe URLs** | Automatic sanitization of media URLs for security |

### Settings, playback speed and quality

The settings menu is shown when it has at least two playback speeds or two direct-video sources to choose from. It slides between views, animates its dimensions and follows pointer or keyboard focus with a moving highlight. Reduced-motion preferences are respected.

```php
VideoField::make('lesson')
    ->src('/videos/lesson-720.mp4')
    ->poster('/images/lesson.jpg')
    ->settingsMenu()
    ->playbackRates([0.5, 0.75, 1, 1.25, 1.5, 2])
    ->qualityOptions([
        [
            'key' => '720',
            'label' => '720p',
            'src' => '/videos/lesson-720.mp4',
            'height' => 720,
            'default' => true,
        ],
        [
            'key' => '1080',
            'label' => '1080p',
            'src' => '/videos/lesson-1080.mp4',
            'height' => 1080,
        ],
    ]);
```

| Method | Default | Use |
|---|---|---|
| `settingsMenu(bool\|Closure)` | `true` | Show the gear menu when choices are available. Use `false` to hide it. |
| `playbackRates(array\|bool\|Closure)` | Disabled | Supply positive speeds, use `true` for 0.25–2× presets, or `false` to hide speed selection. Duplicates are removed and rates are sorted. |
| `qualityOptions(array\|Closure)` | `[]` | Supply direct-video sources. Each entry needs `src`; `key`, `label`, `height` and `default` are optional. Keys must be unique and at most one entry may be the default. |
| `tooltips(bool\|Closure)` | `true` | Show control labels for mouse users. |
| `castable(bool\|Closure)` | `false` | Enable browser Remote Playback for direct video when available. |
| `airPlayable(bool\|Closure)` | `false` | Enable Safari AirPlay when the device supports it. |

Speed and quality lists accept at most 64 entries. Playback speeds must be finite and greater than zero. Quality sources must be safe HTTP(S) URLs or paths starting with a single `/`. A quality switch preserves playback position and speed when the new source is ready. Use equivalent versions of the same recording. The source configured with `src()` is played initially; use the matching `default` entry for its initial quality label.

YouTube playback speeds are limited to the speeds supported by that video. Custom quality selection applies to direct video; Vimeo uses its embedded player controls. Casting, fullscreen, Picture-in-Picture and autoplay also depend on the browser, device and user permissions.

### Keyboard and touch controls

Focus the player to use Space/K for play and pause, M for mute, F for fullscreen, I for Picture-in-Picture, arrow keys for five-second seeking or volume adjustment, and Escape to dismiss menus. Editable fields retain their normal keyboard behavior.

In settings, Up/Down and Home/End move focus, Enter selects an option, and Escape closes the menu and returns focus to its trigger. Submenu navigation follows the page's writing direction. On touch devices, double-tap the left or right side to skip by `skipSeconds()`; double-tap the center for fullscreen.

### Size, corners and icons

Use `size('sm')`, `size('md')` or `size('lg')` to change control dimensions. `rounding()` accepts the shared FLEX rounding values. Set `fullWidth()` to fill the available column and `ratio()` to preserve a chosen aspect ratio.

The custom player uses the original Video.js SVG icons, bundled locally and rendered inline. Volume has high, low and muted states; fullscreen, Picture-in-Picture and remote playback have separate entry and exit icons. No external icon request is needed.

Icon methods accept an icon identifier, enum, renderable icon or closure: `playIcon()`, `pauseIcon()`, `volumeIcon()`, `volumeLowIcon()`, `muteIcon()`, `fullscreenIcon()`, `exitFullscreenIcon()`, `placeholderIcon()`, `settingsIcon()`, `speedIcon()`, `qualityIcon()`, `castIcon()`, `exitCastIcon()`, `airPlayIcon()`, `exitAirPlayIcon()`, `pictureInPictureIcon()` and `exitPictureInPictureIcon()`.


If you override `volumeIcon()`, `castIcon()` or `airPlayIcon()`, that icon also applies to the corresponding low-volume or connected state unless you supply a separate state icon:

```php
VideoField::make('video')
    ->volumeIcon('heroicon-o-speaker-wave')
    ->volumeLowIcon('flexvideojs-volume-low')
    ->castIcon('flexvideojs-cast-enter')
    ->exitCastIcon('flexvideojs-cast-exit')
    ->airPlayIcon('flexvideojs-airplay-enter')
    ->exitAirPlayIcon('flexvideojs-airplay-exit');
```


## Efficient frame previews

By default, native video previews seek a separate video decoder only when you
move along the progress bar. For large videos, supply either a lightweight
preview video or pre-generated thumbnail images. The player does not generate
these files: prepare them in your media-processing workflow.

### Lightweight preview video

Use the same timeline as the original film, with a lower resolution and bitrate:

```php
VideoField::make('lesson')
    ->src('/videos/lesson-1080p.mp4')
    ->previewSource('/videos/lesson-preview-240p.mp4')
    ->compactControls();
```

### Individual thumbnails

`start` is inclusive and `end` is exclusive, in seconds. Entries are sorted by
start time and must not overlap. Only the image for the hovered time loads:

```php
VideoField::make('lesson')
    ->src('/videos/lesson.mp4')
    ->previewThumbnails([
        ['start' => 0, 'end' => 5, 'src' => '/previews/lesson-000.jpg'],
        ['start' => 5, 'end' => 10, 'src' => '/previews/lesson-005.jpg'],
        ['start' => 10, 'end' => 15, 'src' => '/previews/lesson-010.jpg'],
    ]);
```

### Storyboard sprite

Several frames may share one image. Add the pixel coordinates of each frame:

```php
VideoField::make('lesson')
    ->src('/videos/lesson.mp4')
    ->previewThumbnails([
        [
            'start' => 0,
            'end' => 5,
            'src' => '/previews/lesson-storyboard.jpg',
            'x' => 0,
            'y' => 0,
            'width' => 192,
            'height' => 108,
        ],
        [
            'start' => 5,
            'end' => 10,
            'src' => '/previews/lesson-storyboard.jpg',
            'x' => 192,
            'y' => 0,
            'width' => 192,
            'height' => 108,
        ],
    ]);
```

| Option | Default | Meaning |
| --- | --- | --- |
| `previewSource(string\|Closure\|null)` | `null` | Video used only for frame previews; otherwise the original video is used. |
| `previewThumbnails(array\|Closure)` | `[]` | Up to 2,048 nonoverlapping frame intervals. Takes priority over video decoding for covered times. |
| `start`, `end` | Required | Finite, nonnegative seconds; end must be greater than start. |
| `src` | Required | Supported local or HTTP(S) image URL. |
| `x`, `y` | `0` | Nonnegative integer crop origin, at most 16,384. |
| `width`, `height` | Full image | Both required when cropping; positive integers at most 16,384. The crop must fit inside the image. |

The image cache holds at most eight images and eight megapixels in total. Images
that exceed the budget, fail to load, or leave gaps in the timeline fall back to
`previewSource()` or the original video. Keep storyboard sheets small; splitting
long timelines into multiple sheets limits decoded memory. A successful image
preview does not start the secondary video decoder. Frame images are cached,
and moving within the same frame interval does not repaint it.

For JSON custom fields, use `preview_source` and `preview_thumbnails` with the
same values. URL checks, interval ordering, overlap checks and crop validation
also apply to stored configuration. PHP closures are evaluated for the current
field; stored configuration contains data only.

Paused players do not run a playback clock. While custom controls are hidden,
the clock uses a 500ms timer; revealing them resumes animation-frame updates.
Hidden browser tabs and inactive form surfaces suspend that clock.
