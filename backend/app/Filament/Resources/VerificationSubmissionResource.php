<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerificationSubmissionResource\Pages;
use App\Filament\Resources\VerificationSubmissionResource\RelationManagers\DocumentsRelationManager;
use App\Filament\Support\MarketplaceAdminOptions;
use App\Models\VerificationSubmission;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class VerificationSubmissionResource extends Resource
{
    protected static ?string $model = VerificationSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Submission')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'display_name')
                            ->searchable()
                            ->preload()
                            ->disabledOn('edit')
                            ->required(),
                        Select::make('verification_type')
                            ->options(MarketplaceAdminOptions::verificationTypes())
                            ->disabledOn('edit')
                            ->required(),
                        Select::make('status')
                            ->options(MarketplaceAdminOptions::verificationStatuses())
                            ->required(),
                        Select::make('reviewed_by')
                            ->relationship(
                                name: 'reviewer',
                                titleAttribute: 'display_name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('is_admin', true),
                            )
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('submitted_at'),
                        DateTimePicker::make('reviewed_at'),
                        Textarea::make('reviewer_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Submitted details')
                    ->description('What the member actually entered for this verification request.')
                    ->schema(static::submittedDetailsSchema())
                    ->columns(2)
                    ->visible(fn (?VerificationSubmission $record): bool => filled($record)),
                Section::make('Review guide')
                    ->description('A quick moderation summary based on the verification rules shown to the user.')
                    ->schema([
                        Placeholder::make('uploaded_documents')
                            ->label('Uploaded files')
                            ->content(fn (?VerificationSubmission $record): string => (string) ($record?->documents()->count() ?? 0)),
                        Placeholder::make('accepted_documents')
                            ->label('Accepted document types')
                            ->content(fn (?VerificationSubmission $record): HtmlString => static::renderList(
                                Arr::wrap(data_get($record?->requirements_snapshot, 'accepted_documents', []))
                            )),
                        Placeholder::make('checklist')
                            ->label('Checklist')
                            ->content(fn (?VerificationSubmission $record): HtmlString => static::renderList(
                                Arr::wrap(data_get($record?->requirements_snapshot, 'checklist', []))
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (?VerificationSubmission $record): bool => filled($record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'reviewer'])->withCount('documents'))
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('user.display_name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('verification_type')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'under_review', 'submitted', 'needs_revision' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('documents_count')
                    ->label('Docs')
                    ->numeric(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('reviewer.display_name')
                    ->label('Reviewer')
                    ->placeholder('Unassigned'),
                TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('verification_type')
                    ->options(MarketplaceAdminOptions::verificationTypes()),
                SelectFilter::make('status')
                    ->options(MarketplaceAdminOptions::verificationStatuses()),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSubmission')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (VerificationSubmission $record): string => static::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->action(fn (VerificationSubmission $record) => $record->update([
                        'status' => 'approved',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                    ])),
                Tables\Actions\Action::make('needsRevision')
                    ->label('Needs revision')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->action(fn (VerificationSubmission $record) => $record->update([
                        'status' => 'needs_revision',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                    ])),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (VerificationSubmission $record) => $record->update([
                        'status' => 'rejected',
                        'reviewed_at' => now(),
                        'reviewed_by' => auth()->id(),
                    ])),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerificationSubmissions::route('/'),
            'create' => Pages\CreateVerificationSubmission::route('/create'),
            'view' => Pages\ViewVerificationSubmission::route('/{record}'),
            'edit' => Pages\EditVerificationSubmission::route('/{record}/edit'),
        ];
    }

    protected static function submittedDetailsSchema(): array
    {
        return collect(static::payloadFieldLabels())
            ->map(
                fn (string $label, string $field) => Placeholder::make("payload_{$field}")
                    ->label($label)
                    ->content(fn (?VerificationSubmission $record): string => static::formatPayloadValue($record, $field))
                    ->hidden(fn (?VerificationSubmission $record): bool => ! static::recordHasPayloadField($record, $field))
            )
            ->values()
            ->all();
    }

    protected static function payloadFieldLabels(): array
    {
        return [
            'legal_name' => 'Legal name',
            'date_of_birth' => 'Date of birth',
            'document_type' => 'Document type',
            'document_number' => 'Document number',
            'country' => 'Country',
            'city' => 'City',
            'postal_code' => 'Postal code',
            'address_line' => 'Street and number',
            'account_holder' => 'Account holder',
            'bank_name' => 'Bank',
            'iban' => 'IBAN',
            'bank_confirmation' => 'Ownership confirmed',
        ];
    }

    protected static function recordHasPayloadField(?VerificationSubmission $record, string $field): bool
    {
        return Arr::has($record?->payload ?? [], $field);
    }

    protected static function formatPayloadValue(?VerificationSubmission $record, string $field): string
    {
        $value = data_get($record?->payload, $field);

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->filter(fn ($item) => filled($item))
                ->implode(', ');
        }

        if (blank($value)) {
            return '-';
        }

        return (string) $value;
    }

    protected static function renderList(array $items): HtmlString
    {
        $items = collect($items)
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => '<li>' . e((string) $item) . '</li>')
            ->implode('');

        if ($items === '') {
            return new HtmlString('<span class="text-gray-500">No extra guidance attached.</span>');
        }

        return new HtmlString(sprintf('<ul class="list-disc space-y-1 pl-5">%s</ul>', $items));
    }
}
