<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserTypeAssignmentResource\Pages;
use App\Models\User;
use App\Models\UserType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserTypeAssignmentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Assign User Types';

    protected static ?string $modelLabel = 'user type assignment';

    protected static ?string $pluralModelLabel = 'Assign User Types';

    protected static ?string $slug = 'assign-user-types';

    protected static ?string $navigationGroup = 'Application';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canAssignUserTypes() ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $record instanceof User
            ? $actor->canAssignUserTypesTo($record)
            : false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('userTypes.name')
                    ->label('User Types')
                    ->badge()
                    ->placeholder('Customer')
                    ->separator(','),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('userTypes')
                    ->label('User Type')
                    ->relationship('userTypes', 'name')
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('assignTypes')
                    ->label('Assign types')
                    ->icon('heroicon-o-tag')
                    ->modalHeading(fn (User $record) => 'Assign types — '.$record->name)
                    ->modalDescription('Leave empty to treat this user as a Customer. A user can have multiple types.')
                    ->form(function (User $record): array {
                        $assignedIds = $record->userTypes()->pluck('user_types.id');

                        return [
                            Forms\Components\Select::make('user_type_ids')
                                ->label('User Types')
                                ->multiple()
                                ->options(
                                    UserType::query()
                                        ->where(function (Builder $query) use ($assignedIds) {
                                            $query->where('is_active', true)
                                                ->orWhereIn('id', $assignedIds);
                                        })
                                        ->orderBy('sort_order')
                                        ->pluck('name', 'id')
                                )
                                ->preload()
                                ->searchable(),
                        ];
                    })
                    ->fillForm(fn (User $record): array => [
                        'user_type_ids' => $record->userTypes()->pluck('user_types.id')->all(),
                    ])
                    ->visible(fn (User $record): bool => auth()->user()?->canAssignUserTypesTo($record) ?? false)
                    ->action(function (User $record, array $data): void {
                        abort_unless(auth()->user()?->canAssignUserTypesTo($record), 403);
                        $record->userTypes()->sync($data['user_type_ids'] ?? []);
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('first_name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('userTypes');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserTypeAssignments::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'phone'];
    }
}
