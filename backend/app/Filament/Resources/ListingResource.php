<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ListingResource\RelationManagers\BidsRelationManager;
use App\Filament\Resources\ListingResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\Listing;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListingResource extends RestrictableResource
{
    protected static ?string $model = Listing::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Listing routing')
                    ->schema([
                        Select::make('product_id')
                            ->relationship('product', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('seller_id')
                            ->relationship('seller', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::listingStatuses())
                            ->default('published')
                            ->required(),
                        Select::make('sale_format')
                            ->options(MarketplaceAdminOptions::saleFormats())
                            ->default('fixed_price')
                            ->required(),
                        TextInput::make('title_snapshot')
                            ->maxLength(255),
                        Toggle::make('is_featured'),
                        DateTimePicker::make('featured_until'),
                        TextInput::make('featured_payment_id')
                            ->numeric(),
                        Toggle::make('accept_offers'),
                    ])
                    ->columns(3),
                Section::make('Commercial terms')
                    ->schema([
                        TextInput::make('price')
                            ->numeric()
                            ->required(),
                        TextInput::make('old_price')
                            ->numeric(),
                        TextInput::make('minimum_offer')
                            ->numeric(),
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1),
                        TextInput::make('available_quantity')
                            ->numeric()
                            ->default(1),
                        TextInput::make('shipping_cost')
                            ->numeric()
                            ->default(0),
                        TextInput::make('condition')
                            ->maxLength(255),
                        TextInput::make('rarity')
                            ->maxLength(255),
                        TextInput::make('availability')
                            ->maxLength(255)
                            ->default('available'),
                    ])
                    ->columns(3),
                Section::make('Auction and lot data')
                    ->schema([
                        TextInput::make('starting_bid')
                            ->numeric(),
                        TextInput::make('current_bid')
                            ->numeric(),
                        TextInput::make('reserve_price')
                            ->numeric(),
                        TextInput::make('bid_increment')
                            ->numeric(),
                        TextInput::make('buyout_price')
                            ->numeric(),
                        DateTimePicker::make('auction_starts_at'),
                        DateTimePicker::make('auction_ends_at'),
                        Select::make('winning_bidder_id')
                            ->relationship('winningBidder', 'display_name')
                            ->searchable()
                            ->preload(),
                        KeyValue::make('auction_settings')
                            ->columnSpanFull(),
                        KeyValue::make('lot_snapshot')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Shipping and compliance')
                    ->schema([
                        TextInput::make('shipping_profile')
                            ->maxLength(255),
                        TagsInput::make('shipping_methods')
                            ->separator(','),
                        TextInput::make('dispatch_time')
                            ->maxLength(255),
                        Textarea::make('packaging_notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        KeyValue::make('attributes')
                            ->columnSpanFull(),
                        KeyValue::make('compliance_flags')
                            ->columnSpanFull(),
                        Textarea::make('moderation_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                        DateTimePicker::make('published_at'),
                        DateTimePicker::make('expires_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'category', 'seller']))
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('seller.display_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('sale_format')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'auction' ? 'warning' : 'info'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published', 'sold' => 'success',
                        'pending_review', 'needs_revision' => 'warning',
                        'rejected', 'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('price')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('current_bid')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('available_quantity')
                    ->label('Available')
                    ->numeric(),
                IconColumn::make('is_featured')
                    ->boolean(),
                TextColumn::make('featured_until')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::listingStatuses()),
                SelectFilter::make('sale_format')
                    ->options(MarketplaceAdminOptions::saleFormats()),
                SelectFilter::make('category_id')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_featured'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Listing $record): bool => in_array($record->status, ['draft', 'pending_review', 'needs_revision', 'rejected'], true))
                    ->action(fn (Listing $record) => $record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ])),
                Tables\Actions\Action::make('requestChanges')
                    ->label('Needs revision')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->action(fn (Listing $record) => $record->update([
                        'status' => 'needs_revision',
                    ])),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Listing $record) => $record->update([
                        'status' => 'rejected',
                    ])),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            BidsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListings::route('/'),
            'create' => Pages\CreateListing::route('/create'),
            'view' => Pages\ViewListing::route('/{record}'),
            'edit' => Pages\EditListing::route('/{record}/edit'),
        ];
    }
}
