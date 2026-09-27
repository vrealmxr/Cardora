<?php

namespace App\Filament\Resources;

use App\Models\IdentityVerification;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;

class IdentityVerificationResource extends RestrictableResource
{
    protected static ?string $model = IdentityVerification::class;
    protected static ?string $navigationGroup = 'Moderation';
    protected static ?string $navigationLabel = 'Didit / διατηρημένες επαληθεύσεις';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function shouldRegisterNavigation(): bool
    {
        return config('didit.enabled') && parent::shouldRegisterNavigation();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.handle')->searchable()->label('Χρήστης'),
            TextColumn::make('source')->badge()->label('Πηγή'),
            TextColumn::make('status')->badge()->label('Κατάσταση'),
            TextColumn::make('current_session_id')->label('Didit session')->copyable(),
            TextColumn::make('verified_at')->dateTime()->label('Εγκρίθηκε'),
            TextColumn::make('expires_at')->dateTime()->sortable()->label('Λήξη / νέο verify'),
            TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->filters([SelectFilter::make('source')->options(['didit' => 'Didit', 'legacy' => 'Διατηρημένη έγκριση'])])->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => IdentityVerificationResource\Pages\ListIdentityVerifications::route('/')];
    }
}
