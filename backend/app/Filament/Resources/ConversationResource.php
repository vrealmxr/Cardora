<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversationResource\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\ConversationResource\Pages;
use App\Models\Conversation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Conversation')
                    ->schema([
                        Select::make('listing_id')
                            ->relationship('listing', 'title_snapshot')
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
                        TextInput::make('status')
                            ->required()
                            ->maxLength(255),
                        DateTimePicker::make('last_message_at'),
                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['listing', 'buyer', 'seller'])->withCount('messages'))
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('listing.title_snapshot')
                    ->label('Listing')
                    ->searchable(),
                TextColumn::make('buyer.display_name')
                    ->label('Buyer')
                    ->searchable(),
                TextColumn::make('seller.display_name')
                    ->label('Seller')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->numeric(),
                TextColumn::make('last_message_at')
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

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversations::route('/'),
            'create' => Pages\CreateConversation::route('/create'),
            'view' => Pages\ViewConversation::route('/{record}'),
            'edit' => Pages\EditConversation::route('/{record}/edit'),
        ];
    }
}
