<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageSeoSettingResource\Pages;
use App\Models\PageSeoSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Deliberately NOT a RestrictableResource — this is the one resource an is_seo_editor
 * user is allowed to see. Rows are pre-seeded (one per page_key x locale); the editor can
 * only edit meta_title/meta_description/h1, not create or delete page entries.
 */
class PageSeoSettingResource extends Resource
{
    protected static ?string $model = PageSeoSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'SEO Pages';

    protected static ?string $modelLabel = 'SEO page';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Page')
                    ->schema([
                        TextInput::make('page_key')
                            ->label('Page')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('locale')
                            ->label('Locale')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
                Section::make('Meta tags')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta title')
                            ->maxLength(60)
                            ->helperText('Ideal length: up to ~60 characters.')
                            ->columnSpanFull(),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Ideal length: up to ~160 characters.')
                            ->columnSpanFull(),
                        TextInput::make('h1')
                            ->label('Page heading (H1)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page_key')
                    ->label('Page')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label('Locale')
                    ->sortable(),
                TextColumn::make('meta_title')
                    ->label('Meta title')
                    ->limit(60)
                    ->placeholder('— using default —'),
                TextColumn::make('meta_description')
                    ->label('Meta description')
                    ->limit(60)
                    ->placeholder('— using default —'),
            ])
            ->defaultSort('page_key')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPageSeoSettings::route('/'),
            'edit' => Pages\EditPageSeoSetting::route('/{record}/edit'),
        ];
    }
}
