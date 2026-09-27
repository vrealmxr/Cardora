<?php

namespace App\Filament\Resources;

use App\Models\DiditSession;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DiditSessionResource extends RestrictableResource
{
    protected static ?string $model = DiditSession::class;
    protected static ?string $navigationGroup = 'Moderation';
    protected static ?string $navigationLabel = 'Didit / διατήρηση & διαγραφές';
    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function shouldRegisterNavigation(): bool { return config('didit.enabled') && parent::shouldRegisterNavigation(); }
    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user_id')->label('User ID')->searchable(),
            TextColumn::make('session_id')->label('Session ID')->copyable()->searchable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('retention_due_at')->dateTime()->sortable()->label('Προθεσμία διαγραφής'),
            TextColumn::make('session_deleted_at')->dateTime()->label('Διαγραφή συνεδρίας'),
            TextColumn::make('deletion_outcome')->label('Αποτέλεσμα διαγραφής')->badge(),
            TextColumn::make('deletion_attempts')->label('Προσπάθειες'),
            TextColumn::make('deletion_attempted_at')->dateTime()->label('Τελευταία προσπάθεια'),
        ])->filters([
            Filter::make('overdue')->label('Εκκρεμείς διαγραφές')->query(fn (Builder $query) => $query
                ->where('retention_due_at', '<=', now())->whereNull('session_deleted_at')),
        ])->defaultSort('retention_due_at');
    }

    public static function getPages(): array
    {
        return ['index' => DiditSessionResource\Pages\ListDiditSessions::route('/')];
    }
}
