<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Enums;

use Bjanczak\FilamentFlexFields\Enums\FieldTypeDefaults\FieldTypeDefaultConfigRegistry;
use Bjanczak\FilamentFlexFields\Support\Translations;

enum FieldType: string
{
    // Text
    case SingleLineText = 'single_line_text';
    case MultiLineText = 'multi_line_text';
    case FlexTextarea = 'flex_textarea';
    case FlexTextInput = 'flex_text_input';
    case RichText = 'rich_text';
    case Markdown = 'markdown';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'phone';
    case Country = 'country';
    case Timezone = 'timezone';
    case FontSelect = 'font_select';
    case Password = 'password';
    case Slug = 'slug';
    case Search = 'search';
    case AddressAutocomplete = 'address_autocomplete';
    case VerificationCode = 'verification_code';
    case IconPicker = 'icon_picker';

    // Number
    case Integer = 'integer';
    case Decimal = 'decimal';
    case NumberStepper = 'number_stepper';
    case Currency = 'currency';
    case Percentage = 'percentage';
    case RangeSlider = 'range_slider';
    case RangeMinMax = 'range_min_max';
    case FlexSlider = 'flex_slider';
    case PriceRange = 'price_range';
    case TrafficSplit = 'traffic_split';

    // Choice
    case Toggle = 'toggle';
    case Checkbox = 'checkbox';
    case CheckboxList = 'checkbox_list';
    case Radio = 'radio';
    case SegmentControl = 'segment_control';
    case ChoiceCards = 'choice_cards';
    case ChoiceCheckboxCards = 'choice_checkbox_cards';
    case ImageChoiceCards = 'image_choice_cards';
    case FlexChecklist = 'flex_checklist';
    case FlexRadiolist = 'flex_radiolist';
    case MatrixChoice = 'matrix_choice';
    case Select = 'select';
    case UserSelect = 'user_select';
    case DualListbox = 'dual_listbox';
    case BubbleChoice = 'bubble_choice';
    case TodoList = 'todo_list';
    case Tags = 'tags';

    // Date & time
    case Date = 'date';
    case Time = 'time';
    case DateTime = 'date_time';
    case DateRange = 'date_range';
    case Duration = 'duration';
    case TimeRange = 'time_range';
    case Month = 'month';
    case Year = 'year';
    case Schedule = 'schedule';

    // Media & visual
    case Color = 'color';
    case ColorPresets = 'color_presets';
    case FlexColorPicker = 'flex_color_picker';
    case File = 'file';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case VoiceNote = 'voice_note';
    case MapPicker = 'map_picker';
    case SocialLinks = 'social_links';
    case Signature = 'signature';
    case CreditCard = 'credit_card';
    case Barcode = 'barcode';

    // Advanced
    case Rating = 'rating';
    case Nps = 'nps';
    case KeyValue = 'key_value';
    case Repeater = 'repeater';
    case Code = 'code';
    case Json = 'json';
    case Hidden = 'hidden';
    case ReadOnly = 'read_only';

    public function category(): FieldCategory
    {
        return match ($this) {
            self::SingleLineText,
            self::MultiLineText,
            self::FlexTextarea,
            self::FlexTextInput,
            self::RichText,
            self::Markdown,
            self::Email,
            self::Url,
            self::Phone,
            self::Country,
            self::Timezone,
            self::FontSelect,
            self::Password,
            self::Slug,
            self::Search,
            self::AddressAutocomplete,
            self::VerificationCode,
            self::IconPicker => FieldCategory::Text,

            self::Integer,
            self::Decimal,
            self::NumberStepper,
            self::Currency,
            self::Percentage,
            self::RangeSlider,
            self::RangeMinMax,
            self::FlexSlider,
            self::PriceRange,
            self::TrafficSplit => FieldCategory::Number,

            self::Toggle,
            self::Checkbox,
            self::CheckboxList,
            self::Radio,
            self::SegmentControl,
            self::ChoiceCards,
            self::ChoiceCheckboxCards,
            self::ImageChoiceCards,
            self::FlexChecklist,
            self::FlexRadiolist,
            self::MatrixChoice,
            self::Select,
            self::UserSelect,
            self::DualListbox,
            self::BubbleChoice,
            self::TodoList,
            self::Tags => FieldCategory::Choice,

            self::Date,
            self::Time,
            self::DateTime,
            self::DateRange,
            self::Duration,
            self::TimeRange,
            self::Month,
            self::Year,
            self::Schedule => FieldCategory::DateTime,

            self::Color,
            self::ColorPresets,
            self::FlexColorPicker,
            self::File,
            self::Image,
            self::Video,
            self::Audio,
            self::VoiceNote,
            self::MapPicker,
            self::SocialLinks,
            self::Signature,
            self::CreditCard,
            self::Barcode => FieldCategory::Media,

            self::Rating,
            self::Nps,
            self::KeyValue,
            self::Repeater,
            self::Code,
            self::Json,
            self::Hidden,
            self::ReadOnly => FieldCategory::Advanced,
        };
    }

    public function label(): string
    {
        return Translations::get("filament-flex-fields::default.field_types.{$this->value}");
    }

    public function icon(): string
    {
        return match ($this) {
            self::SingleLineText => 'hugeicons-minus-sign',
            self::MultiLineText => 'hugeicons-text-align-left',
            self::FlexTextarea => 'hugeicons-bubble-chat',
            self::FlexTextInput => 'hugeicons-text-align-left',
            self::RichText => 'hugeicons-file-01',
            self::Markdown => 'hugeicons-hashtag',
            self::Email => 'hugeicons-mail-01',
            self::Url => 'hugeicons-link-01',
            self::Phone => 'hugeicons-call-02',
            self::Country => 'hugeicons-globe-02',
            self::Timezone => 'hugeicons-clock-01',
            self::FontSelect => 'hugeicons-type-outline',
            self::Password => 'hugeicons-lock-password',
            self::Slug => 'hugeicons-at',
            self::Search => 'hugeicons-search-01',
            self::AddressAutocomplete => 'hugeicons-location-01',
            self::VerificationCode => 'hugeicons-shield-01',
            self::IconPicker => 'hugeicons-sparkles',

            self::Integer => 'hugeicons-hashtag',
            self::Decimal => 'hugeicons-function',
            self::NumberStepper => 'hugeicons-add-circle',
            self::Currency => 'hugeicons-dollar-circle',
            self::Percentage => 'hugeicons-percent',
            self::RangeSlider => 'hugeicons-sliders-horizontal',
            self::RangeMinMax => 'hugeicons-arrow-data-transfer-horizontal',
            self::FlexSlider => 'hugeicons-menu-01',
            self::PriceRange => 'hugeicons-bar-chart',
            self::TrafficSplit => 'hugeicons-segment',

            self::Toggle => 'hugeicons-power-service',
            self::Checkbox => 'hugeicons-checkmark-circle-02',
            self::CheckboxList => 'hugeicons-left-to-right-list-bullet',
            self::Radio => 'hugeicons-radio-01',
            self::SegmentControl => 'hugeicons-layout-two-column',
            self::ChoiceCards => 'hugeicons-layers-01',
            self::ChoiceCheckboxCards => 'hugeicons-add-square',
            self::ImageChoiceCards => 'hugeicons-image-01',
            self::FlexChecklist => 'hugeicons-task-01',
            self::FlexRadiolist => 'hugeicons-left-to-right-list-bullet',
            self::MatrixChoice => 'hugeicons-table-01',
            self::Select => 'hugeicons-arrow-up-down',
            self::UserSelect => 'hugeicons-user-circle',
            self::DualListbox => 'hugeicons-arrow-data-transfer-horizontal',
            self::BubbleChoice => 'hugeicons-stop-circle',
            self::TodoList => 'hugeicons-task-02',
            self::Tags => 'hugeicons-tag-01',

            self::Date => 'hugeicons-calendar-03',
            self::Time => 'hugeicons-clock-01',
            self::DateTime => 'hugeicons-calendar-03',
            self::DateRange => 'hugeicons-calendar-02',
            self::Duration => 'hugeicons-hourglass',
            self::TimeRange => 'hugeicons-arrow-data-transfer-horizontal',
            self::Month => 'hugeicons-calendar-03',
            self::Year => 'hugeicons-calendar-03',
            self::Schedule => 'hugeicons-calendar-03',

            self::Color => 'hugeicons-paint-board',
            self::ColorPresets => 'hugeicons-paint-brush-01',
            self::FlexColorPicker => 'hugeicons-droplet',
            self::File => 'hugeicons-attachment-01',
            self::Image => 'hugeicons-image-01',
            self::Video => 'hugeicons-video-01',
            self::Audio => 'hugeicons-volume-high',
            self::VoiceNote => 'hugeicons-mic-01',
            self::MapPicker => 'hugeicons-location-01',
            self::SocialLinks => 'hugeicons-share-01',
            self::Signature => 'hugeicons-edit-02',
            self::CreditCard => 'hugeicons-credit-card',
            self::Barcode => 'hugeicons-qr-code',

            self::Rating => 'hugeicons-star',
            self::Nps => 'hugeicons-bar-chart',
            self::KeyValue => 'hugeicons-table-01',
            self::Repeater => 'hugeicons-layers-01',
            self::Code => 'hugeicons-code',
            self::Json => 'hugeicons-code-square',
            self::Hidden => 'hugeicons-eye-off',
            self::ReadOnly => 'hugeicons-eye',
        };
    }

    public function isCustomComponent(): bool
    {
        return in_array($this, [
            self::NumberStepper,
            self::SegmentControl,
            self::ChoiceCards,
            self::ChoiceCheckboxCards,
            self::ImageChoiceCards,
            self::FlexChecklist,
            self::FlexRadiolist,
            self::MatrixChoice,
            self::RangeSlider,
            self::FlexSlider,
            self::Toggle,
            self::TrafficSplit,
            self::Color,
            self::ColorPresets,
            self::FlexColorPicker,
            self::DualListbox,
            self::BubbleChoice,
            self::TodoList,
            self::Tags,
            self::PriceRange,
            self::FlexTextarea,
            self::FlexTextInput,
            self::VerificationCode,
            self::IconPicker,
            self::CreditCard,
            self::Barcode,
            self::Phone,
            self::Country,
            self::Timezone,
            self::FontSelect,
            self::AddressAutocomplete,
            self::Currency,
            self::Video,
            self::Audio,
            self::VoiceNote,
            self::MapPicker,
            self::SocialLinks,
            self::UserSelect,
            self::Slug,
            self::Date,
            self::Time,
            self::DateTime,
            self::DateRange,
            self::Duration,
            self::TimeRange,
            self::Month,
            self::Year,
            self::Schedule,
            self::Nps,
            self::Rating,
        ], true);
    }

    /**
     * Lazy Flex Fields CSS/JS component ids used when this field type is rendered
     * (including shared dependency roots such as phone-field → flex-text-input).
     *
     * Empty list = native Filament control with no flex-fields lazy bundle.
     * Expand deps via FlexFieldAssets::stylesheetsFor() / alpineChunksFor().
     *
     * @return list<string>
     */
    public function assetComponents(): array
    {
        return match ($this) {
            self::Phone => ['phone-field'],
            self::Country => ['country-field'],
            self::Timezone => ['timezone-field'],
            self::FontSelect => ['font-select'],
            self::Slug => ['slug-field'],
            self::AddressAutocomplete => ['address-autocomplete'],
            self::VerificationCode => ['flex-verification-code'],
            self::IconPicker => ['icon-picker-field'],
            self::FlexTextInput,
            self::Email,
            self::Url,
            self::Integer,
            self::Decimal => ['flex-text-input'],
            self::FlexTextarea => ['flex-textarea'],
            self::RichText => ['rich-editor-field'],
            self::CreditCard => ['credit-card'],
            self::Barcode => ['barcode-scanner-field'],
            self::Currency => ['currency-field'],
            self::NumberStepper => ['number-stepper'],
            self::Percentage,
            self::RangeSlider,
            self::RangeMinMax => ['track-slider'],
            self::FlexSlider => ['flex-slider'],
            self::PriceRange => ['price-range'],
            self::TrafficSplit => ['traffic-split'],
            self::Toggle => ['switch'],
            self::SegmentControl => ['segment-control'],
            self::ChoiceCards,
            self::ChoiceCheckboxCards => ['choice-cards'],
            self::ImageChoiceCards => ['image-choice-cards'],
            self::FlexChecklist => ['flex-checklist'],
            self::FlexRadiolist => ['flex-radiolist'],
            self::MatrixChoice => ['matrix-choice-field'],
            self::Select => ['select-field'],
            self::UserSelect => ['user-select'],
            self::DualListbox => ['dual-listbox'],
            self::BubbleChoice => ['bubble-choice'],
            self::TodoList => ['todo-list-field'],
            self::Tags => ['tags-field'],
            self::Date,
            self::DateTime,
            self::DateRange,
            self::Month,
            self::Year => ['flex-date-picker'],
            self::Duration => ['flex-duration'],
            self::TimeRange,
            self::Time => ['flex-time-picker'],
            self::Schedule => ['schedule-field'],
            self::Color,
            self::ColorPresets => ['color-swatch'],
            self::FlexColorPicker => ['flex-color-picker'],
            self::File,
            self::Image => ['flex-file-upload'],
            self::Video => ['video-field'],
            self::Audio => ['audio-field'],
            self::VoiceNote => ['voice-note-recorder-field'],
            self::MapPicker => ['map-picker'],
            self::SocialLinks => ['social-links-field'],
            self::Signature => ['signature-field'],
            self::Rating => ['rating-field'],
            self::Nps => ['nps-field'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        return FieldTypeDefaultConfigRegistry::for($this);
    }

    public function supportsUserDefinedOptions(): bool
    {
        return match ($this) {
            self::Select,
            self::Radio,
            self::CheckboxList,
            self::SegmentControl,
            self::ChoiceCards,
            self::ChoiceCheckboxCards,
            self::ImageChoiceCards,
            self::FlexChecklist,
            self::FlexRadiolist,
            self::DualListbox,
            self::BubbleChoice,
            self::TodoList,
            self::Tags => true,
            default => false,
        };
    }

    public function requiresConfiguredOptions(): bool
    {
        return $this->supportsUserDefinedOptions() && ! $this->usesSuggestionsInsteadOfOptions();
    }

    public function usesSuggestionsInsteadOfOptions(): bool
    {
        return $this === self::Tags;
    }

    public function supportsRichFieldOptions(): bool
    {
        return match ($this) {
            self::SegmentControl,
            self::ChoiceCards,
            self::ChoiceCheckboxCards,
            self::ImageChoiceCards,
            self::FlexChecklist,
            self::FlexRadiolist,
            self::BubbleChoice,
            self::TodoList => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function forCategory(FieldCategory $category): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->category() === $category,
        ));
    }
}
