<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\Order;
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
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends RestrictableResource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Order routing')
                    ->schema([
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
                        TextInput::make('order_number')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::orderStatuses())
                            ->required(),
                        Select::make('escrow_status')
                            ->options(MarketplaceAdminOptions::escrowStatuses())
                            ->required(),
                        TextInput::make('payment_method')
                            ->maxLength(255),
                    ])
                    ->columns(3),
                Section::make('Financials')
                    ->schema([
                        TextInput::make('subtotal')->numeric()->required(),
                        TextInput::make('shipping_total')->numeric()->required(),
                        TextInput::make('service_fee')->numeric()->required(),
                        TextInput::make('total')->numeric()->required(),
                        TextInput::make('total_amount')->numeric(),
                        TextInput::make('commission_amount')->numeric(),
                        TextInput::make('seller_amount')->numeric(),
                        TextInput::make('currency')
                            ->default('EUR')
                            ->maxLength(3),
                        TextInput::make('tracking_number')
                            ->maxLength(255),
                        TextInput::make('stripe_checkout_session_id')
                            ->maxLength(255),
                        TextInput::make('stripe_payment_intent_id')
                            ->maxLength(255),
                        TextInput::make('stripe_charge_id')
                            ->maxLength(255),
                        TextInput::make('stripe_transfer_id')
                            ->maxLength(255),
                    ])
                    ->columns(3),
                Section::make('Addresses and notes')
                    ->schema([
                        KeyValue::make('shipping_address')
                            ->columnSpanFull(),
                        KeyValue::make('billing_address')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                        DateTimePicker::make('placed_at'),
                        DateTimePicker::make('buyer_confirmed_at'),
                        DateTimePicker::make('auto_release_at'),
                        DateTimePicker::make('released_at'),
                        DateTimePicker::make('cancelled_at'),
                        DateTimePicker::make('refunded_at'),
                        DateTimePicker::make('completed_at'),
                        DateTimePicker::make('disputed_at'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['buyer', 'seller'])->withCount(['items', 'supportTickets']))
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buyer.display_name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('seller.display_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'released' => 'success',
                        'pending_payment', 'paid_pending_release' => 'warning',
                        'disputed', 'refunded', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('escrow_status')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('commission_amount')
                    ->label('Commission')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('seller_amount')
                    ->label('Seller amount')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('stripe_payment_intent_id')
                    ->label('PaymentIntent')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                TextColumn::make('stripe_transfer_id')
                    ->label('Transfer')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->numeric(),
                TextColumn::make('support_tickets_count')
                    ->label('Tickets')
                    ->numeric(),
                TextColumn::make('placed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::orderStatuses()),
                SelectFilter::make('escrow_status')
                    ->options(MarketplaceAdminOptions::escrowStatuses()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('manual_release')
                    ->label('Manual release')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->status === 'paid_pending_release')
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        app(\App\Services\StripeMarketplaceService::class)->releaseFundsToSeller($record, [
                            'buyer_confirmed' => false,
                            'released_by' => 'filament_admin',
                            'manual_release' => true,
                        ]);
                    }),
                Tables\Actions\Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(fn (Order $record): bool => in_array($record->status, ['paid_pending_release', 'disputed'], true))
                    ->requiresConfirmation()
                    ->action(function (Order $record) {
                        app(\App\Services\StripeMarketplaceService::class)->refundOrder($record, [
                            'requested_by' => 'filament_admin',
                            'reason' => 'Admin initiated refund from Filament.',
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
