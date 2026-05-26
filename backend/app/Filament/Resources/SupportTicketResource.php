<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\SupportTicket;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ticket')
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
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255),
                        Select::make('category')
                            ->options(MarketplaceAdminOptions::supportCategories())
                            ->required()
                            ->searchable(),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::supportStatuses())
                            ->required(),
                        Select::make('priority')
                            ->options(MarketplaceAdminOptions::supportPriorities())
                            ->required(),
                        Textarea::make('description')
                            ->rows(5)
                            ->columnSpanFull(),
                        TagsInput::make('attachments')
                            ->separator(',')
                            ->columnSpanFull(),
                        Textarea::make('resolution')
                            ->rows(4)
                            ->columnSpanFull(),
                        DateTimePicker::make('resolved_at'),
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
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('user.display_name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('No order'),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => MarketplaceAdminOptions::supportCategories()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => $state === 'dsa_notice' ? 'danger' : 'gray'),
                TextColumn::make('metadata.notice_reason')
                    ->label('Reason')
                    ->toggleable()
                    ->limit(32),
                TextColumn::make('reported_target')
                    ->label('Reported target')
                    ->toggleable()
                    ->limit(42)
                    ->state(fn (SupportTicket $record): ?string => data_get($record->metadata, 'reported_url') ?: data_get($record->metadata, 'reported_user_reference')),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'normal' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(MarketplaceAdminOptions::supportCategories()),
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::supportStatuses()),
                SelectFilter::make('priority')
                    ->options(MarketplaceAdminOptions::supportPriorities()),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->action(fn (SupportTicket $record) => $record->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'create' => Pages\CreateSupportTicket::route('/create'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
