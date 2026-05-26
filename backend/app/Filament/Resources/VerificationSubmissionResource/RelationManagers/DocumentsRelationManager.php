<?php

namespace App\Filament\Resources\VerificationSubmissionResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Verification files';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('document_type')
                    ->required()
                    ->maxLength(100),
                TextInput::make('storage_disk')
                    ->default('public')
                    ->required(),
                TextInput::make('storage_path')
                    ->required()
                    ->maxLength(255),
                TextInput::make('original_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('mime_type')
                    ->maxLength(100),
                TextInput::make('file_size')
                    ->numeric(),
                DateTimePicker::make('uploaded_at'),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('document_type')
                    ->badge(),
                TextColumn::make('original_name')
                    ->searchable()
                    ->url(fn ($record) => $record->getFileUrl())
                    ->openUrlInNewTab()
                    ->color('primary'),
                TextColumn::make('storage_path')
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('mime_type')
                    ->toggleable(),
                TextColumn::make('file_size')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('uploaded_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Open file')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record) => $record->getFileUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => filled($record->getFileUrl())),
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
