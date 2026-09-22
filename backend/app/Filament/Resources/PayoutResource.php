<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\Payout;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayoutResource extends RestrictableResource
{
    protected static ?string $model = Payout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Payout')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('order_id')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload(),
                        Select::make('escrow_transaction_id')
                            ->relationship('escrowTransaction', 'provider_reference')
                            ->searchable()
                            ->preload(),
                        TextInput::make('amount')
                            ->numeric()
                            ->required(),
                        TextInput::make('currency')
                            ->default('EUR')
                            ->required(),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::payoutStatuses())
                            ->required(),
                        TextInput::make('reference')
                            ->maxLength(255),
                        DateTimePicker::make('initiated_at'),
                        DateTimePicker::make('completed_at'),
                        Textarea::make('failure_reason')
                            ->rows(3)
                            ->columnSpanFull(),
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
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.display_name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('No order'),
                TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('initiated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::payoutStatuses()),
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
            'index' => Pages\ListPayouts::route('/'),
            'create' => Pages\CreatePayout::route('/create'),
            'view' => Pages\ViewPayout::route('/{record}'),
            'edit' => Pages\EditPayout::route('/{record}/edit'),
        ];
    }
}
