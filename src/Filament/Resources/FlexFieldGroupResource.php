<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Filament\Resources;

use Bjanczak\FilamentFlexFields\Enums\FieldType;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldSectionType;
use Bjanczak\FilamentFlexFields\Enums\FlexFieldWidth;
use Bjanczak\FilamentFlexFields\Filament\Resources\FlexFieldGroupResource\Pages;
use Bjanczak\FilamentFlexFields\Filament\Schema\FieldOptionsBuilder;
use Bjanczak\FilamentFlexFields\Filament\Schema\FieldTypeSettingsBuilder;
use Bjanczak\FilamentFlexFields\Filament\Schema\FlexFieldTargetModelSelect;
use Bjanczak\FilamentFlexFields\Filament\Schema\MatrixChoiceBuilder;
use Bjanczak\FilamentFlexFields\Filament\Schema\VisibilityRuleBuilder;
use Bjanczak\FilamentFlexFields\FilamentFlexFieldsPlugin;
use Bjanczak\FilamentFlexFields\Models\FlexFieldGroup;
use Bjanczak\FilamentFlexFields\Support\FlexFieldsConfig;
use Bjanczak\FilamentFlexFields\Support\Schema\FlexFieldEntityRegistry;
use Bjanczak\FilamentFlexFields\Support\Translations;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class FlexFieldGroupResource extends Resource
{
    protected static ?string $model = FlexFieldGroup::class;

    protected static ?string $slug = 'flex-field-groups';

    protected static \BackedEnum|string|null $navigationIcon = 'hugeicons-dashboard-square-01';

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? Translations::get('filament-flex-fields::schema-management.navigation_plural');
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? Translations::get('filament-flex-fields::schema-management.model_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? Translations::get('filament-flex-fields::schema-management.navigation_plural');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return FlexFieldsConfig::isSchemaResourceEnabled()
            && ! FlexFieldsConfig::isSchemaManagementPageEnabled();
    }

    public static function canAccess(): bool
    {
        return FlexFieldsConfig::isSchemaResourceEnabled() && parent::canAccess();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        $pluginGroup = null;

        try {
            $pluginGroup = FilamentFlexFieldsPlugin::get()->getNavigationGroup();
        } catch (\Throwable) {
            // Panel may not have the plugin when resolving labels outside a panel.
        }

        return $pluginGroup
            ?? config('filament-flex-fields.schema.navigation_group')
            ?? 'Flex Fields';
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-flex-fields.schema.navigation_sort');

        if (is_int($sort) || is_numeric($sort)) {
            return (int) $sort;
        }

        return 90;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn () => __('filament-flex-fields::schema-management.group'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(fn () => __('filament-flex-fields::schema-management.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, mixed $state, callable $set): void {
                                if ($operation !== 'create' || ! is_string($state)) {
                                    return;
                                }

                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label(fn () => __('filament-flex-fields::schema-management.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: FlexFieldGroup::class,
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                                    $tenantId = $get('tenant_id');

                                    return $rule->where('tenant_id', filled($tenantId) ? (string) $tenantId : '');
                                },
                            )
                            ->alphaDash(),

                        FlexFieldTargetModelSelect::make(),
                        FlexFieldTargetModelSelect::missingEntitiesPlaceholder(),

                        TextInput::make('order')
                            ->label(fn () => __('filament-flex-fields::schema-management.order'))
                            ->numeric()
                            ->default(0)
                            ->required(),

                        TextInput::make('tenant_id')
                            ->label(fn () => __('filament-flex-fields::schema-management.tenant_id'))
                            ->maxLength(255)
                            ->nullable()
                            ->dehydrateStateUsing(fn (?string $state): string => filled($state) ? (string) $state : '')
                            ->formatStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->helperText(fn () => __('filament-flex-fields::schema-management.tenant_help')),
                    ]),

                Section::make(fn () => __('filament-flex-fields::schema-management.sections'))
                    ->description(fn () => __('filament-flex-fields::schema-management.sections_description'))
                    ->schema([
                        Repeater::make('sections')
                            ->label(fn () => __('filament-flex-fields::schema-management.sections'))
                            ->default([])
                            ->schema([
                                TextInput::make('id')
                                    ->label(fn () => __('filament-flex-fields::schema-management.section_id'))
                                    ->required()
                                    ->alphaDash(),
                                TextInput::make('label')
                                    ->label(fn () => __('filament-flex-fields::schema-management.label'))
                                    ->required()
                                    ->maxLength(255),
                                Select::make('type')
                                    ->label(fn () => __('filament-flex-fields::schema-management.type'))
                                    ->options(fn (): array => collect(FlexFieldSectionType::cases())
                                        ->mapWithKeys(fn (FlexFieldSectionType $type): array => [$type->value => Translations::get('filament-flex-fields::schema-management.section_type_'.$type->value)])
                                        ->all())
                                    ->default(FlexFieldSectionType::Section->value)
                                    ->required()
                                    ->native(false),
                                TextInput::make('sort')
                                    ->label(fn () => __('filament-flex-fields::schema-management.sort'))
                                    ->numeric()
                                    ->default(0),
                                TextInput::make('description')
                                    ->label(fn () => __('filament-flex-fields::schema-management.description'))
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                VisibilityRuleBuilder::make(
                                    'visible_when',
                                    fn (Get $get): array => self::flexFieldSlugOptions($get, '../../fields'),
                                    fn (Get $get): array => app(FlexFieldEntityRegistry::class)
                                        ->modelAttributeOptionsFor((string) ($get('../../target_type') ?? '')),
                                )->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->reorderable()
                            ->cloneable()
                            ->columnSpanFull(),
                    ]),

                Section::make(fn () => __('filament-flex-fields::schema-management.fields_schema'))
                    ->description(fn () => __('filament-flex-fields::schema-management.fields_description'))
                    ->schema([
                        Repeater::make('fields')
                            ->label(fn () => __('filament-flex-fields::schema-management.fields'))
                            ->default([])
                            ->schema([
                                TextInput::make('slug')
                                    ->label(fn () => __('filament-flex-fields::schema-management.slug'))
                                    ->required()
                                    ->maxLength(255)
                                    ->alphaDash(),
                                TextInput::make('label')
                                    ->label(fn () => __('filament-flex-fields::schema-management.label'))
                                    ->required()
                                    ->maxLength(255),
                                Select::make('type')
                                    ->label(fn () => __('filament-flex-fields::schema-management.type'))
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->options(fn (): array => self::fieldTypeOptions()),
                                FieldOptionsBuilder::make(),
                                MatrixChoiceBuilder::rowsRepeater(),
                                MatrixChoiceBuilder::columnsRepeater(),
                                FieldTypeSettingsBuilder::section(),
                                Select::make('section_id')
                                    ->label(fn () => __('filament-flex-fields::schema-management.section'))
                                    ->options(fn (Get $get): array => collect($get('../../sections') ?? [])
                                        ->filter(fn ($section): bool => is_array($section) && filled($section['id'] ?? null))
                                        ->mapWithKeys(fn (array $section): array => [
                                            (string) $section['id'] => (string) ($section['label'] ?? $section['id']),
                                        ])
                                        ->all())
                                    ->nullable()
                                    ->native(false),
                                Select::make('width')
                                    ->label(fn () => __('filament-flex-fields::schema-management.width'))
                                    ->options(FlexFieldWidth::options())
                                    ->default(FlexFieldWidth::Full->value)
                                    ->native(false),
                                TextInput::make('sort')
                                    ->label(fn () => __('filament-flex-fields::schema-management.sort'))
                                    ->numeric()
                                    ->default(0),
                                Toggle::make('is_required')
                                    ->label(fn () => __('filament-flex-fields::schema-management.required')),
                                Toggle::make('is_encrypted')
                                    ->label(fn () => __('filament-flex-fields::schema-management.encrypted')),
                                TextInput::make('help_text')
                                    ->label(fn () => __('filament-flex-fields::schema-management.help_text'))
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                TextInput::make('placeholder')
                                    ->label(fn () => __('filament-flex-fields::schema-management.placeholder'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('formula')
                                    ->label(fn () => __('filament-flex-fields::default.schema.formula'))
                                    ->helperText(fn () => __('filament-flex-fields::default.schema.formula_help'))
                                    ->maxLength(2000)
                                    ->columnSpanFull(),
                                VisibilityRuleBuilder::make(
                                    'visible_when',
                                    fn (Get $get): array => self::flexFieldSlugOptions($get, '../../fields'),
                                    fn (Get $get): array => app(FlexFieldEntityRegistry::class)
                                        ->modelAttributeOptionsFor((string) ($get('../../target_type') ?? '')),
                                )->columnSpanFull(),
                                VisibilityRuleBuilder::make(
                                    'required_when',
                                    fn (Get $get): array => self::flexFieldSlugOptions($get, '../../fields'),
                                    fn (Get $get): array => app(FlexFieldEntityRegistry::class)
                                        ->modelAttributeOptionsFor((string) ($get('../../target_type') ?? '')),
                                )->columnSpanFull(),
                                VisibilityRuleBuilder::make(
                                    'disabled_when',
                                    fn (Get $get): array => self::flexFieldSlugOptions($get, '../../fields'),
                                    fn (Get $get): array => app(FlexFieldEntityRegistry::class)
                                        ->modelAttributeOptionsFor((string) ($get('../../target_type') ?? '')),
                                )->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['slug'] ?? null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(fn () => __('filament-flex-fields::schema-management.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(fn () => __('filament-flex-fields::schema-management.slug'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('target_type')
                    ->label(fn () => __('filament-flex-fields::default.schema.target_model'))
                    ->formatStateUsing(function (?string $state): string {
                        if (! filled($state)) {
                            return '—';
                        }

                        $entity = app(FlexFieldEntityRegistry::class)->find((string) $state);

                        return $entity?->label ?? class_basename((string) $state);
                    })
                    ->description(fn (?string $state): ?string => filled($state) ? (string) $state : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fields')
                    ->label(fn () => __('filament-flex-fields::schema-management.fields'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? (string) count($state) : '0')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('order')
                    ->label(fn () => __('filament-flex-fields::schema-management.order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant_id')
                    ->label(fn () => __('filament-flex-fields::schema-management.tenant'))
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(fn () => __('filament-flex-fields::schema-management.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFlexFieldGroups::route('/'),
            'create' => Pages\CreateFlexFieldGroup::route('/create'),
            'edit' => Pages\EditFlexFieldGroup::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function fieldTypeOptions(): array
    {
        $options = [];

        foreach (FieldType::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function flexFieldSlugOptions(Get $get, string $fieldsPath): array
    {
        return collect($get($fieldsPath) ?? [])
            ->filter(fn ($field): bool => is_array($field) && filled($field['slug'] ?? null))
            ->mapWithKeys(fn (array $field): array => [
                (string) $field['slug'] => (string) ($field['label'] ?? $field['slug']),
            ])
            ->all();
    }
}
