<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DrawCampaignResource\RelationManagers\EntriesRelationManager;
use App\Filament\Resources\DrawCampaignResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\DrawCampaign;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Str;

class DrawCampaignResource extends RestrictableResource
{
    protected static ?string $model = DrawCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Promotions';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Community raffles';
    }

    public static function getModelLabel(): string
    {
        return 'Community raffle';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Community raffles';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campaign')
                    ->schema([
                        Select::make('host_user_id')
                            ->relationship('hostUser', 'display_name')
                            ->searchable()
                            ->preload(),
                        Select::make('winner_user_id')
                            ->relationship('winner', 'display_name')
                            ->searchable()
                            ->preload(),
                        Select::make('prize_listing_id')
                            ->relationship('prizeListing', 'title_snapshot')
                            ->searchable()
                            ->preload(),
                        Hidden::make('campaign_type')
                            ->default('community_raffle')
                            ->dehydrated(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::drawStatuses())
                            ->required(),
                        Toggle::make('featured'),
                        Toggle::make('requires_verification'),
                        Toggle::make('shipping_covered'),
                        TextInput::make('prize_title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('prize_category')
                            ->maxLength(255),
                        TextInput::make('prize_condition')
                            ->maxLength(255),
                        TextInput::make('prize_value')->numeric(),
                        TextInput::make('entry_price')->numeric(),
                        TextInput::make('entries_per_euro')->numeric(),
                        TextInput::make('target_amount')->numeric(),
                        TextInput::make('current_amount')->numeric(),
                        TextInput::make('target_entries')->numeric(),
                        TextInput::make('entries_issued')->numeric(),
                        TextInput::make('sold_entries')->numeric(),
                        TextInput::make('participants_count')->numeric(),
                        TextInput::make('max_entries_per_user')->numeric(),
                    ])
                    ->columns(3),
                Section::make('Messaging and fairness')
                    ->schema([
                        Textarea::make('subtitle')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('fairness_note')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('dispatch_window')
                            ->maxLength(255),
                        KeyValue::make('rules')
                            ->columnSpanFull(),
                        KeyValue::make('eligibility')
                            ->columnSpanFull(),
                        KeyValue::make('visual')
                            ->columnSpanFull(),
                        KeyValue::make('draw_result')
                            ->columnSpanFull(),
                        DateTimePicker::make('locked_at'),
                        DateTimePicker::make('ends_at'),
                        DateTimePicker::make('draw_at'),
                        DateTimePicker::make('drawn_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'completed' => 'success',
                        'review', 'locked' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('hostUser.display_name')
                    ->label('Host')
                    ->searchable(),
                TextColumn::make('prize_title')
                    ->label('Prize')
                    ->searchable(),
                TextColumn::make('target_amount')
                    ->money('EUR')
                    ->label('Target'),
                TextColumn::make('current_amount')
                    ->money('EUR')
                    ->label('Raised'),
                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->numeric(),
                IconColumn::make('featured')
                    ->boolean(),
                TextColumn::make('draw_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::drawStatuses()),
                TernaryFilter::make('featured'),
            ])
            ->actions([
                Tables\Actions\Action::make('activate')
                    ->label('Make active')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DrawCampaign $record): bool => in_array($record->status, ['draft', 'review', 'cancelled'], true))
                    ->action(fn (DrawCampaign $record) => $record->update([
                        'status' => 'active',
                    ])),
                Tables\Actions\Action::make('sendToReview')
                    ->label('Send to review')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn (DrawCampaign $record): bool => in_array($record->status, ['draft', 'cancelled'], true))
                    ->action(fn (DrawCampaign $record) => $record->update([
                        'status' => 'review',
                    ])),
                Tables\Actions\Action::make('moveToDraft')
                    ->label('Move to draft')
                    ->icon('heroicon-o-document')
                    ->color('gray')
                    ->visible(fn (DrawCampaign $record): bool => in_array($record->status, ['review', 'active'], true))
                    ->action(fn (DrawCampaign $record) => $record->update([
                        'status' => 'draft',
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('campaign_type', 'community_raffle');
    }

    public static function getRelations(): array
    {
        return [
            EntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDrawCampaigns::route('/'),
            'create' => Pages\CreateDrawCampaign::route('/create'),
            'view' => Pages\ViewDrawCampaign::route('/{record}'),
            'edit' => Pages\EditDrawCampaign::route('/{record}/edit'),
        ];
    }
}
