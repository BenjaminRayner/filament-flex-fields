---
title: "FlexVerificationCode"
description: OTP / verification code input with grouped digit boxes, paste support, and optional account-verify chrome.
icon: "key-round"
"canonical": "https://flex-fields.bjanczak.com/docs/flexverificationcode"
"og:type": "article"
"og:image": "https://flex-fields.bjanczak.com/art/og/verification-code.webp"
"og:image:type": "image/webp"
"og:image:width": "1200"
"og:image:height": "630"
"og:image:alt": "FlexVerificationCode in the FLEX Fields playground"
"og:video": "https://flex-fields.bjanczak.com/art/previews/verification-code-motion.mp4"
"og:video:secure_url": "https://flex-fields.bjanczak.com/art/previews/verification-code-motion.mp4"
"og:video:type": "video/mp4"
"og:video:width": "1920"
"og:video:height": "1080"
"twitter:image": "https://flex-fields.bjanczak.com/art/og/verification-code.webp"
"twitter:image:alt": "FlexVerificationCode in the FLEX Fields playground"
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
      "@id": "https://flex-fields.bjanczak.com/docs/flexverificationcode#webpage",
      "url": "https://flex-fields.bjanczak.com/docs/flexverificationcode",
      "name": "FlexVerificationCode",
      "description": "OTP / verification code input with grouped digit boxes, paste support, and optional account-verify chrome.",
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/#website"
      },
      "mainEntity": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexverificationcode#article"
      }
    },
    {
      "@type": "TechArticle",
      "@id": "https://flex-fields.bjanczak.com/docs/flexverificationcode#article",
      "url": "https://flex-fields.bjanczak.com/docs/flexverificationcode",
      "headline": "FlexVerificationCode",
      "description": "OTP / verification code input with grouped digit boxes, paste support, and optional account-verify chrome.",
      "inLanguage": "en",
      "mainEntityOfPage": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexverificationcode#webpage"
      },
      "publisher": {
        "@id": "https://flex-fields.bjanczak.com/#organization"
      },
      "video": {
        "@id": "https://flex-fields.bjanczak.com/art/previews/verification-code-motion.mp4#video"
      }
    },
    {
      "@type": "VideoObject",
      "@id": "https://flex-fields.bjanczak.com/art/previews/verification-code-motion.mp4#video",
      "name": "FlexVerificationCode — FLEX Fields demonstration",
      "description": "A recorded demonstration of FlexVerificationCode in the FLEX Fields playground for Filament. OTP / verification code input with grouped digit boxes, paste support, and optional account-verify chrome.",
      "thumbnailUrl": [
        "https://flex-fields.bjanczak.com/art/previews/verification-code.webp"
      ],
      "uploadDate": "2026-10-16T03:10:31+02:00",
      "duration": "PT3.283S",
      "contentUrl": "https://flex-fields.bjanczak.com/art/previews/verification-code-motion.mp4",
      "width": 1920,
      "height": 1080,
      "inLanguage": "en",
      "isPartOf": {
        "@id": "https://flex-fields.bjanczak.com/docs/flexverificationcode#article"
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
  src="/art/previews/verification-code-motion.mp4"
  poster="/art/previews/verification-code.webp"
  width="1920"
  height="1080"
  aria-label="FlexVerificationCode — FLEX Fields demonstration"
>
  <a href="/art/previews/verification-code-motion.mp4">Watch the FlexVerificationCode demonstration (MP4).</a>
</video>
{/* flex-component-media:end */}

[← Back to Table of Contents](/docs/index)

### Summary

OTP / verification code input with grouped digit boxes, paste support, optional auto-submit, loading indicator, and optional account-verify chrome (heading, description, footer link action).

| | |
|---|---|
| **Class** | `Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexVerificationCode` |
| **State type** | `string` — normalized code (no separators) |
| **Model cast** | `'verification_code' => 'string'` |
| **FieldType** | `verification_code` |
| **Playground** | `verification-code` slug in Flex Fields playground |

---

### Basic usage

#### Standard 6-digit OTP

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexVerificationCode;

FlexVerificationCode::make('otp')
    ->label('Verification code')
    ->length(6)
    ->groups([3, 3])
    ->groupSeparator('-')
    ->required();
```

#### Alphanumeric with auto-submit

```php
use Bjanczak\FilamentFlexFields\Filament\Forms\Components\FlexVerificationCode;

FlexVerificationCode::make('backup_code')
    ->length(8)
    ->allowedCharacters('alphanumeric')
    ->autoSubmit()
    ->autoSubmitMethod('verifyOtp')
    ->loading();
```

---

### State & validation

#### Stored value

After successful validation, state is a **normalized string** of exactly `length()` ASCII characters. An optional field may store an empty string. Leading zeroes remain intact. Separators are display-only.

```php
$record->verification_code; // string — e.g. "123456"
```

#### Validation rules

| Check | Detail |
|-------|--------|
| `string` | Rejects arrays, numbers, booleans and objects |
| `required()` | Non-empty submitted value |
| Characters | Must match `allowedCharacters` pattern (`numeric` or `alphanumeric`) |
| Length | Exactly `length()` when non-empty |

---

### Configuration API

All methods accept `Closure` unless noted.

| Method | Type | Default | Description |
|--------|------|---------|-------------|
| `length(int\|Closure $length)` | Setup | `6` | Total characters (1–16) |
| `groups(array\|Closure\|null $groups)` | Setup | One group `[length]` | List of positive integers summing to `length()` |
| `groupSizes(array\|Closure\|null $groups)` | Alias | One group | Alias of `groups()` |
| `groupSeparator(string\|Closure\|null $separator)` | Setup | `null` (gap) | Visual separator between groups |
| `allowedCharacters(string\|Closure $allowedCharacters)` | Setup | `'numeric'` | `numeric` or `alphanumeric` |
| `color(string\|Closure\|null $color)` | Setup | `'primary'` | Accent color for active digit |
| `autoSubmit(bool\|Closure $condition = true)` | Setup | `false` | Submit when all digits are filled |
| `autoSubmitMethod(string\|Closure\|null $method)` | Setup | `null` | Livewire method to call on completion |
| `submitUsing(Closure $callback)` | Setup | `null` | PHP callback to run on completion |
| `loading(bool\|Closure $condition = true)` | Setup | `false` | Show spinner during Livewire requests |
| `validating(bool\|Closure $condition = true)` | Alias | `false` | Alias of `loading()` |
| `heading(string\|Htmlable\|Closure\|null $heading)` | Setup | `null` | Title above the inputs |
| `description(string\|Htmlable\|Closure\|null $description)` | Setup | `null` | Supporting copy below the heading |
| `footer(string\|Htmlable\|Closure\|null $footer)` | Setup | `null` | Muted text before the footer action |
| `footerAction(Action\|Closure\|null $action)` | Setup | `null` | Link-style action beside the footer text |
| `size(string\|ControlSize\|Closure $size)` | Setup | `'md'` | Control size: `sm`, `md`, `lg` |
| `rounding(string\|Closure\|null $rounding)` | Setup | config | Border radius token |

#### `submitUsing()`

Run a PHP callback when the code is complete. Inject `$state`, `$livewire`, `$set`, etc.

```php
FlexVerificationCode::make('code')
    ->submitUsing(function (string $code, $livewire) {
        $livewire->verifyCode($code);
    });
```

Use either `submitUsing()` or `autoSubmitMethod()` on an instance. Configuring both
throws a configuration exception. `autoSubmit()` alone enables the feature but
requires one of those handlers. A complete value loaded from the server does not
automatically submit on initialization. PHP callbacks run only for valid complete
state changes; oversized codes and invalid suffixes are rejected before the callback.
`submitUsing()` enables live updates with a 250ms debounce unless already configured.

The Livewire method receives the string as its first argument. Validate it in that
method, check the actual OTP, enforce expiry and attempt limits, and authorize the
operation there. The field validates format; it does not issue or authenticate codes.

```php
public function verifyOtp(string $code): void
{
    validator(['code' => $code], ['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/D']])->validate();
    // Verify against your application's expiring OTP and attempt policy.
}
```

Only the submitting field becomes pending while its method request runs; other
verification fields remain editable. A rejected request restores the inputs and
shows a translated error with a **Try again** button. Enter also retries that failed
request. An unchanged completed code does not repeatedly auto-submit. Editing it
or explicitly retrying allows another request. Server validation errors use the
normal Filament field error presentation. Completing a transport request does not
itself establish that the OTP was accepted by your application.

`loading()` reserves spinner space without a layout jump. The spinner follows the
instance's method promise or Livewire updates targeted at that field's state path.

#### `footerAction()`

Register a link-style action (e.g. **Resend**):

```php
FlexVerificationCode::make('otp')
    ->footer("Didn't receive a code?")
    ->footerAction(function ($livewire): void {
        $livewire->resendCode();
    });
```

---

### Real-world examples

#### Account verification screen

```php
FlexVerificationCode::make('otp')
    ->hiddenLabel()
    ->heading('Verify your account')
    ->description("We've sent a 6-digit code to your email.")
    ->footer("Didn't receive it?")
    ->footerAction(\Filament\Actions\Action::make('resendOtp')->label('Resend')->action('resendOtp'))
    ->length(6)
    ->autoSubmitMethod('verifyOtp')
    ->loading();
```

#### 2FA backup codes

```php
FlexVerificationCode::make('backup_code')
    ->length(8)
    ->allowedCharacters('alphanumeric')
    ->groups([4, 4])
    ->groupSeparator(' ');
```

---

### Playground

`/admin/flex-fields/verification-code`

See [Playground](/docs/index#playground) for setup.

---

### Related components

| Component | When to use instead |
|-----------|---------------------|
| [FlexTextInput](/docs/flextextinput) | For standard single-line text inputs |
| [PhoneField](/docs/phonefield) | For entering phone numbers |
| [NumberStepper](/docs/numberstepper) | For incrementing/decrementing numeric values |

---

### CSS classes (reference)

| Class | Role |
|-------|------|
| `fff-verification-code` | Digit inputs root |
| `fff-verification-code__input` | Single digit cell |
| `fff-verification-code__separator` | Group separator |
| `fff-verification-code-layout` | Vertical stack for heading/footer chrome |
| `fff-verification-code-layout__heading` | Title |
| `fff-verification-code-layout__footer` | Footer row |

---

### Keyboard, autofill and RTL

- Each cell has a translated digit label and uses `inputmode="numeric"` or `text`.
- The first cell advertises `autocomplete="one-time-code"`. A complete autofilled code is distributed across all cells.
- Pasting a code replaces the whole code, strips separators and uppercases ASCII letters in alphanumeric mode.
- Typing advances immediately. Backspace clears the current digit or moves back from an empty cell; Delete clears the current cell.
- Arrow keys, Home and End move focus without scrolling the page. Modified shortcuts and IME composition remain available.
- Surrounding headings and footer actions follow RTL. The code sequence stays **LTR**, keeping numeric/alphanumeric codes in their canonical order; physical Left/Right arrows follow that sequence.
- A reset or value change from Livewire updates every cell. Dynamic disabled/length/groups configurations replace only that instance.
- All sizes keep their existing geometry; inputs shrink within narrow containers and honor the configured rounding token. Very long codes keep a readable minimum cell width and scroll inside the field; focus reveals the active cell without scrolling the page.

### Performance

| Mechanism | What it does |
|-----------|--------------|
| **Smart Paste** | Distributes pasted codes across cells instantly |
| **Auto-focus** | Automatically moves focus to the next cell after input |
| **First paint** | PHP renders digits, filled state and disabled attributes before JS initialization |
| **Bounded work** | At most 16 cells; cached nodes, synchronous input updates and no per-keystroke timers or animation frames |
| **Ownership** | Two disposable state watchers and instance-local delegated listeners; hidden tabs activate through the shared asset runtime |
