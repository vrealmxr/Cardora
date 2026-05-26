<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryTaxonomyResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\CategoryTaxonomy;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryTaxonomyResource extends Resource
{
    protected static ?string $model = CategoryTaxonomy::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'catalog taxonomy';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Taxonomy definition')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('parent_id')
                            ->relationship('parent', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('taxonomy_type')
                            ->options(MarketplaceAdminOptions::taxonomyTypes())
                            ->required()
                            ->default('item_type'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('icon')
                            ->maxLength(255),
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
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category')->withCount(['children', 'attributeDefinitions']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('Global'),
                TextColumn::make('taxonomy_type')
                    ->badge(),
                TextColumn::make('slug')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'hidden' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('children_count')
                    ->label('Children')
                    ->numeric(),
                TextColumn::make('attribute_definitions_count')
                    ->label('Attributes')
                    ->numeric(),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('taxonomy_type')
                    ->options(MarketplaceAdminOptions::taxonomyTypes()),
                SelectFilter::make('category_id')
                    ->relationship('category', 'name'),
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'hidden' => 'Hidden',
                        'archived' => 'Archived',
                    ]),
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
            'index' => Pages\ListCategoryTaxonomies::route('/'),
            'create' => Pages\CreateCategoryTaxonomy::route('/create'),
            'view' => Pages\ViewCategoryTaxonomy::route('/{record}'),
            'edit' => Pages\EditCategoryTaxonomy::route('/{record}/edit'),
        ];
    }
}
