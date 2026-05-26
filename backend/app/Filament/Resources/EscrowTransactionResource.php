<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscrowTransactionResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\EscrowTransaction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EscrowTransactionResource extends Resource
{
    protected static ?string $model = EscrowTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Escrow')
                    ->schema([
                        Select::make('order_id')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('buyer_id')
                            ->relationship('buyer', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('seller_id')
                            ->relationship('seller', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->default('EUR')
                            ->required(),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::escrowStatuses())
                            ->required(),
                        TextInput::make('provider_reference')
                            ->maxLength(255),
                        DateTimePicker::make('held_at'),
                        DateTimePicker::make('released_at'),
                        DateTimePicker::make('disputed_at'),
                        DateTimePicker::make('resolved_at'),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('buyer.display_name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('seller.display_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('provider_reference')
                    ->toggleable(),
                TextColumn::make('held_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::escrowStatuses()),
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
            'index' => Pages\ListEscrowTransactions::route('/'),
            'create' => Pages\CreateEscrowTransaction::route('/create'),
            'view' => Pages\ViewEscrowTransaction::route('/{record}'),
            'edit' => Pages\EditEscrowTransaction::route('/{record}/edit'),
        ];
    }
}
