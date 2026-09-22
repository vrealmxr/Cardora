<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewResource extends RestrictableResource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Review')
                    ->schema([
                        Select::make('order_id')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload(),
                        Select::make('listing_id')
                            ->relationship('listing', 'title_snapshot')
                            ->searchable()
                            ->preload(),
                        Select::make('product_id')
                            ->relationship('product', 'title')
                            ->searchable()
                            ->preload(),
                        Select::make('reviewer_id')
                            ->relationship('reviewer', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('reviewee_id')
                            ->relationship('reviewee', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('rating')
                            ->numeric()
                            ->required(),
                        TextInput::make('title')
                            ->maxLength(255),
                        Textarea::make('body')
                            ->rows(5)
                            ->columnSpanFull(),
                        Toggle::make('is_public')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reviewer.display_name')
                    ->label('Reviewer')
                    ->searchable(),
                TextColumn::make('reviewee.display_name')
                    ->label('Reviewee')
                    ->searchable(),
                TextColumn::make('rating')
                    ->badge(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->placeholder('No product'),
                IconColumn::make('is_public')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListReviews::route('/'),
            'create' => Pages\CreateReview::route('/create'),
            'view' => Pages\ViewReview::route('/{record}'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
