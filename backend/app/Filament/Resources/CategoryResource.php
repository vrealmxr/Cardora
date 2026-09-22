<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryResource extends RestrictableResource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Category details')
                    ->schema([
                        Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('icon')
                            ->helperText('Heroicon name, short token, or brand marker'),
                        Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'hidden' => 'Hidden',
                                'archived' => 'Archived',
                            ])
                            ->default('active')
                            ->required(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Textarea::make('short_description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Storefront metadata')
                    ->description('These fields control how the category appears across the public Cardora experience.')
                    ->schema([
                        TextInput::make('metadata.frontend_key')
                            ->label('Frontend key')
                            ->helperText('Internal storefront key such as cards, figures, comics, or misc.')
                            ->maxLength(255),
                        TextInput::make('metadata.visual.gradient')
                            ->label('Visual gradient')
                            ->helperText('Tailwind gradient classes used by category cards and hero surfaces.')
                            ->columnSpanFull(),
                        ColorPicker::make('metadata.visual.accent')
                            ->label('Accent color')
                            ->helperText('Highlight color for badges and glow states.'),
                        TextInput::make('metadata.visual.label')
                            ->label('Visual label')
                            ->maxLength(255),
                        TextInput::make('metadata.visual.finish')
                            ->label('Visual finish')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                static::buildLocaleContentSection('el', 'Greek content'),
                static::buildLocaleFilterSection('el', 'Greek filters'),
                static::buildLocaleContentSection('en', 'English content'),
                static::buildLocaleFilterSection('en', 'English filters'),
            ]);
    }

    protected static function buildLocaleContentSection(string $locale, string $heading): Section
    {
        return Section::make($heading)
            ->schema([
                TextInput::make("metadata.translations.{$locale}.name")
                    ->label('Public name')
                    ->maxLength(255),
                TextInput::make("metadata.translations.{$locale}.market_label")
                    ->label('Market label')
                    ->maxLength(255),
                TextInput::make("metadata.translations.{$locale}.tagline")
                    ->label('Tagline')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make("metadata.translations.{$locale}.short_description")
                    ->label('Short description')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make("metadata.translations.{$locale}.description")
                    ->label('Full description')
                    ->rows(4)
                    ->columnSpanFull(),
                TagsInput::make("metadata.translations.{$locale}.subcategories")
                    ->label('Subcategories')
                    ->placeholder('Add a subcategory and press Enter')
                    ->default([])
                    ->columnSpanFull(),
                TagsInput::make("metadata.translations.{$locale}.product_types")
                    ->label('Filter product types')
                    ->placeholder('Add an option and press Enter')
                    ->default([])
                    ->columnSpanFull(),
                TagsInput::make("metadata.translations.{$locale}.spotlight_filters")
                    ->label('Spotlight filters')
                    ->placeholder('Add a spotlight filter and press Enter')
                    ->default([])
                    ->columnSpanFull(),
            ])
            ->columns(2)
            ->collapsible();
    }

    protected static function buildLocaleFilterSection(string $locale, string $heading): Section
    {
        return Section::make($heading)
            ->schema([
                TagsInput::make("metadata.translations.{$locale}.filters.prices")
                    ->label('Price options')
                    ->placeholder('Add a price option and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.conditions")
                    ->label('Condition options')
                    ->placeholder('Add a condition and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.rarities")
                    ->label('Rarity options')
                    ->placeholder('Add a rarity and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.franchises")
                    ->label('Franchise options')
                    ->placeholder('Add a franchise and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.brands")
                    ->label('Brand options')
                    ->placeholder('Add a brand and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.graded")
                    ->label('Grading options')
                    ->placeholder('Add a grading option and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.availability")
                    ->label('Availability options')
                    ->placeholder('Add an availability option and press Enter')
                    ->default([]),
                TagsInput::make("metadata.translations.{$locale}.filters.seller_ratings")
                    ->label('Seller rating options')
                    ->placeholder('Add a rating threshold and press Enter')
                    ->default([]),
            ])
            ->columns(2)
            ->collapsible()
            ->collapsed();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['children', 'products', 'listings', 'taxonomies', 'attributeDefinitions']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Top level')
                    ->toggleable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'hidden' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->numeric(),
                TextColumn::make('taxonomies_count')
                    ->label('Taxonomies')
                    ->numeric(),
                TextColumn::make('attribute_definitions_count')
                    ->label('Attributes')
                    ->numeric(),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'hidden' => 'Hidden',
                        'archived' => 'Archived',
                    ]),
                Filter::make('root_only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id')),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
