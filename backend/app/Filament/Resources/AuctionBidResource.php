<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuctionBidResource\Pages;
use App\Models\AuctionBid;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuctionBidResource extends RestrictableResource
{
    protected static ?string $model = AuctionBid::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Auctions';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Bid')
                    ->schema([
                        Select::make('listing_id')
                            ->relationship('listing', 'title_snapshot')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('bidder_id')
                            ->relationship('bidder', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        TextInput::make('status')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('placed_at'),
                        TextInput::make('ip_address')
                            ->maxLength(255),
                        Textarea::make('user_agent')
                            ->rows(3)
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
            ->columns([
                TextColumn::make('listing.title_snapshot')
                    ->label('Listing')
                    ->searchable(),
                TextColumn::make('bidder.display_name')
                    ->label('Bidder')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('placed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(),
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
            'index' => Pages\ListAuctionBids::route('/'),
            'create' => Pages\CreateAuctionBid::route('/create'),
            'view' => Pages\ViewAuctionBid::route('/{record}'),
            'edit' => Pages\EditAuctionBid::route('/{record}/edit'),
        ];
    }
}
