<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FeaturedListingPaymentResource\Pages;
use App\Models\FeaturedListingPayment;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FeaturedListingPaymentResource extends RestrictableResource
{
    protected static ?string $model = FeaturedListingPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.display_name')->label('User')->searchable(),
                TextColumn::make('listing.title_snapshot')->label('Listing')->searchable(),
                TextColumn::make('amount')->money('EUR'),
                TextColumn::make('currency')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('stripe_payment_intent_id')->label('Stripe PI')->toggleable(),
                TextColumn::make('paid_at')->dateTime()->toggleable(),
                TextColumn::make('expires_at')->dateTime()->toggleable(),
                TextColumn::make('used_at')->dateTime()->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'pending',
                        'paid' => 'paid',
                        'used' => 'used',
                        'failed' => 'failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListFeaturedListingPayments::route('/'),
            'view' => Pages\ViewFeaturedListingPayment::route('/{record}'),
        ];
    }
}
