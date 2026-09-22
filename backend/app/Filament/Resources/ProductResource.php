<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductResource extends RestrictableResource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Core product data')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->native(false)
                            ->required(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('product_type')
                            ->maxLength(255),
                        TextInput::make('subtitle')
                            ->maxLength(255),
                        TextInput::make('franchise')
                            ->maxLength(255),
                        TextInput::make('series')
                            ->maxLength(255),
                        TextInput::make('brand')
                            ->maxLength(255),
                        TextInput::make('year')
                            ->numeric(),
                        TextInput::make('language')
                            ->maxLength(255),
                        TextInput::make('set_name')
                            ->maxLength(255),
                        TextInput::make('item_number')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('Attributes')
                    ->schema([
                        KeyValue::make('specifications')
                            ->columnSpanFull(),
                        TagsInput::make('tags')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Section::make('Media')
                    ->schema([
                        Repeater::make('media')
                            ->label('Product media rows')
                            ->default([])
                            ->schema([
                                TextInput::make('label')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                Select::make('kind')
                                    ->options([
                                        'image' => 'Image',
                                        'video' => 'Video',
                                        'file' => 'File',
                                    ])
                                    ->default('image')
                                    ->native(false)
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->url()
                                    ->maxLength(2048)
                                    ->columnSpan(3),
                                TextInput::make('path')
                                    ->label('Storage path')
                                    ->maxLength(2048)
                                    ->columnSpan(2),
                                TextInput::make('disk')
                                    ->maxLength(100)
                                    ->columnSpan(1),
                                TextInput::make('mime_type')
                                    ->maxLength(255)
                                    ->columnSpan(2),
                                TextInput::make('file_size')
                                    ->numeric()
                                    ->minValue(0)
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->addActionLabel('Add media row')
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Storefront metadata')
                    ->schema([
                        Tabs::make('MetadataTabs')
                            ->tabs([
                                Tabs\Tab::make('Greek')
                                    ->schema([
                                        TextInput::make('metadata.translations.el.short_description')
                                            ->label('Short description (EL)')
                                            ->maxLength(255),
                                        Textarea::make('metadata.translations.el.description')
                                            ->label('Description (EL)')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                        Textarea::make('metadata.translations.el.shipping_info')
                                            ->label('Shipping info (EL)')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        TextInput::make('metadata.translations.el.authenticity')
                                            ->label('Authenticity note (EL)')
                                            ->maxLength(255),
                                        TagsInput::make('metadata.translations.el.highlights')
                                            ->label('Highlights (EL)')
                                            ->separator(',')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Tabs\Tab::make('English')
                                    ->schema([
                                        TextInput::make('metadata.translations.en.short_description')
                                            ->label('Short description (EN)')
                                            ->maxLength(255),
                                        Textarea::make('metadata.translations.en.description')
                                            ->label('Description (EN)')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                        Textarea::make('metadata.translations.en.shipping_info')
                                            ->label('Shipping info (EN)')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        TextInput::make('metadata.translations.en.authenticity')
                                            ->label('Authenticity note (EN)')
                                            ->maxLength(255),
                                        TagsInput::make('metadata.translations.en.highlights')
                                            ->label('Highlights (EN)')
                                            ->separator(',')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                                Tabs\Tab::make('Visual')
                                    ->schema([
                                        TextInput::make('metadata.visual.gradient')
                                            ->label('Gradient')
                                            ->maxLength(255),
                                        TextInput::make('metadata.visual.label')
                                            ->label('Visual label')
                                            ->maxLength(100),
                                        TextInput::make('metadata.visual.accent')
                                            ->label('Accent color')
                                            ->maxLength(40),
                                        TextInput::make('metadata.visual.finish')
                                            ->label('Finish')
                                            ->maxLength(100),
                                    ])
                                    ->columns(2),
                                Tabs\Tab::make('Shipping')
                                    ->schema([
                                        TextInput::make('metadata.shipping.domestic.carrier')
                                            ->label('Domestic carrier')
                                            ->maxLength(100),
                                        TextInput::make('metadata.shipping.domestic.fee')
                                            ->label('Domestic fee')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        Toggle::make('metadata.shipping.domestic.included_in_price')
                                            ->label('Domestic fee included in product price'),
                                        Toggle::make('metadata.shipping.international.enabled')
                                            ->label('International enabled'),
                                        TextInput::make('metadata.shipping.international.carrier')
                                            ->label('International carrier')
                                            ->maxLength(100),
                                        TextInput::make('metadata.shipping.international.rates.europe')
                                            ->label('Europe rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        TextInput::make('metadata.shipping.international.rates.usa')
                                            ->label('USA rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        TextInput::make('metadata.shipping.international.rates.canada')
                                            ->label('Canada rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        TextInput::make('metadata.shipping.international.rates.china')
                                            ->label('China rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        TextInput::make('metadata.shipping.international.rates.uk')
                                            ->label('United Kingdom rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                        TextInput::make('metadata.shipping.international.rates.restOfWorld')
                                            ->label('Rest of world rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step(0.01),
                                    ])
                                    ->columns(2),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Section::make('Authenticity and lot support')
                    ->schema([
                        Toggle::make('is_authenticated'),
                        Textarea::make('authenticity_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        Toggle::make('is_lot'),
                        TextInput::make('lot_configuration.total_cards')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('lot_configuration.guaranteed_hits')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('lot_configuration.condition_mix')
                            ->maxLength(255),
                        Textarea::make('lot_configuration.summary')
                            ->rows(3),
                        TagsInput::make('lot_configuration.named_cards')
                            ->separator(',')
                            ->columnSpanFull(),
                        TagsInput::make('lot_configuration.preview_cards')
                            ->separator(',')
                            ->columnSpanFull(),
                        TagsInput::make('lot_configuration.themes')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category')->withCount(['listings']))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('franchise')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_lot')
                    ->boolean()
                    ->label('Lot'),
                IconColumn::make('is_authenticated')
                    ->boolean()
                    ->label('Auth'),
                TextColumn::make('listings_count')
                    ->label('Listings')
                    ->numeric(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable(),
                TernaryFilter::make('is_lot'),
                TernaryFilter::make('is_authenticated'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function normalizeMediaStateForForm(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return collect($state)
            ->map(function (mixed $item, mixed $key): ?array {
                if (is_string($item) && trim($item) !== '') {
                    return [
                        'label' => is_string($key) && ! is_numeric($key) ? trim($key) : null,
                        'kind' => 'image',
                        'url' => trim($item),
                        'path' => null,
                        'disk' => null,
                        'mime_type' => null,
                        'file_size' => null,
                    ];
                }

                if (! is_array($item)) {
                    return null;
                }

                $row = [
                    'label' => self::nullableString($item['label'] ?? (is_string($key) && ! is_numeric($key) ? $key : null)),
                    'kind' => self::nullableString($item['kind'] ?? null) ?? 'image',
                    'url' => self::nullableString($item['url'] ?? null),
                    'path' => self::nullableString($item['path'] ?? null),
                    'disk' => self::nullableString($item['disk'] ?? $item['storage_disk'] ?? null),
                    'mime_type' => self::nullableString($item['mime_type'] ?? null),
                    'file_size' => is_numeric($item['file_size'] ?? null) ? (int) $item['file_size'] : null,
                ];

                return self::mediaRowHasContent($row) ? $row : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function dehydrateMediaStateForStorage(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return collect($state)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $row = [
                    'label' => self::nullableString($item['label'] ?? null),
                    'kind' => self::nullableString($item['kind'] ?? null) ?? 'image',
                    'url' => self::nullableString($item['url'] ?? null),
                    'path' => self::nullableString($item['path'] ?? null),
                    'disk' => self::nullableString($item['disk'] ?? null),
                    'mime_type' => self::nullableString($item['mime_type'] ?? null),
                    'file_size' => is_numeric($item['file_size'] ?? null) ? (int) $item['file_size'] : null,
                ];

                return self::mediaRowHasContent($row) ? $row : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function normalizeLotConfigurationForForm(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return [
            'total_cards' => is_numeric($state['total_cards'] ?? null) ? (int) $state['total_cards'] : null,
            'guaranteed_hits' => is_numeric($state['guaranteed_hits'] ?? null) ? (int) $state['guaranteed_hits'] : null,
            'condition_mix' => self::nullableString($state['condition_mix'] ?? null),
            'summary' => self::nullableString($state['summary'] ?? null),
            'named_cards' => self::normalizeTagList($state['named_cards'] ?? []),
            'preview_cards' => self::normalizeTagList($state['preview_cards'] ?? []),
            'themes' => self::normalizeTagList($state['themes'] ?? []),
        ];
    }

    public static function dehydrateLotConfigurationForStorage(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $normalized = [
            'total_cards' => is_numeric($state['total_cards'] ?? null) ? (int) $state['total_cards'] : 0,
            'guaranteed_hits' => is_numeric($state['guaranteed_hits'] ?? null) ? (int) $state['guaranteed_hits'] : 0,
            'condition_mix' => self::nullableString($state['condition_mix'] ?? null),
            'summary' => self::nullableString($state['summary'] ?? null),
            'named_cards' => self::normalizeTagList($state['named_cards'] ?? []),
            'preview_cards' => self::normalizeTagList($state['preview_cards'] ?? []),
            'themes' => self::normalizeTagList($state['themes'] ?? []),
        ];

        $hasValues = ($normalized['total_cards'] ?? 0) > 0
            || ($normalized['guaranteed_hits'] ?? 0) > 0
            || ! empty($normalized['condition_mix'])
            || ! empty($normalized['summary'])
            || ! empty($normalized['named_cards'])
            || ! empty($normalized['preview_cards'])
            || ! empty($normalized['themes']);

        return $hasValues ? $normalized : [];
    }

    protected static function normalizeTagList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item) => self::nullableString($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function mediaRowHasContent(array $row): bool
    {
        return collect($row)
            ->except(['kind', 'label'])
            ->contains(fn (mixed $value) => self::nullableString($value) !== null);
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        return null;
    }
}
