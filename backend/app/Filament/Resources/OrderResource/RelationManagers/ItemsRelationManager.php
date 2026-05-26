<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('listing_id')
                    ->relationship('listing', 'title_snapshot')
                    ->searchable()
                    ->preload(),
                Select::make('product_id')
                    ->relationship('product', 'title')
                    ->searchable()
                    ->preload(),
                TextInput::make('title_snapshot')
                    ->required()
                    ->maxLength(255),
                TextInput::make('unit_price')
                    ->numeric()
                    ->required(),
                TextInput::make('quantity')
                    ->numeric()
                    ->required(),
                TextInput::make('condition_snapshot')
                    ->maxLength(255),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_snapshot')
            ->columns([
                TextColumn::make('title_snapshot')
                    ->label('Item')
                    ->searchable(),
                TextColumn::make('product.title')
                    ->label('Product')
                    ->placeholder('No product'),
                TextColumn::make('unit_price')
                    ->money('EUR'),
                TextColumn::make('quantity')
                    ->numeric(),
                TextColumn::make('condition_snapshot')
                    ->label('Condition')
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
