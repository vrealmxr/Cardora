<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends RestrictableResource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Message Watch';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = Message::query()->where('requires_admin_review', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Message::query()->where('requires_admin_review', true)->exists() ? 'danger' : 'gray';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Message')
                    ->schema([
                        Select::make('conversation_id')
                            ->relationship('conversation', 'id')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('sender_id')
                            ->relationship('sender', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('body')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Textarea::make('body_masked')
                            ->label('Masked body')
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
                    ])
                    ->columns(2),
                Section::make('Moderation')
                    ->schema([
                        TextInput::make('moderation_status')
                            ->required(),
                        TagsInput::make('moderation_flags')
                            ->separator(','),
                        TextInput::make('moderation_score')
                            ->numeric()
                            ->required(),
                        Select::make('requires_admin_review')
                            ->options([
                                0 => 'No',
                                1 => 'Yes',
                            ])
                            ->required(),
                        DateTimePicker::make('reviewed_at'),
                        Select::make('reviewed_by')
                            ->relationship('reviewer', 'display_name')
                            ->searchable()
                            ->preload(),
                        Placeholder::make('conversation_participants')
                            ->label('Participants')
                            ->content(fn (?Message $record) => $record
                                ? sprintf(
                                    '%s → %s',
                                    $record->sender?->display_name ?: $record->sender?->email ?: 'Unknown sender',
                                    $record->conversation && $record->conversation->seller_id === $record->sender_id
                                        ? ($record->conversation->buyer?->display_name ?: $record->conversation->buyer?->email ?: 'Unknown recipient')
                                        : ($record->conversation?->seller?->display_name ?: $record->conversation?->seller?->email ?: 'Unknown recipient')
                                )
                                : '-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sender', 'reviewer', 'conversation.buyer', 'conversation.seller']))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('sender.display_name')
                    ->label('Sender')
                    ->searchable(),
                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->state(fn (Message $record) => $record->conversation && $record->conversation->seller_id === $record->sender_id
                        ? ($record->conversation->buyer?->display_name ?: $record->conversation->buyer?->email ?: 'Unknown')
                        : ($record->conversation?->seller?->display_name ?: $record->conversation?->seller?->email ?: 'Unknown'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('conversation.buyer', fn (Builder $buyerQuery) => $buyerQuery->where('display_name', 'like', "%{$search}%"))
                            ->orWhereHas('conversation.seller', fn (Builder $sellerQuery) => $sellerQuery->where('display_name', 'like', "%{$search}%"));
                    }),
                TextColumn::make('body_preview')
                    ->label('Message')
                    ->state(fn (Message $record) => $record->body_masked ?: $record->body)
                    ->limit(80)
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('body', 'like', "%{$search}%")
                        ->orWhere('body_masked', 'like', "%{$search}%")),
                TextColumn::make('moderation_status')
                    ->badge()
                    ->color(fn (string $state) => $state === 'clean' ? 'success' : 'danger'),
                TextColumn::make('moderation_flags')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                IconColumn::make('requires_admin_review')
                    ->label('Review')
                    ->boolean(),
                TextColumn::make('read_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('needs_review')
                    ->label('Needs review')
                    ->query(fn (Builder $query) => $query->where('requires_admin_review', true)),
                Filter::make('unread')
                    ->label('Unread')
                    ->query(fn (Builder $query) => $query->whereNull('read_at')),
                Filter::make('moderated')
                    ->label('Moderated')
                    ->query(fn (Builder $query) => $query->where('moderation_status', '!=', 'clean')),
            ])
            ->actions([
                Tables\Actions\Action::make('markReviewed')
                    ->label('Mark reviewed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Message $record) => $record->requires_admin_review)
                    ->action(function (Message $record): void {
                        $record->update([
                            'requires_admin_review' => false,
                            'reviewed_at' => now(),
                            'reviewed_by' => auth()->id(),
                        ]);
                    }),
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
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'view' => Pages\ViewMessage::route('/{record}'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
