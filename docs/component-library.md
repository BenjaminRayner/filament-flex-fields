---
title: "Components"
sidebarTitle: "Component library"
description: "Explore the complete FLEX Fields component library for Filament 5: form controls, layouts, table columns, infolist entries and actions, with real previews and PHP guides."
icon: "blocks"
mode: "wide"
canonical: "https://flex-fields.bjanczak.com/docs/component-library"
hideFooterPagination: true
---

<div className="fff-library">
  <p className="fff-library-intro">Form controls, layouts, table columns, infolist entries and actions for Filament. Browse component families, including controls without a recorded preview. Each component family has one card; its adapters, table columns and infolist variants are included in the same guide. Media OS rendering helpers and custom composition guides live in their own sections.</p>
  <Accordion title="Watch FLEX Fields v4 in action · 4:26" icon="play">
    <p>A recorded tour of real FLEX components for Filament 5: pickers, uploads, image editing, maps, signatures and more.</p>
    <div style={{ position: 'relative', width: '100%', aspectRatio: '16 / 9' }}>
      <iframe
        style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', border: 0, borderRadius: '12px' }}
        src="https://www.youtube-nocookie.com/embed/BAV82Qg7-W4"
        title="FLEX Fields v4 — component showcase for Filament 5"
        loading="lazy"
        referrerPolicy="strict-origin-when-cross-origin"
        allow="encrypted-media; picture-in-picture; fullscreen"
        allowFullScreen
      ></iframe>
    </div>
    <p><a href="https://www.youtube.com/watch?v=BAV82Qg7-W4" target="_blank" rel="noopener noreferrer">Watch on YouTube</a></p>
  </Accordion>
  <div className="fff-library-index" role="navigation" aria-label="Component categories">
    <a href="#text-input">Text &amp; Input<span>9</span></a>
    <a href="#choice-selection">Choice &amp; Selection<span>20</span></a>
    <a href="#number-range">Number &amp; Range<span>7</span></a>
    <a href="#date-time">Date &amp; Time<span>7</span></a>
    <a href="#media-files">Media &amp; Files<span>7</span></a>
    <a href="#color">Color<span>2</span></a>
    <a href="#maps-location">Maps &amp; Location<span>8</span></a>
    <a href="#layout-display">Layout &amp; Display<span>9</span></a>
    <a href="#actions">Actions<span>1</span></a>
  </div>
  <section className="fff-library-section" aria-labelledby="text-input">
    <div className="fff-library-heading"><h2 id="text-input">Text &amp; Input</h2><span>9</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/currencyfield" data-component="CurrencyField" data-components="CurrencyField">
        <span className="fff-library-preview"><img src="/art/catalog/currency-field.webp" alt="Currency in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Currency</strong><span>Revolut-style currency input with locale-aware formatting, digit animations, optional currency picker, and minor-unit storage.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/calculator-field" data-component="CalculatorField" data-components="CalculatorField">
        <span className="fff-library-preview"><img src="/art/catalog/calculator-field.webp" alt="Calculator in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Calculator</strong><span>Numeric input with a shared iOS-style calculator panel, per-field session memory, and full numeric constraints.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flextextareafield" data-component="FlexTextareaField" data-components="FlexTextareaField">
        <span className="fff-library-preview"><img src="/art/catalog/flex-textarea.webp" alt="Textarea in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Textarea</strong><span>Smart auto-sizing textarea with character counters, speech dictation, and emoji support.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flex-rich-editor" data-component="FlexRichEditor" data-components="FlexRichEditor">
        <span className="fff-library-preview"><img src="/art/catalog/flex-rich-editor.webp" alt="Rich Editor in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Rich Editor</strong><span>Rich document editing, formatting, media and extensible content blocks.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flextextinput" data-component="FlexTextInput" data-components="FlexTextInput">
        <span className="fff-library-preview"><img src="/art/catalog/flex-text-input-emoji.webp" alt="Text Input in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Text Input</strong><span>Versatile text inputs with shared styles, validation, sizes and visual variants.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/slugfield-and-titleslugfield" data-component="SlugField" data-components="SlugField TitleSlugField">
        <span className="fff-library-preview"><img src="/art/catalog/slug-field.webp" alt="Title &amp; Slug in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Title &amp; Slug</strong><span>Generate and edit URL slugs, independently or together with a title.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/creditcardfield" data-component="CreditCardField" data-components="CreditCardField">
        <span className="fff-library-preview"><img src="/art/catalog/credit-card.webp" alt="Credit Card in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Credit Card</strong><span>Credit card input with live brand detection, interactive preview and server-side Luhn validation.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/social-links-field" data-component="SocialLinksField" data-components="SocialLinksField">
        <span className="fff-library-preview"><img src="/art/catalog/social-links-field.webp" alt="Social Links in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Social Links</strong><span>Build a set of social links with platform icons and URL validation.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flexverificationcode" data-component="FlexVerificationCode" data-components="FlexVerificationCode">
        <span className="fff-library-preview"><img src="/art/catalog/verification-code.webp" alt="Verification Code in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Verification Code</strong><span>OTP / verification code input with grouped digit boxes, paste support, and optional account-verify chrome.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="choice-selection">
    <div className="fff-library-heading"><h2 id="choice-selection">Choice &amp; Selection</h2><span>20</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/phonefield" data-component="PhoneField" data-components="PhoneField">
        <span className="fff-library-preview"><img src="/art/catalog/phone-field.webp" alt="Phone in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Phone</strong><span>International phone numbers with searchable country codes and validation.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/countryfield" data-component="CountryField" data-components="CountryField">
        <span className="fff-library-preview"><img src="/art/catalog/country-field.webp" alt="Country in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Country</strong><span>Search countries and territories with local flags; store a two-letter country code.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/font-select" data-component="FontSelect" data-components="FontSelect">
        <span className="fff-library-preview"><img src="/art/catalog/font-select.webp" alt="Font Select in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Font Select</strong><span>Search fonts with live previews, category filters and virtualized infinite scrolling.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/timezonefield" data-component="TimezoneField" data-components="TimezoneField">
        <span className="fff-library-preview"><img src="/art/catalog/timezone-field.webp" alt="Timezone in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Timezone</strong><span>Search world time zones with local offsets and browser defaults.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/choicecards" data-component="ChoiceCards" data-components="ChoiceCards">
        <span className="fff-library-preview"><img src="/art/catalog/choice-cards.webp" alt="Choice Cards in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Choice Cards</strong><span>Present selectable cards with icons, labels, descriptions and checked states.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/imagechoicecards" data-component="ImageChoiceCards" data-components="ImageChoiceCards">
        <span className="fff-library-preview"><img src="/art/catalog/image-choice-cards.webp" alt="Image Choice Cards in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Image Choice Cards</strong><span>Select visual options with image cards, captions and consistent checked states.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/choicecheckboxcards" data-component="ChoiceCheckboxCards" data-components="ChoiceCheckboxCards">
        <span className="fff-library-preview"><img src="/art/catalog/choice-checkbox-cards.webp" alt="Choice Checkbox Cards in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Choice Checkbox Cards</strong><span>Multiple-choice cards using the shared animated selection indicator.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/segmentcontrol" data-component="SegmentControl" data-components="SegmentControl">
        <span className="fff-library-preview"><img src="/art/catalog/segment-control.webp" alt="Segment Control in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Segment Control</strong><span>Single-select segmented control with sliding indicators, icons, and animated label expansion.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/switchfield" data-component="SwitchField" data-components="SwitchField">
        <span className="fff-library-preview"><img src="/art/catalog/switch.webp" alt="Switch in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Switch</strong><span>Boolean toggle with row and card layouts, badges, and ripple effects.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/selectfield" data-component="SelectField" data-components="SelectField">
        <span className="fff-library-preview"><img src="/art/catalog/select-field.webp" alt="Select in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Select</strong><span>Rich selection with search, grouped options, virtual lists and remote pagination.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/userselect" data-component="UserSelect" data-components="UserSelect UserColumn">
        <span className="fff-library-preview"><img src="/art/catalog/user-select.webp" alt="User Select in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>User Select</strong><span>Choose people with avatars, names, secondary details and multiple selection.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/duallistboxfield" data-component="DualListboxField" data-components="DualListboxField">
        <span className="fff-library-preview"><img src="/art/catalog/dual-listbox.webp" alt="Dual Listbox in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Dual Listbox</strong><span>Dual-pane selection tool with search, reordering, and virtual scrolling.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/bubblechoicefield" data-component="BubbleChoiceField" data-components="BubbleChoiceField">
        <span className="fff-library-preview"><img src="/art/catalog/bubble-choice.webp" alt="Bubble Choice in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Bubble Choice</strong><span>Bubble multi-select with center magnification, fringe shrink on pan, and optional scalloped selection.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/icon-picker-field" data-component="IconPickerField" data-components="IconPickerField IconColumn">
        <span className="fff-library-preview"><img src="/art/catalog/icon-picker-field.webp" alt="Icon Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Icon Picker</strong><span>Browse icons with search, list or grid layouts, virtual rendering and pagination.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flexchecklist" data-component="FlexChecklist" data-components="FlexChecklist">
        <span className="fff-library-preview"><img src="/art/catalog/flex-checklist.webp" alt="Checklist in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Checklist</strong><span>Accessible single or multiple choices with configurable list presentations.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/todolistfield" data-component="TodoListField" data-components="TodoListField">
        <span className="fff-library-preview"><img src="/art/catalog/todo-list-field.webp" alt="Todo List in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Todo List</strong><span>Interactive task lists with completion, ordering and configurable feedback.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flexradiolist" data-component="FlexRadiolist" data-components="FlexRadiolist">
        <span className="fff-library-preview"><img src="/art/catalog/flex-radiolist.webp" alt="Radiolist in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Radiolist</strong><span>Enhanced radio list with icons, descriptions, and custom sizing.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flex-matrix-table" data-component="FlexMatrixTable" data-components="FlexMatrixTable">
        <span className="fff-library-preview"><img src="/art/catalog/flex-matrix-table.webp" alt="Matrix Table in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Matrix Table</strong><span>Edit structured choices in rows and columns with shared form controls.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/matrixchoicefield" data-component="MatrixChoiceField" data-components="MatrixChoiceField">
        <span className="fff-library-preview"><img src="/art/catalog/matrix-choice.webp" alt="Matrix Choice in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Matrix Choice</strong><span>Explore matrix choice with configurable behavior, styling and live examples.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/tags-field" data-component="TagsField" data-components="TagsField FlexSpatieTagsField">
        <span className="fff-library-preview"><img src="/art/catalog/tags-field.webp" alt="Tags in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Tags</strong><span>Search, select and create tags, with an optional Spatie Tags adapter.</span><span className="fff-library-badges"><span>Spatie optional</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="number-range">
    <div className="fff-library-heading"><h2 id="number-range">Number &amp; Range</h2><span>7</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/numberstepper" data-component="NumberStepper" data-components="NumberStepper">
        <span className="fff-library-preview"><img src="/art/catalog/number-stepper.webp" alt="Number Stepper in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Number Stepper</strong><span>Pill-shaped numeric control with − / + buttons and an animated NumberFlow display.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/nps-field" data-component="NpsField" data-components="NpsField">
        <span className="fff-library-preview"><img src="/art/catalog/nps-field.webp" alt="NPS in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>NPS</strong><span>NPS, CSAT, and Likert scale inputs with three visual variants.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/trackslider" data-component="TrackSlider" data-components="TrackSlider">
        <span className="fff-library-preview"><img src="/art/catalog/track-slider.webp" alt="Track Slider in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Track Slider</strong><span>Minimalist track-based range slider with optional live value output.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flexslider" data-component="FlexSlider" data-components="FlexSlider">
        <span className="fff-library-preview"><img src="/art/catalog/flex-slider.webp" alt="Slider in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Slider</strong><span>Pill and circular sliders with elastic motion, rolling numbers, magnetic marks and validated ranges.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/trafficsplit" data-component="TrafficSplit" data-components="TrafficSplit">
        <span className="fff-library-preview"><img src="/art/catalog/traffic-split.webp" alt="Traffic Split in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Traffic Split</strong><span>Interactive percentage-based weight distributor for A/B testing and traffic allocation.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/ratingfield" data-component="RatingField" data-components="RatingField RatingColumn">
        <span className="fff-library-preview"><img src="/art/catalog/rating.webp" alt="Rating in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Rating</strong><span>Capture ratings with custom symbols, precision, labels and sizes.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/pricerangefield" data-component="PriceRangeField" data-components="PriceRangeField">
        <span className="fff-library-preview"><img src="/art/catalog/price-range.webp" alt="Price Range in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Price Range</strong><span>Dual-handle price range slider with histogram backdrop, min/max numeric inputs, and currency prefix.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="date-time">
    <div className="fff-library-heading"><h2 id="date-time">Date &amp; Time</h2><span>7</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/date-periods-and-duration" data-component="FlexDurationField" data-components="FlexDurationField">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/grid-view.svg)', WebkitMaskImage: 'url(/art/icons/grid-view.svg)'}} aria-hidden="true"></span><code>FlexDurationField</code></span>
        <span className="fff-library-copy"><strong>Duration</strong><span>Enter elapsed time, including durations beyond 24 hours.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/date-periods-and-duration" data-component="FlexMonthPicker" data-components="FlexMonthPicker">
        <span className="fff-library-preview"><img src="/art/catalog/date-periods.webp" alt="Month Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Month Picker</strong><span>Choose a calendar month and year.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/date-periods-and-duration" data-component="FlexYearPicker" data-components="FlexYearPicker">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/grid-view.svg)', WebkitMaskImage: 'url(/art/icons/grid-view.svg)'}} aria-hidden="true"></span><code>FlexYearPicker</code></span>
        <span className="fff-library-copy"><strong>Year Picker</strong><span>Select a year with configurable bounds.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/date-picker" data-component="FlexDatePicker" data-components="FlexDateField FlexDatePicker FlexDateTimePicker">
        <span className="fff-library-preview"><img src="/art/catalog/date-picker.webp" alt="Date &amp; Time Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Date &amp; Time Picker</strong><span>Choose dates and times or use guided entry without a calendar popup.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/date-range-picker" data-component="FlexDateRangeField" data-components="FlexDateRangeField">
        <span className="fff-library-preview"><img src="/art/catalog/date-range-picker.webp" alt="Date Range in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Date Range</strong><span>Date ranges with custom shortcuts and optional start/end times.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/time-picker" data-component="FlexTimeField" data-components="FlexTimeField FlexTimeRangeField FlexTimeSegmentsField">
        <span className="fff-library-preview"><img src="/art/catalog/time-picker.webp" alt="Time Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Time Picker</strong><span>Clock inputs, segmented entry and start/end time ranges.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/schedule-field" data-component="ScheduleField" data-components="ScheduleField ScheduleColumn ScheduleEntry">
        <span className="fff-library-preview"><img src="/art/catalog/schedule-field.webp" alt="Schedule in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Schedule</strong><span>Weekly availability, time ranges, breaks and time-zone aware configuration.</span><span className="fff-library-badges"><span>Table column</span><span>Infolist</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="media-files">
    <div className="fff-library-heading"><h2 id="media-files">Media &amp; Files</h2><span>7</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/flexfileupload-and-fleximageupload" data-component="FlexFileUpload" data-components="FlexFileUpload FlexImageUpload FlexSpatieMediaLibraryFileUpload">
        <span className="fff-library-preview"><img src="/art/catalog/file-upload-list.webp" alt="File Upload in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>File Upload</strong><span>Upload files and images as lists, grids or avatars. Optional Spatie Media Library storage.</span><span className="fff-library-badges"><span>Spatie optional</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/videofield" data-component="VideoField" data-components="VideoField">
        <span className="fff-library-preview"><img src="/art/catalog/video-field.webp" alt="Video in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Video</strong><span>Custom video player with optional YouTube embed, skip controls, PiP, fullscreen, and compact control layout.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/audiofield" data-component="AudioField" data-components="AudioField">
        <span className="fff-library-preview"><img src="/art/catalog/audio-field.webp" alt="Audio in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Audio</strong><span>Compact audio player with waveform visualization, play/pause control, optional loop, and optional client-side Whisper speech-to-text.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/voicenoterecorderfield" data-component="VoiceNoteRecorderField" data-components="VoiceNoteRecorderField VoiceNoteSpatieRecorderField">
        <span className="fff-library-preview"><img src="/art/catalog/voice-note-recorder-field.webp" alt="Voice Note Recorder in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Voice Note Recorder</strong><span>Record and upload voice notes through Media OS, with optional Spatie storage.</span><span className="fff-library-badges"><span>Spatie optional</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/link-preview-field" data-component="LinkPreviewField" data-components="LinkPreviewField">
        <span className="fff-library-preview"><img src="/art/catalog/link-preview-field.webp" alt="Link Preview in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Link Preview</strong><span>Explore link preview with configurable behavior, styling and live examples.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/barcode-scanner-field" data-component="BarcodeScannerField" data-components="BarcodeScannerField">
        <span className="fff-library-preview"><img src="/art/catalog/barcode-scanner-field.webp" alt="Barcode Scanner in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Barcode Scanner</strong><span>Explore barcode scanner with configurable behavior, styling and live examples.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/signaturefield" data-component="SignatureField" data-components="SignatureField SignaturePreviewColumn">
        <span className="fff-library-preview"><img src="/art/catalog/signature-field.webp" alt="Signature in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Signature</strong><span>Explore signature with configurable behavior, styling and live examples.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="color">
    <div className="fff-library-heading"><h2 id="color">Color</h2><span>2</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/colorswatchfield" data-component="ColorSwatchField" data-components="ColorSwatchField">
        <span className="fff-library-preview"><img src="/art/catalog/color-swatch.webp" alt="Color Swatch in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Color Swatch</strong><span>Preset color picker: horizontal swatch pills with optional section header and tooltips.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/flexcolorpickerfield" data-component="FlexColorPickerField" data-components="FlexColorPickerField">
        <span className="fff-library-preview"><img src="/art/catalog/flex-color-picker.webp" alt="Color Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Color Picker</strong><span>Choose colors with an HSV area, channel sliders, formats and swatches.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="maps-location">
    <div className="fff-library-heading"><h2 id="maps-location">Maps &amp; Location</h2><span>8</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/mappickerfield" data-component="MapPickerField" data-components="MapPickerField MapPinColumn">
        <span className="fff-library-preview"><img src="/art/catalog/map-picker-structured.webp" alt="Map Picker in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Picker</strong><span>Search for addresses and choose a location with structured coordinates.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/country-map-field" data-component="CountryMapField" data-components="CountryMapField">
        <span className="fff-library-preview"><img src="/art/catalog/country-map-field.webp" alt="Country Map in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Country Map</strong><span>Choose countries on an interactive world map with region selection and search.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/map-zones" data-component="MapZonesField" data-components="MapZonesField">
        <span className="fff-library-preview"><img src="/art/catalog/map-zones-polygon.webp" alt="Map Zones in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Zones</strong><span>Draw and edit polygons, rectangles and circles.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/map-markers" data-component="MapMarkersField" data-components="MapMarkersField">
        <span className="fff-library-preview"><img src="/art/catalog/map-markers.webp" alt="Map Markers in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Markers</strong><span>Place, move and cluster multiple locations.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/map-editor" data-component="MapEditorField" data-components="MapEditorField">
        <span className="fff-library-preview"><img src="/art/catalog/map-editor.webp" alt="Map Editor in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Editor</strong><span>Edit points, lines and zones in one geometry field.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/map-coordinates" data-component="MapCoordinatesField" data-components="MapCoordinatesField">
        <span className="fff-library-preview"><img src="/art/catalog/map-coordinates.webp" alt="Map Coordinates in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Coordinates</strong><span>Choose coordinates without address geocoding.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/map-display" data-component="MapDisplayField" data-components="MapDisplayField">
        <span className="fff-library-preview"><img src="/art/catalog/map-display.webp" alt="Map Display in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Map Display</strong><span>Display supplied layers with tooltips and legends.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/addressautocompletefield" data-component="AddressAutocompleteField" data-components="AddressAutocompleteField">
        <span className="fff-library-preview"><img src="/art/catalog/address-autocomplete.webp" alt="Address Autocomplete in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Address Autocomplete</strong><span>Find addresses with shared search, structured results and configurable geocoding.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="layout-display">
    <div className="fff-library-heading"><h2 id="layout-display">Layout &amp; Display</h2><span>9</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/itemcard" data-component="ItemCard" data-components="ItemCard">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/grid-view.svg)', WebkitMaskImage: 'url(/art/icons/grid-view.svg)'}} aria-hidden="true"></span><code>ItemCard</code></span>
        <span className="fff-library-copy"><strong>Item Card</strong><span>Configure Item Card for your Filament application.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/itemcardstack" data-component="ItemCardStack" data-components="ItemCardStack">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/grid-view.svg)', WebkitMaskImage: 'url(/art/icons/grid-view.svg)'}} aria-hidden="true"></span><code>ItemCardStack</code></span>
        <span className="fff-library-copy"><strong>Item Card Stack</strong><span>Configure Item Card Stack for your Filament application.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/segmenttabs" data-component="SegmentTabs" data-components="SegmentTabs SegmentTab">
        <span className="fff-library-preview"><img src="/art/catalog/segment-tabs.webp" alt="Segment Tabs in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Segment Tabs</strong><span>Compose form sections with animated tabs, nested schemas and persistent selection.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/status-chip-column" data-component="StatusChipColumn" data-components="StatusChipColumn">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/label.svg)', WebkitMaskImage: 'url(/art/icons/label.svg)'}} aria-hidden="true"></span><code>StatusChipColumn</code></span>
        <span className="fff-library-copy"><strong>Status Chip Column</strong><span>Read-only table presentation with configurable appearance and empty states.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/translatablefields" data-component="TranslatableFields" data-components="TranslatableFields TranslatableTabs TranslatableTab">
        <span className="fff-library-preview"><img src="/art/catalog/translatable-fields.webp" alt="Translatable Fields in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Translatable Fields</strong><span>Build localized fields and switch between language-specific form states.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/itemcardgroup" data-component="ItemCardGroup" data-components="ItemCardGroup">
        <span className="fff-library-preview"><img src="/art/catalog/item-card-group.webp" alt="Item Card Group in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Item Card Group</strong><span>Group content and actions into configurable card layouts.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/covercard" data-component="CoverCard" data-components="CoverCard">
        <span className="fff-library-preview"><img src="/art/catalog/cover-card.webp" alt="Cover Card in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Cover Card</strong><span>Present a cover image, title and description in a static card.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/progressbar" data-component="ProgressBar" data-components="ProgressBar ProgressColumn">
        <span className="fff-library-preview fff-library-preview--icon"><span className="fff-library-glyph" style={{maskImage: 'url(/art/icons/bar-chart.svg)', WebkitMaskImage: 'url(/art/icons/bar-chart.svg)'}} aria-hidden="true"></span><code>ProgressBar</code></span>
        <span className="fff-library-copy"><strong>Progress Bar</strong><span>Present progress, targets and milestones with configurable visual states.</span><span className="fff-library-badges"><span>Table column</span></span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
      <a className="fff-library-card" href="/docs/progresscircle" data-component="ProgressCircle" data-components="ProgressCircle">
        <span className="fff-library-preview"><img src="/art/catalog/progress-circle.webp" alt="Progress Circle in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Progress Circle</strong><span>Circular progress displays with labels, colors and sizes.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <section className="fff-library-section" aria-labelledby="actions">
    <div className="fff-library-heading"><h2 id="actions">Actions</h2><span>1</span></div>
    <div className="fff-library-grid">
      <a className="fff-library-card" href="/docs/hold-confirm-action" data-component="Action" data-components="Action ActionGroup">
        <span className="fff-library-preview"><img src="/art/catalog/hold-confirm.webp" alt="Actions in the FLEX Fields playground" width="560" height="360" loading="lazy" decoding="async" /></span>
        <span className="fff-library-copy"><strong>Actions</strong><span>Configure FLEX actions, grouped menus and deliberate hold-to-confirm workflows.</span></span>
        <span className="fff-library-open" aria-hidden="true">↗</span>
      </a>
    </div>
  </section>
  <div className="fff-library-more"><strong>Build around your application.</strong><p>Connect storage and validation, translate your content or compose a reusable component. Each family guide covers its related variants and optional adapters.</p><a href="/docs/data-and-validation">Saving &amp; validation ↗</a><a href="/docs/rtl">RTL support ↗</a><a href="/docs/extensions">Extensions ↗</a><a href="/docs/media-capture-os">Media OS ↗</a></div>
</div>
