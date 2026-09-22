<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryAttributeDefinitionResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\CategoryAttributeDefinition;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryAttributeDefinitionResource extends RestrictableResource
{
    protected static ?string $model = CategoryAttributeDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'attribute definition';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Attribute definition')
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('taxonomy_id')
                            ->relationship('taxonomy', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        Select::make('field_type')
                            ->options(MarketplaceAdminOptions::attributeFieldTypes())
                            ->required()
                            ->default('text'),
                        TextInput::make('placeholder')
                            ->maxLength(255),
                        TextInput::make('default_value')
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
                        Textarea::make('help_text')
                            ->rows(3)
                            ->columnSpanFull(),
                        KeyValue::make('options')
                            ->helperText('Use key => label pairs for select or multiselect fields.')
                            ->columnSpanFull(),
                        TagsInput::make('validation_rules')
                            ->helperText('Laravel validation rules, e.g. required|string|max:255')
                            ->separator(',')
                            ->columnSpanFull(),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Behavior')
                    ->schema([
                        Toggle::make('is_required'),
                        Toggle::make('is_filterable'),
                        Toggle::make('applies_to_product')
                            ->default(true),
                        Toggle::make('applies_to_listing')
                            ->default(true),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'taxonomy']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('Global'),
                TextColumn::make('taxonomy.name')
                    ->label('Taxonomy')
                    ->placeholder('None'),
                TextColumn::make('field_type')
                    ->badge(),
                IconColumn::make('is_required')
                    ->boolean()
                    ->label('Required'),
                IconColumn::make('is_filterable')
                    ->boolean()
                    ->label('Filterable'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'hidden' => 'warning',
                        'archived' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->relationship('category', 'name'),
                SelectFilter::make('field_type')
                    ->options(MarketplaceAdminOptions::attributeFieldTypes()),
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
            'index' => Pages\ListCategoryAttributeDefinitions::route('/'),
            'create' => Pages\CreateCategoryAttributeDefinition::route('/create'),
            'view' => Pages\ViewCategoryAttributeDefinition::route('/{record}'),
            'edit' => Pages\EditCategoryAttributeDefinition::route('/{record}/edit'),
        ];
    }
}
