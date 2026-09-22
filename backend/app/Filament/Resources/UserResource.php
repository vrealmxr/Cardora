<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
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
use Illuminate\Support\Str;

class UserResource extends RestrictableResource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Account identity')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('display_name')
                            ->label('Nickname')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('handle')
                            ->prefix('@')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('handle', Str::slug((string) $state))),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('city')
                            ->maxLength(255),
                        Select::make('locale')
                            ->options(MarketplaceAdminOptions::localeOptions())
                            ->default('el')
                            ->required(),
                        TextInput::make('password')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Profile')
                    ->schema([
                        TextInput::make('collector_tagline')
                            ->maxLength(255),
                        Textarea::make('bio')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('avatar_url')
                            ->label('Avatar URL')
                            ->url()
                            ->maxLength(2048),
                        Select::make('profile_visibility')
                            ->options([
                                'public' => 'Public',
                                'followers' => 'Followers only',
                                'private' => 'Private',
                            ])
                            ->default('public'),
                        TagsInput::make('favorite_categories')
                            ->separator(',')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Marketplace status')
                    ->schema([
                        Select::make('trust_status')
                            ->options(MarketplaceAdminOptions::trustStatuses())
                            ->default('new')
                            ->required(),
                        TextInput::make('rating')
                            ->numeric()
                            ->step(0.01),
                        TextInput::make('sales_count')
                            ->numeric()
                            ->default(0),
                        TextInput::make('purchase_count')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_verified_seller'),
                        DateTimePicker::make('last_seen_at'),
                    ])
                    ->columns(3),
                Section::make('Admin access')
                    ->schema([
                        Toggle::make('is_admin'),
                        TextInput::make('admin_role')
                            ->default('moderator')
                            ->maxLength(255),
                        DateTimePicker::make('admin_last_seen_at'),
                        Textarea::make('admin_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['listings', 'purchases', 'sales']))
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label('Nickname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->prefix('@')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('city')
                    ->toggleable(),
                TextColumn::make('locale')
                    ->badge(),
                TextColumn::make('trust_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trusted' => 'success',
                        'flagged', 'restricted' => 'danger',
                        'reviewing' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('is_verified_seller')
                    ->boolean()
                    ->label('Verified'),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->label('Admin'),
                TextColumn::make('listings_count')
                    ->label('Listings')
                    ->numeric(),
                TextColumn::make('sales_count')
                    ->label('Sales')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin'),
                TernaryFilter::make('is_verified_seller'),
                SelectFilter::make('locale')
                    ->options(MarketplaceAdminOptions::localeOptions()),
                SelectFilter::make('trust_status')
                    ->options(MarketplaceAdminOptions::trustStatuses()),
            ])
            ->actions([
                Tables\Actions\Action::make('grantAdmin')
                    ->label('Grant admin')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->visible(fn (User $record): bool => ! $record->is_admin)
                    ->action(fn (User $record) => $record->update([
                        'is_admin' => true,
                        'admin_role' => $record->admin_role ?: 'moderator',
                    ])),
                Tables\Actions\Action::make('revokeAdmin')
                    ->label('Revoke admin')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (User $record): bool => (bool) $record->is_admin)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update([
                        'is_admin' => false,
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
