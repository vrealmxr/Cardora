<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\Notification;
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

class NotificationResource extends RestrictableResource
{
    protected static ?string $model = Notification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Notification')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'display_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->options(MarketplaceAdminOptions::notificationTypes())
                            ->required(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('body')
                            ->rows(4)
                            ->columnSpanFull(),
                        KeyValue::make('data')
                            ->columnSpanFull(),
                        DateTimePicker::make('read_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.display_name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('read_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(MarketplaceAdminOptions::notificationTypes()),
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
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'view' => Pages\ViewNotification::route('/{record}'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }
}
