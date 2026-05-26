<?php

namespace App\Filament\Resources\ConversationResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('sender_id')
                    ->relationship('sender', 'display_name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                TagsInput::make('attachments')
                    ->separator(',')
                    ->columnSpanFull(),
                TextInput::make('offer_amount')
                    ->numeric(),
                DateTimePicker::make('read_at'),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('sender.display_name')
                    ->label('Sender')
                    ->searchable(),
                TextColumn::make('body')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('moderation_status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'clean' ? 'success' : 'danger'),
                IconColumn::make('requires_admin_review')
                    ->label('Review')
                    ->boolean(),
                TextColumn::make('offer_amount')
                    ->money('EUR')
                    ->toggleable(),
                TextColumn::make('read_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
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
