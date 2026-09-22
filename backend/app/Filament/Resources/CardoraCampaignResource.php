<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardoraCampaignResource\Pages;
use App\Filament\Resources\DrawCampaignResource\RelationManagers\EntriesRelationManager;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\DrawCampaign;
use App\Services\PlatformVolumeCampaignService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use App\Filament\Resources\RestrictableResource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardoraCampaignResource extends RestrictableResource
{
    protected static ?string $model = DrawCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Promotions';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Cardora campaigns';
    }

    public static function getModelLabel(): string
    {
        return 'Cardora campaign';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Cardora campaigns';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campaign overview')
                    ->description('Official homepage campaigns managed directly by Cardora.')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug((string) $state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::drawStatuses())
                            ->default('draft')
                            ->native(false)
                            ->required(),
                        Toggle::make('featured')
                            ->label('Feature on homepage')
                            ->inline(false),
                        TextInput::make('subtitle')
                            ->maxLength(255)
                            ->placeholder('Short supporting line below the title')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Prize and campaign mechanics')
                    ->schema([
                        TextInput::make('prize_title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('prize_category')
                            ->maxLength(255),
                        TextInput::make('prize_condition')
                            ->maxLength(255),
                        TextInput::make('dispatch_window')
                            ->maxLength(255)
                            ->placeholder('Ships within 2 working days'),
                        TextInput::make('prize_value')
                            ->numeric()
                            ->prefix('EUR')
                            ->step(0.01),
                        TextInput::make('entries_per_euro')
                            ->label('Entries per EUR')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        TextInput::make('target_amount')
                            ->label('Target volume')
                            ->numeric()
                            ->prefix('EUR')
                            ->step(0.01)
                            ->required(),
                        TextInput::make('current_amount')
                            ->label('Current volume')
                            ->numeric()
                            ->default(0)
                            ->prefix('EUR')
                            ->step(0.01)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Synced automatically from released marketplace orders.'),
                        TextInput::make('entries_issued')
                            ->label('Issued entries')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Calculated automatically from the released order total.'),
                        TextInput::make('participants_count')
                            ->label('Participants')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Unique buyers counted from released orders.'),
                        Textarea::make('fairness_note')
                            ->rows(3)
                            ->placeholder('Short note about how the winner is selected and handled.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Schedule and fulfillment')
                    ->schema([
                        DateTimePicker::make('ends_at')
                            ->seconds(false),
                        DateTimePicker::make('draw_at')
                            ->seconds(false),
                        DateTimePicker::make('drawn_at')
                            ->seconds(false),
                        Select::make('winner_user_id')
                            ->relationship('winner', 'display_name')
                            ->searchable()
                            ->preload(),
                        Toggle::make('requires_verification')
                            ->inline(false),
                        Toggle::make('shipping_covered')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(3),
                Section::make('Visual cover')
                    ->description('The uploaded image is used directly on the homepage campaign card.')
                    ->schema([
                        FileUpload::make('cover_image')
                            ->label('Campaign photo')
                            ->disk('public')
                            ->directory('draws')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                        Select::make('visual_gradient')
                            ->label('Frame style')
                            ->options(self::visualThemeOptions())
                            ->default(self::defaultVisualGradient())
                            ->native(false),
                        TextInput::make('visual_label')
                            ->label('Top-right label')
                            ->default(self::defaultVisualLabel())
                            ->maxLength(100),
                        Placeholder::make('front_usage')
                            ->label('Frontend placement')
                            ->content('Active Cardora campaigns appear only on the homepage and use this photo automatically.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->latest('created_at'))
            ->columns([
                ImageColumn::make('visual.imageUrl')
                    ->label('Photo')
                    ->square(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (DrawCampaign $record): ?string => $record->subtitle),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'completed' => 'success',
                        'review', 'locked' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('target_amount')
                    ->money('EUR')
                    ->label('Target'),
                TextColumn::make('current_amount')
                    ->money('EUR')
                    ->label('Progress'),
                TextColumn::make('participants_count')
                    ->label('Participants')
                    ->numeric(),
                IconColumn::make('featured')
                    ->boolean(),
                TextColumn::make('draw_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->label('Updated'),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        app(PlatformVolumeCampaignService::class)->syncAll();

        return parent::getEloquentQuery()
            ->where('campaign_type', 'platform_volume');
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
            'index' => Pages\ListCardoraCampaigns::route('/'),
            'create' => Pages\CreateCardoraCampaign::route('/create'),
            'edit' => Pages\EditCardoraCampaign::route('/{record}/edit'),
        ];
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        $visual = is_array($data['visual'] ?? null) ? $data['visual'] : [];
        $coverImage = self::nullableString($visual['imagePath'] ?? null);

        if (! $coverImage) {
            $coverImage = self::extractStoragePathFromUrl(self::nullableString($visual['imageUrl'] ?? null));
        }

        $data['cover_image'] = $coverImage;
        $data['visual_gradient'] = self::nullableString($visual['gradient'] ?? null) ?? self::defaultVisualGradient();
        $data['visual_label'] = self::nullableString($visual['label'] ?? null) ?? self::defaultVisualLabel();

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data, ?DrawCampaign $record = null): array
    {
        $existingVisual = is_array($record?->visual) ? $record->visual : [];
        $coverImage = self::nullableString($data['cover_image'] ?? null);
        $visual = array_replace($existingVisual, [
            'gradient' => self::nullableString($data['visual_gradient'] ?? null) ?? self::defaultVisualGradient(),
            'label' => self::nullableString($data['visual_label'] ?? null) ?? self::defaultVisualLabel(),
        ]);

        if ($coverImage) {
            $visual['imagePath'] = $coverImage;
            $visual['imageUrl'] = Storage::disk('public')->url($coverImage);
        } else {
            unset($visual['imagePath'], $visual['imageUrl']);
        }

        unset($data['cover_image'], $data['visual_gradient'], $data['visual_label']);

        $data['campaign_type'] = 'platform_volume';
        $data['visual'] = $visual;
        $data['entries_per_euro'] = max((int) ($data['entries_per_euro'] ?? 1), 1);
        $data['current_amount'] = array_key_exists('current_amount', $data)
            ? (float) ($data['current_amount'] ?? 0)
            : (float) ($record?->current_amount ?? 0);
        $data['entries_issued'] = array_key_exists('entries_issued', $data)
            ? (int) ($data['entries_issued'] ?? 0)
            : (int) ($record?->entries_issued ?? 0);
        $data['sold_entries'] = array_key_exists('sold_entries', $data)
            ? (int) ($data['sold_entries'] ?? 0)
            : (int) ($record?->sold_entries ?? ($record?->entries_issued ?? 0));
        $data['participants_count'] = array_key_exists('participants_count', $data)
            ? (int) ($data['participants_count'] ?? 0)
            : (int) ($record?->participants_count ?? 0);
        $data['shipping_covered'] = (bool) ($data['shipping_covered'] ?? false);
        $data['featured'] = (bool) ($data['featured'] ?? false);
        $data['requires_verification'] = (bool) ($data['requires_verification'] ?? false);

        return $data;
    }

    public static function defaultVisualGradient(): string
    {
        return 'from-[#29465d] via-[#182033] to-[#09111d]';
    }

    public static function defaultVisualLabel(): string
    {
        return 'Cardora Campaign';
    }

    public static function visualThemeOptions(): array
    {
        return [
            'from-[#29465d] via-[#182033] to-[#09111d]' => 'Midnight Gold',
            'from-[#2f4f6f] via-[#17263b] to-[#060d18]' => 'Deep Sapphire',
            'from-[#2d5848] via-[#163227] to-[#07140e]' => 'Collector Emerald',
            'from-[#5b392e] via-[#24161b] to-[#0b0811]' => 'Vintage Crimson',
        ];
    }

    protected static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected static function extractStoragePathFromUrl(?string $url): ?string
    {
        if (! $url || ! Str::contains($url, '/storage/')) {
            return null;
        }

        return Str::after($url, '/storage/');
    }
}
